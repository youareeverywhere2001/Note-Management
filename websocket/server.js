'use strict';
// Máy chủ WebSocket cộng tác thời gian thực (TV3).
// - Trình duyệt gửi {type:'join', token}. Token do PHP ký bằng WS_SECRET (HMAC-SHA256, hết hạn sau 120 giây).
// - Quyền (Edit / Read-only) được lấy lại từ CSDL qua api/collab/internal.php, không tin hoàn toàn vào token.
// - Chỉ người có quyền Edit mới gửi được 'update'. Bản cập nhật có base_rev cũ bị từ chối ('stale').
// - Nội dung được lưu vào MySQL (qua PHP) sau 700ms không có thay đổi, và khi người cuối cùng rời phòng.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const WebSocket = require('ws');

// Đọc file .env ở thư mục gốc dự án (khi chạy XAMPP). Biến môi trường thật (Docker) được ưu tiên.
for (const f of [path.join(__dirname, '..', '.env')]) {
  try {
    for (const raw of fs.readFileSync(f, 'utf8').split(/\r?\n/)) {
      const line = raw.trim();
      if (!line || line.startsWith('#') || !line.includes('=')) continue;
      const i = line.indexOf('=');
      const k = line.slice(0, i).trim();
      let v = line.slice(i + 1).trim();
      if (v.length >= 2 && (v[0] === '"' || v[0] === "'") && v.endsWith(v[0])) v = v.slice(1, -1);
      if (k && process.env[k] === undefined) process.env[k] = v;
    }
  } catch { /* không có .env: dùng biến môi trường */ }
}

const MAX_CHARS = 500000;          // khớp NOTE_MAX_CONTENT ở PHP
const SAVE_DELAY_MS = 700;
const RECHECK_MS = 30000;

function createServer(opts = {}) {
  const PORT = opts.port ?? (+process.env.WS_PORT || 8081);
  const SECRET = opts.secret ?? process.env.WS_SECRET ?? '';
  const PHP = (opts.phpUrl ?? process.env.PHP_INTERNAL_URL ?? 'http://localhost/Note-Management/public').replace(/\/$/, '');
  const ORIGINS = (opts.origins ?? process.env.WS_ALLOWED_ORIGINS ?? '').split(',').map(s => s.trim()).filter(Boolean);
  if (!SECRET) throw new Error('Thiếu WS_SECRET (phải trùng với WS_SECRET của PHP).');

  const wss = new WebSocket.Server({ port: PORT, maxPayload: 2 * 1024 * 1024 });
  const rooms = new Map();   // noteId -> room

  const send = (ws, obj) => { if (ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj)); };
  const broadcast = (room, obj, except = null) => { for (const c of room.clients) if (c !== except) send(c, obj); };

  function verifyToken(tok) {
    if (typeof tok !== 'string') return null;
    const [body, sig] = tok.split('.');
    if (!body || !sig) return null;
    const expect = crypto.createHmac('sha256', SECRET).update(body).digest('hex');
    const a = Buffer.from(sig), b = Buffer.from(expect);
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return null;
    try {
      const p = JSON.parse(Buffer.from(body.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8'));
      if (!p || typeof p.exp !== 'number' || p.exp < Date.now() / 1000) return null;
      if (!Number.isInteger(p.uid) || !Number.isInteger(p.nid)) return null;
      return p;
    } catch { return null; }
  }

  // Gọi endpoint PHP nội bộ
  async function php(action, data) {
    try {
      const res = await fetch(PHP + '/api/collab/internal.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Internal-Secret': SECRET },
        body: JSON.stringify({ action, ...data }),
        signal: AbortSignal.timeout(10000),
      });
      let j = {};
      try { j = await res.json(); } catch { /* không phải JSON */ }
      return { status: res.status, data: j };
    } catch (e) {
      return { status: 0, data: {} };
    }
  }

  const presence = room => broadcast(room, { type: 'presence', users: [...new Set([...room.clients].map(c => c.userName))] });

  // ----- Lưu vào MySQL (tuần tự, mỗi phòng một hàng đợi) -----
  function scheduleSave(room) {
    clearTimeout(room.timer);
    room.timer = setTimeout(() => flushRoom(room), SAVE_DELAY_MS);
  }
  function flushRoom(room) {
    clearTimeout(room.timer);
    room.queue = room.queue.then(() => doSave(room)).catch(() => {});
    return room.queue;
  }
  async function doSave(room) {
    if (!room.dirty) return;
    const content = room.content, rev = room.rev, uid = room.lastEditor;
    room.dirty = false;
    const r = await php('save', { note_id: room.id, user_id: uid, content });
    if (r.status === 200) {
      room.failures = 0;
      broadcast(room, { type: 'saved', rev, at: r.data.updated_at });
    } else if (r.status === 403 || r.status === 404) {
      // người sửa không còn quyền (hoặc ghi chú đã bị xóa): ngừng nhận chỉnh sửa của họ
      for (const c of room.clients) if (c.uid === uid) { c.perm = 'read'; send(c, { type: 'perm', permission: 'read' }); }
      broadcast(room, { type: 'error', code: 'save_failed', message: 'Không lưu được thay đổi gần nhất (quyền đã bị thu hồi hoặc ghi chú không còn).' });
    } else {
      room.dirty = true;                      // lỗi tạm thời: thử lại
      if (++room.failures >= 3) { room.failures = 0; room.dirty = true; broadcast(room, { type: 'error', code: 'save_failed', message: 'Máy chủ chưa lưu được. Sẽ tự thử lại.' }); }
      room.timer = setTimeout(() => flushRoom(room), 2000);
    }
  }

  async function onJoin(ws, msg) {
    if (ws.joined || ws.joining) return;
    ws.joining = true;
    const p = verifyToken(msg.token);
    if (!p) { send(ws, { type: 'error', code: 'auth', message: 'Phiên kết nối không hợp lệ hoặc đã hết hạn.' }); return ws.close(4001); }
    const r = await php('get', { note_id: p.nid, user_id: p.uid });
    if (r.status !== 200) {
      send(ws, { type: 'error', code: 'forbidden', message: 'Bạn không có quyền truy cập ghi chú này.' });
      return ws.close(4003);
    }
    ws.uid = p.uid; ws.userName = String(p.name || 'Ẩn danh'); ws.noteId = p.nid;
    ws.perm = r.data.permission === 'edit' ? 'edit' : 'read';   // quyền lấy từ CSDL
    let room = rooms.get(p.nid);
    if (!room) {   // kiểm tra lại sau await để hai người vào cùng lúc không tạo hai phòng
      room = { id: p.nid, content: String(r.data.content ?? ''), rev: 0, clients: new Set(), dirty: false, lastEditor: null, timer: null, queue: Promise.resolve(), failures: 0 };
      rooms.set(p.nid, room);
    }
    ws.room = room; ws.joined = true; ws.joining = false;
    room.clients.add(ws);
    send(ws, { type: 'joined', rev: room.rev, content: room.content, permission: ws.perm });
    presence(room);
  }

  function onUpdate(ws, msg) {
    const room = ws.room;
    if (!ws.joined || !room) return;
    if (ws.perm !== 'edit') return send(ws, { type: 'error', code: 'readonly', message: 'Bạn chỉ có quyền xem ghi chú này.' });
    if (typeof msg.content !== 'string' || msg.content.length > MAX_CHARS) return send(ws, { type: 'error', code: 'invalid', message: 'Nội dung không hợp lệ hoặc quá dài.' });
    if (msg.base_rev !== room.rev) return send(ws, { type: 'stale', content: room.content, rev: room.rev });   // không ghi đè bản mới hơn
    room.rev += 1;
    room.content = msg.content;
    room.lastEditor = ws.uid;
    room.dirty = true;
    send(ws, { type: 'ack', rev: room.rev });
    broadcast(room, { type: 'update', content: room.content, rev: room.rev, user: ws.userName }, ws);
    scheduleSave(room);
  }

  function onLeave(ws) {
    const room = ws.room;
    if (!room) return;
    room.clients.delete(ws);
    ws.room = null;
    if (room.clients.size === 0) {
      flushRoom(room).then(() => { if (room.clients.size === 0 && !room.dirty) rooms.delete(room.id); });
    } else presence(room);
  }

  wss.on('connection', (ws, req) => {
    if (ORIGINS.length && !ORIGINS.includes(req.headers.origin || '')) return ws.close(4003);
    ws.isAlive = true;
    ws.msgCount = 0;
    ws.on('pong', () => { ws.isAlive = true; });
    const joinTimer = setTimeout(() => { if (!ws.joined) ws.close(4001); }, 10000);
    ws.on('message', raw => {
      if (++ws.msgCount > 60) return;      // tối đa 60 tin nhắn / giây / kết nối
      let msg;
      try { msg = JSON.parse(raw.toString()); } catch { return; }
      if (!msg || typeof msg !== 'object') return;
      if (msg.type === 'join') onJoin(ws, msg).catch(() => ws.close(1011));
      else if (msg.type === 'update') onUpdate(ws, msg);
    });
    ws.on('close', () => { clearTimeout(joinTimer); onLeave(ws); });
    ws.on('error', () => {});
  });

  const tick = setInterval(() => { for (const c of wss.clients) c.msgCount = 0; }, 1000);
  const ping = setInterval(() => {
    for (const c of wss.clients) { if (!c.isAlive) { c.terminate(); continue; } c.isAlive = false; c.ping(); }
  }, 30000);
  // Kiểm tra lại quyền định kỳ: bị thu hồi chia sẻ thì ngắt kết nối; đổi quyền thì thông báo
  const recheck = setInterval(async () => {
    for (const room of rooms.values()) {
      for (const c of [...room.clients]) {
        const r = await php('perm', { note_id: room.id, user_id: c.uid });
        if (r.status === 403) { send(c, { type: 'kicked' }); c.close(4003); }
        else if (r.status === 200) {
          const np = r.data.permission === 'edit' ? 'edit' : 'read';
          if (np !== c.perm) { c.perm = np; send(c, { type: 'perm', permission: np }); }
        }
      }
    }
  }, opts.recheckMs ?? RECHECK_MS);

  wss.on('listening', () => { if (!opts.quiet) console.log(`WebSocket đang chạy tại ws://localhost:${PORT} (PHP nội bộ: ${PHP})`); });
  wss.on('close', () => { clearInterval(tick); clearInterval(ping); clearInterval(recheck); });
  return { wss, rooms, close: () => new Promise(res => { for (const c of wss.clients) c.terminate(); wss.close(res); }) };
}

module.exports = { createServer };

if (require.main === module) {
  try { createServer(); } catch (e) { console.error(e.message); process.exit(1); }
}

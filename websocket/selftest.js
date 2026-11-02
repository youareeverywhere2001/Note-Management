'use strict';
// Kiểm thử tự động máy chủ WebSocket với một "PHP giả" (không cần MySQL). Chạy: node selftest.js
const http = require('http');
const crypto = require('crypto');
const WebSocket = require('ws');
const { createServer } = require('./server');

const SECRET = 'test-secret-123';
const perms = { 1: { 10: 'edit' }, 2: { 10: 'read' }, 3: {} };   // user -> note -> quyền (3 không có quyền)
const notes = { 10: 'xin chào' };
const saves = [];
let failures = 0;
const ok = (c, name) => { console.log((c ? 'PASS ' : 'FAIL ') + name); if (!c) failures++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const mock = http.createServer((req, res) => {
  let b = '';
  req.on('data', d => (b += d));
  req.on('end', () => {
    const out = (code, o) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
    if (req.headers['x-internal-secret'] !== SECRET) return out(403, { error: 'Forbidden' });
    const j = JSON.parse(b);
    const p = (perms[j.user_id] || {})[j.note_id];
    if (!p) return out(403, { error: 'no access' });
    if (j.action === 'perm') return out(200, { permission: p });
    if (j.action === 'get') return out(200, { permission: p, content: notes[j.note_id], title: 't' });
    if (j.action === 'save') {
      if (p !== 'edit') return out(403, { error: 'read only' });
      notes[j.note_id] = j.content; saves.push({ uid: j.user_id, content: j.content });
      return out(200, { ok: true, updated_at: '2026-01-01 00:00:00' });
    }
    out(400, {});
  });
});

const token = (uid, nid, exp = Math.floor(Date.now() / 1000) + 120, secret = SECRET) => {
  const body = Buffer.from(JSON.stringify({ uid, name: 'U' + uid, nid, perm: 'edit', exp })).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  return body + '.' + crypto.createHmac('sha256', secret).update(body).digest('hex');
};
function client(port) {
  const ws = new WebSocket('ws://127.0.0.1:' + port);
  ws.msgs = [];
  ws.on('message', d => ws.msgs.push(JSON.parse(d.toString())));
  ws.opened = new Promise(r => ws.on('open', r));
  ws.closed = new Promise(r => ws.on('close', c => r(c)));
  ws.next = async (type, ms = 1500) => {
    const t0 = Date.now();
    while (Date.now() - t0 < ms) { const i = ws.msgs.findIndex(m => m.type === type); if (i >= 0) return ws.msgs.splice(i, 1)[0]; await sleep(10); }
    return null;
  };
  return ws;
}

(async () => {
  await new Promise(r => mock.listen(0, r));
  const phpUrl = 'http://127.0.0.1:' + mock.address().port;
  const srv = createServer({ port: 0, secret: SECRET, phpUrl, quiet: true, recheckMs: 400 });
  await new Promise(r => srv.wss.on('listening', r));
  const port = srv.wss.address().port;

  // 1. Token giả / sai chữ ký / hết hạn / không có quyền
  let c = client(port); await c.opened; c.send(JSON.stringify({ type: 'join', token: token(1, 10, undefined, 'wrong') }));
  ok((await c.next('error'))?.code === 'auth', 'Từ chối token sai chữ ký'); ok((await c.closed) === 4001, 'Đóng kết nối 4001');
  c = client(port); await c.opened; c.send(JSON.stringify({ type: 'join', token: token(1, 10, 1) }));
  ok((await c.next('error'))?.code === 'auth', 'Từ chối token hết hạn');
  c = client(port); await c.opened; c.send(JSON.stringify({ type: 'join', token: token(3, 10) }));
  ok((await c.next('error'))?.code === 'forbidden', 'User không có quyền với ghi chú bị từ chối (dù token hợp lệ)');

  // 2. Editor A + viewer B
  const A = client(port), B = client(port); await A.opened; await B.opened;
  A.send(JSON.stringify({ type: 'join', token: token(1, 10) }));
  B.send(JSON.stringify({ type: 'join', token: token(2, 10) }));
  const ja = await A.next('joined'), jb = await B.next('joined');
  ok(ja?.permission === 'edit' && jb?.permission === 'read', 'Quyền lấy từ CSDL: A=edit, B=read');
  ok(ja?.content === 'xin chào', 'Nhận nội dung hiện tại khi vào phòng');
  ok((await A.next('presence'))?.users?.length >= 1, 'Có thông báo presence');

  // 3. A gõ -> B nhận, A nhận ack
  A.send(JSON.stringify({ type: 'update', content: 'bản A1', base_rev: ja.rev }));
  const ack = await A.next('ack'); const up = await B.next('update');
  ok(ack?.rev === 1 && up?.content === 'bản A1', 'Cập nhật của A được phát cho B, A nhận ack');

  // 4. B (read-only) cố sửa -> bị chặn, A không nhận
  B.send(JSON.stringify({ type: 'update', content: 'B phá hoại', base_rev: 1 }));
  ok((await B.next('error'))?.code === 'readonly', 'Read-only không sửa được');
  ok((await A.next('update', 300)) === null, 'Không phát nội dung của người Read-only');

  // 5. Cập nhật cũ (base_rev lỗi thời) bị từ chối, không ghi đè bản mới
  A.send(JSON.stringify({ type: 'update', content: 'bản A2', base_rev: 1 })); await A.next('ack');
  A.send(JSON.stringify({ type: 'update', content: 'bản cũ ghi đè', base_rev: 1 }));
  const st = await A.next('stale');
  ok(st?.content === 'bản A2' && st?.rev === 2, 'Cập nhật cũ bị từ chối và nhận lại bản mới nhất');

  // 6. Lưu xuống "MySQL" (mock) sau ~700ms, đúng người sửa
  await sleep(1200);
  ok(saves.length >= 1 && saves[saves.length - 1].content === 'bản A2' && saves[saves.length - 1].uid === 1, 'Nội dung mới nhất được lưu bởi đúng user (uid=1)');
  ok(notes[10] === 'bản A2', 'Nội dung bền vững (mock DB) = bản mới nhất, không phải bản cũ');
  ok((await A.next('saved', 300)) !== null, 'Máy chủ phát thông báo saved sau khi lưu');

  // 7. Thu hồi quyền: B bị kick sau lần kiểm tra định kỳ; A đổi thành read bị hạ quyền
  perms[2][10] = null;
  ok((await B.next('kicked', 2500)) !== null, 'Thu hồi chia sẻ => người nhận bị ngắt kết nối');
  perms[1][10] = 'read';
  ok((await A.next('perm', 2500))?.permission === 'read', 'Hạ quyền Edit -> Read được áp dụng ngay');
  A.send(JSON.stringify({ type: 'update', content: 'sau khi bị hạ quyền', base_rev: 2 }));
  ok((await A.next('error'))?.code === 'readonly', 'Sau khi bị hạ quyền không sửa được nữa');

  // 8. Kết nối lại: người vào lại thấy nội dung bền vững
  perms[1][10] = 'edit';
  A.close(); await sleep(300);
  const A2 = client(port); await A2.opened; A2.send(JSON.stringify({ type: 'join', token: token(1, 10) }));
  ok((await A2.next('joined'))?.content === 'bản A2', 'Kết nối lại nhận đúng nội dung đã lưu');

  // 9. Tin nhắn trước khi join bị bỏ qua
  const D = client(port); await D.opened; D.send(JSON.stringify({ type: 'update', content: 'x', base_rev: 0 }));
  ok((await D.next('error', 300)) === null, 'Chưa join thì không gửi được update');

  await srv.close(); mock.close();
  console.log(failures ? `\n${failures} kiểm thử THẤT BẠI` : '\nTẤT CẢ kiểm thử WebSocket ĐẠT');
  process.exit(failures ? 1 : 0);
})();

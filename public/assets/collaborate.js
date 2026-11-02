// TV3: cộng tác thời gian thực qua WebSocket.
// - Kết nối bằng token ngắn hạn do PHP cấp (api/collab/token.php); máy chủ WebSocket kiểm tra chữ ký và quyền.
// - Mỗi bản cập nhật mang số phiên bản (rev). Nếu rev cũ hơn phiên bản của máy chủ, máy chủ từ chối ("stale")
//   và gửi lại bản mới nhất để không ghi đè nội dung mới hơn bằng bản cũ.
// - Mất kết nối: tự kết nối lại; trong lúc đó lưu qua HTTP (api/shared/update.php) để không mất dữ liệu.
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const app = $('co-app');
  const noteId = +app.dataset.id;
  const esc = NM.esc;
  const text = $('co-text'), title = $('co-title');

  let canEdit = false, ws = null, wsOk = false, connecting = false, closedForGood = false;
  let rev = 0, lastSynced = '', inflight = false, sentContent = '', retry = 0;
  let inputTimer = null, titleTimer = null, ackTimer = null, reconnectTimer = null, titleSaved = '';

  const setStatus = (t, cls = 'warn') => { $('co-status').innerHTML = `<span class="co-dot ${cls}"></span>${esc(t)}`; };
  const showError = html => { $('co-main').classList.add('d-none'); const b = $('co-error'); b.innerHTML = html; b.classList.remove('d-none'); };
  const warn = (html, type = 'warning') => { $('co-warn').innerHTML = html ? `<div class="alert alert-${type} py-2">${html}</div>` : ''; };
  const now = () => new Date().toLocaleTimeString('vi-VN');

  // ---------- Tải ghi chú ----------
  async function load() {
    if (!noteId) { showError('Thiếu mã ghi chú. <a href="shared.php">Quay lại</a>'); return; }
    let note;
    try { note = (await api('shared/get.php?id=' + noteId)).note; }
    catch (err) {
      if (err.status === 423) {
        if (await NM.unlockNote(noteId)) return load();
        showError('Ghi chú đang được khóa bằng mật khẩu. <button class="btn btn-sm btn-primary ms-2" id="co-unlock">Mở khóa</button>');
        $('co-unlock').onclick = () => { $('co-error').classList.add('d-none'); load(); };
        return;
      }
      showError(esc(err.message) + ' <a href="shared.php">Quay lại</a>');
      return;
    }
    canEdit = note.role === 'owner' || note.role === 'edit';
    title.value = titleSaved = note.title;
    text.value = lastSynced = note.content;
    title.readOnly = text.readOnly = !canEdit;

    const roleTxt = { owner: 'Chủ sở hữu', edit: 'Được chỉnh sửa', read: 'Chỉ xem' }[note.role];
    $('co-badge').textContent = roleTxt;
    $('co-badge').className = 'badge ' + (canEdit ? 'text-bg-success' : 'text-bg-secondary');
    const o = note.owner;
    $('co-meta').innerHTML = note.role === 'owner' ? 'Ghi chú của bạn'
      : `${o.avatar ? `<img class="avatar" width="20" height="20" src="${esc(o.avatar)}" alt="">` : ''}Chia sẻ bởi <strong>${esc(o.name)}</strong> (${esc(o.email)})`;
    $('co-atts').innerHTML = note.attachments.length
      ? '<div class="fw-semibold mb-1">Tệp đính kèm</div>' + note.attachments.map(a => `<div><a href="api/attachments/download.php?id=${a.id}" target="_blank" rel="noopener">📎 ${esc(a.file_name)}</a></div>`).join('')
      : '';
    $('co-main').classList.remove('d-none');
    setStatus('Đang kết nối...');
    connect();
  }

  // ---------- WebSocket ----------
  async function connect() {
    if (connecting || wsOk || closedForGood) return;
    connecting = true;
    let t;
    try { t = await api('collab/token.php?note_id=' + noteId); }
    catch (err) {
      connecting = false;
      if (err.status === 423 || err.status === 404) { showError(esc(err.message)); closedForGood = true; return; }
      setStatus('Không có thời gian thực — lưu qua HTTP (' + err.message + ')', 'warn');
      scheduleReconnect();
      return;
    }
    const sock = new WebSocket(t.ws_url);
    ws = sock;
    sock.onopen = () => sock.send(JSON.stringify({ type: 'join', token: t.token }));
    sock.onmessage = ev => { let m; try { m = JSON.parse(ev.data); } catch { return; } if (ws === sock) handle(m); };
    sock.onerror = () => {};
    sock.onclose = () => {
      if (ws !== sock) return;
      ws = null; wsOk = false; connecting = false; inflight = false; clearTimeout(ackTimer);
      $('co-users').textContent = '';
      if (closedForGood) return;
      setStatus('Mất kết nối, đang thử lại...', 'warn');
      scheduleReconnect();
    };
  }
  function scheduleReconnect() {
    clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(connect, Math.min(15000, 1000 * 2 ** Math.min(retry++, 4)));
  }
  window.addEventListener('online', () => { retry = 0; connect(); });

  function applyRemote(content) {
    const s = text.selectionStart, e = text.selectionEnd;
    text.value = content;
    try { text.setSelectionRange(Math.min(s, content.length), Math.min(e, content.length)); } catch { /* bỏ qua */ }
  }

  function handle(m) {
    switch (m.type) {
      case 'joined': {
        connecting = false; wsOk = true; retry = 0; rev = m.rev;
        canEdit = m.permission === 'edit';
        text.readOnly = title.readOnly = !canEdit;
        const unsent = text.value !== lastSynced;
        if (!unsent) { applyRemote(m.content); lastSynced = m.content; warn(''); }
        else if (m.content === lastSynced) { sendUpdate(); }   // chưa ai sửa trong lúc mất kết nối: gửi phần mình đã gõ
        else {                                                // cả hai bên cùng đã sửa: để người dùng chọn
          warn(`Trong lúc bạn mất kết nối, người khác đã sửa ghi chú.
            <button class="btn btn-sm btn-outline-dark ms-2" id="co-mine">Giữ bản của tôi</button>
            <button class="btn btn-sm btn-outline-dark ms-1" id="co-theirs">Dùng bản mới nhất</button>`);
          $('co-mine').onclick = () => { lastSynced = m.content; warn(''); sendUpdate(); };
          $('co-theirs').onclick = () => { applyRemote(m.content); lastSynced = m.content; warn(''); };
        }
        setStatus(canEdit ? 'Đã kết nối — cộng tác trực tiếp' : 'Đã kết nối — chế độ chỉ xem', 'ok');
        break;
      }
      case 'update':
        rev = m.rev; lastSynced = m.content; applyRemote(m.content);
        setStatus('Đã nhận cập nhật từ ' + (m.user || 'người khác') + ' lúc ' + now(), 'ok');
        break;
      case 'ack':
        rev = m.rev; inflight = false; clearTimeout(ackTimer); lastSynced = sentContent;
        if (text.value !== lastSynced) sendUpdate();
        break;
      case 'stale':   // bản của mình cũ hơn: nhận bản mới nhất thay vì ghi đè
        rev = m.rev; inflight = false; clearTimeout(ackTimer); lastSynced = m.content; applyRemote(m.content);
        warn('Có người vừa chỉnh sửa cùng lúc; ghi chú đã được cập nhật theo bản mới nhất.');
        setTimeout(() => warn(''), 4000);
        break;
      case 'saved': setStatus('Đã lưu lúc ' + now(), 'ok'); break;
      case 'presence': $('co-users').textContent = 'Đang mở: ' + m.users.join(', '); break;
      case 'perm':
        canEdit = m.permission === 'edit'; text.readOnly = title.readOnly = !canEdit;
        $('co-badge').textContent = canEdit ? 'Được chỉnh sửa' : 'Chỉ xem';
        warn('Quyền truy cập của bạn đã được thay đổi: ' + (canEdit ? 'có thể chỉnh sửa.' : 'chỉ xem.'), 'info');
        break;
      case 'kicked':
        closedForGood = true; showError('Quyền truy cập ghi chú này đã bị thu hồi. <a href="shared.php">Quay lại</a>'); ws && ws.close();
        break;
      case 'error':
        if (m.code === 'auth' || m.code === 'forbidden') { closedForGood = true; showError(esc(m.message || 'Không có quyền truy cập.')); }
        else setStatus(m.message || 'Có lỗi xảy ra.', 'bad');
        break;
    }
  }

  // ---------- Gửi chỉnh sửa ----------
  function sendUpdate() {
    if (!wsOk || !canEdit || inflight) return;
    const content = text.value;
    if (content === lastSynced) return;
    inflight = true; sentContent = content;
    ws.send(JSON.stringify({ type: 'update', content, base_rev: rev }));
    clearTimeout(ackTimer);
    ackTimer = setTimeout(() => { if (inflight && ws) ws.close(); }, 8000);   // không có phản hồi: kết nối lại
  }
  async function httpSave() {
    const content = text.value;
    if (content === lastSynced) return;
    try {
      await api('shared/update.php', { method: 'POST', body: { id: noteId, content } });
      lastSynced = content; setStatus('Đã lưu (HTTP) lúc ' + now(), 'ok');
    } catch (err) { setStatus('Lưu thất bại: ' + err.message, 'bad'); }
  }
  text.addEventListener('input', () => {
    if (!canEdit) return;
    setStatus('Đang đồng bộ...', 'warn');
    clearTimeout(inputTimer);
    inputTimer = setTimeout(() => (wsOk ? sendUpdate() : httpSave()), wsOk ? 150 : 600);
  });
  title.addEventListener('input', () => {
    if (!canEdit) return;
    clearTimeout(titleTimer);
    titleTimer = setTimeout(async () => {
      const v = title.value.trim();
      if (v === titleSaved) return;
      try { await api('shared/update.php', { method: 'POST', body: { id: noteId, title: v } }); titleSaved = v; setStatus('Đã lưu tiêu đề lúc ' + now(), 'ok'); }
      catch (err) { setStatus('Lưu tiêu đề thất bại: ' + err.message, 'bad'); }
    }, 700);
  });
  window.addEventListener('beforeunload', e => { if (canEdit && (inflight || text.value !== lastSynced)) { e.preventDefault(); e.returnValue = ''; } });

  load();
})();

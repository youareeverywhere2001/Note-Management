// TV3: trang share.php — chia sẻ theo email, đổi quyền, thu hồi.
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const sel = $('sh-note');
  if (!sel) return;
  const msg = (t, type = 'danger') => { $('sh-msg').innerHTML = t ? `<div class="alert alert-${type} py-2 mb-0">${esc(t)}</div>` : ''; };

  
async function load() {
    msg('');
    $('sh-open').href = 'collaborate.php?id=' + sel.value;

    try {
      const r = await api('shares/list.php?note_id=' + sel.value);

      $('sh-list').innerHTML = r.shares.length ? r.shares.map(s => `
        <div class="list-group-item share-member" data-sid="${s.id}">
          <div class="share-member-info">
            <div class="share-avatar">
              ${esc((s.display_name || s.email || '?').charAt(0).toUpperCase())}
            </div>

            <div class="share-member-details">
              <div class="fw-semibold">${esc(s.display_name)}</div>
              <div class="small text-secondary">${esc(s.email)}</div>
            </div>
          </div>

          <div class="share-member-actions">
            <select class="form-select form-select-sm"
                    data-perm
                    aria-label="Quyền">
              <option value="read" ${s.permission === 'read' ? 'selected' : ''}>Chỉ xem</option>
              <option value="edit" ${s.permission === 'edit' ? 'selected' : ''}>Được sửa</option>
            </select>

            <button type="button"
                    class="share-revoke-btn"
                    data-revoke>
              Thu hồi
            </button>
          </div>
        </div>
      `).join('') : `
        <div class="share-no-members">
          Chưa chia sẻ ghi chú này cho ai.
        </div>
      `;

    } catch (err) {
      msg(err.message);
    }
}

  sel.addEventListener('change', load);

  $('sh-form').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const r = await api('shares/create.php', { method: 'POST', body: { note_id: +sel.value, email: $('sh-email').value, permission: $('sh-perm').value } });
      msg(r.updated ? 'Đã cập nhật quyền chia sẻ.' : 'Đã chia sẻ ghi chú.', 'success');
      $('sh-email').value = '';
      load();
    } catch (err) { msg(err.message); }
  });

  $('sh-list').addEventListener('change', async e => {
    if (!e.target.matches('[data-perm]')) return;
    try {
      await api('shares/update.php', { method: 'POST', body: { share_id: +e.target.closest('[data-sid]').dataset.sid, permission: e.target.value } });
      msg('Đã đổi quyền.', 'success');
    } catch (err) { msg(err.message); load(); }
  });
  $('sh-list').addEventListener('click', async e => {
    if (!e.target.matches('[data-revoke]')) return;
    if (!confirm('Thu hồi quyền chia sẻ này?')) return;
    try {
      await api('shares/delete.php', { method: 'POST', body: { share_id: +e.target.closest('[data-sid]').dataset.sid } });
      msg('Đã thu hồi chia sẻ.', 'success');
      load();
    } catch (err) { msg(err.message); }
  });
  load();
})();

// TV3: tiện ích hộp thoại (NM), khóa/mở khóa ghi chú, nút chia sẻ + cộng tác trong trình soạn thảo.
// Gọi API qua window.api() (assets/api.js) nên tự gửi CSRF và tự quay về trang đăng nhập khi hết phiên.
(() => {
  'use strict';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const NM = (window.NM = {});
  NM.esc = esc;

  // Thông báo nhỏ ở góc màn hình (dùng khi không có hộp thoại nào đang mở)
  NM.toast = (msg, type = 'danger') => {
    let host = document.getElementById('nt-toasts');
    if (!host) { host = document.createElement('div'); host.id = 'nt-toasts'; host.className = 'nt-toasts'; document.body.appendChild(host); }
    const d = document.createElement('div');
    d.className = `alert alert-${type} shadow-sm py-2`;
    d.textContent = msg;
    host.appendChild(d);
    setTimeout(() => d.remove(), 5000);
  };

  // Hộp thoại biểu mẫu. onSubmit(values) là hàm async: ném lỗi => hiện lỗi trong hộp thoại và giữ hộp thoại mở.
  // Trả về Promise: kết quả của onSubmit, hoặc null nếu người dùng hủy.
  NM.form = ({ title, intro = '', fields = [], submit = 'OK', danger = false, onSubmit }) => new Promise(resolve => {
    const d = document.createElement('dialog');
    d.className = 'nt-dialog nt-narrow';
    const inputs = fields.map(f => {
      const common = `class="form-control" id="nmf-${f.name}" name="${f.name}" autocomplete="${f.autocomplete || 'off'}" ${f.required === false ? '' : 'required'}`;
      const ctl = f.type === 'textarea'
        ? `<textarea ${common} rows="4" maxlength="${f.maxlength || 1000}"></textarea>`
        : `<input ${common} type="${f.type || 'text'}">`;
      return `<div class="mb-3"><label class="form-label" for="nmf-${f.name}">${esc(f.label)}</label>${ctl}</div>`;
    }).join('');
    d.innerHTML = `<form method="dialog">
      <h2 class="h5 mb-2">${esc(title)}</h2>
      ${intro ? `<p class="text-secondary small">${esc(intro)}</p>` : ''}
      <div class="alert alert-danger py-2 d-none" role="alert" data-err></div>
      ${inputs}
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary" data-cancel>Hủy</button>
        <button type="submit" class="btn ${danger ? 'btn-danger' : 'nm-submit'}">${esc(submit)}</button>
      </div></form>`;
    document.body.appendChild(d);
    let result = null;
    const form = d.querySelector('form');
    const err = d.querySelector('[data-err]');
    d.addEventListener('close', () => { d.remove(); resolve(result); });
    d.querySelector('[data-cancel]').addEventListener('click', () => d.close());
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      btn.disabled = true;
      err.classList.add('d-none');
      try {
        const values = Object.fromEntries(new FormData(form).entries());
        result = onSubmit ? ((await onSubmit(values)) ?? true) : values;
        d.close();
      } catch (ex) {
        err.textContent = ex.message || 'Có lỗi xảy ra.';
        err.classList.remove('d-none');
        btn.disabled = false;
      }
    });
    d.showModal();
    const first = d.querySelector('input,textarea');
    if (first) first.focus();
  });

  // Hộp thoại chỉ để hiển thị kết quả (HTML đã được tự escape bởi người gọi)
  NM.panel = (title, html) => new Promise(resolve => {
    const d = document.createElement('dialog');
    d.className = 'nt-dialog';
    d.innerHTML = `<div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 mb-0">${esc(title)}</h2><button type="button" class="btn-close" aria-label="Đóng" data-x></button></div>
      <div data-body>${html}</div>
      <div class="text-end mt-3"><button type="button" class="btn btn-primary btn-sm" data-x>Đóng</button></div>`;
    document.body.appendChild(d);
    d.querySelectorAll('[data-x]').forEach(b => b.addEventListener('click', () => d.close()));
    d.addEventListener('close', () => { d.remove(); resolve(); });
    d.showModal();
  });

  // ---------- Mở khóa ghi chú: trả về true nếu mở khóa thành công ----------
  NM.unlockNote = async id => {
    const r = await NM.form({
      title: '🔒 Ghi chú được bảo vệ',
      intro: 'Nhập mật khẩu của ghi chú này để mở.',
      fields: [{ name: 'password', label: 'Mật khẩu ghi chú', type: 'password' }],
      submit: 'Mở khóa',
      onSubmit: v => api('locks/unlock.php', { method: 'POST', body: { id, password: v.password } }),
    });
    return !!r;
  };
  // TV2 gọi hàm này khi người dùng bấm vào ghi chú đang khóa (HTTP 423)
  window.onLockedNoteOpen = async id => { if (await NM.unlockNote(id)) window.NoteApp?.open(id); };

  // ---------- Thanh công cụ trong trình soạn thảo (chỉ có ở trang chủ) ----------
  const bar = document.getElementById('nt-adv');
  if (!bar || !window.NoteApp) return;
  let locked = false;
  const show = () => {
    const set = (k, v) => { const b = bar.querySelector(`[data-adv="${k}"]`); if (b) b.hidden = !v; };
    set('lock', !locked); set('change', locked); set('remove', locked); set('relock', locked);
  };
  document.addEventListener('nt:editor-open', e => { locked = !!(e.detail.note && e.detail.note.is_locked); show(); });

  const needId = async () => {
    const id = await NoteApp.ensureSaved();
    if (!id) NoteApp.toast('Hãy nhập tiêu đề hoặc nội dung trước.', 'warning');
    return id;
  };
  const pwFields = (...names) => names.map(([name, label]) => ({ name, label, type: 'password', autocomplete: 'new-password' }));
  const sameCheck = v => { if (v.new_password !== v.confirm) throw new Error('Hai mật khẩu không khớp.'); };

  const actions = {
    async lock() {
      const id = await needId(); if (!id) return;
      const ok = await NM.form({
        title: 'Khóa ghi chú',
        intro: 'Mật khẩu này tách biệt với mật khẩu đăng nhập. Nếu quên, bạn sẽ không mở được ghi chú.',
        fields: pwFields(['new_password', 'Mật khẩu ghi chú (tối thiểu 6 ký tự)'], ['confirm', 'Nhập lại mật khẩu']),
        submit: 'Khóa ghi chú',
        onSubmit: async v => { sameCheck(v); return api('locks/set.php', { method: 'POST', body: { id, password: v.new_password } }); },
      });
      if (ok) { locked = true; show(); NoteApp.toast('Đã khóa ghi chú.', 'success'); }
    },
    async change() {
      const id = await needId(); if (!id) return;
      const ok = await NM.form({
        title: 'Đổi mật khẩu ghi chú',
        fields: [{ name: 'old_password', label: 'Mật khẩu hiện tại', type: 'password' }, ...pwFields(['new_password', 'Mật khẩu mới'], ['confirm', 'Nhập lại mật khẩu mới'])],
        submit: 'Đổi mật khẩu',
        onSubmit: async v => { sameCheck(v); return api('locks/change.php', { method: 'POST', body: { id, old_password: v.old_password, new_password: v.new_password } }); },
      });
      if (ok) NoteApp.toast('Đã đổi mật khẩu ghi chú.', 'success');
    },
    async remove() {
      const id = await needId(); if (!id) return;
      const ok = await NM.form({
        title: 'Gỡ khóa ghi chú', intro: 'Nhập mật khẩu hiện tại để tắt khóa. Ghi chú sẽ không còn cần mật khẩu.',
        fields: [{ name: 'password', label: 'Mật khẩu hiện tại', type: 'password' }],
        submit: 'Gỡ khóa', danger: true,
        onSubmit: v => api('locks/remove.php', { method: 'POST', body: { id, password: v.password } }),
      });
      if (ok) { locked = false; show(); NoteApp.toast('Đã gỡ khóa ghi chú.', 'success'); }
    },
    async relock() {
      const id = NoteApp.currentId(); if (!id) return;
      await NoteApp.closeEditor();   // lưu nốt rồi đóng trước khi khóa, để tự động lưu không bị chặn
      try { await api('locks/relock.php', { method: 'POST', body: { id } }); } catch (err) { NoteApp.toast(err.message); }
      NoteApp.reload();
    },
    async share() { const id = await needId(); if (id) location.href = 'share.php?id=' + id; },
    async collab() { const id = await needId(); if (id) location.href = 'collaborate.php?id=' + id; },
  };
  bar.addEventListener('click', e => {
    const b = e.target.closest('[data-adv]');
    if (!b || !actions[b.dataset.adv]) return;   // summary/qa do ai.js xử lý
    actions[b.dataset.adv]().catch(err => NoteApp.toast(err.message));
  });
})();

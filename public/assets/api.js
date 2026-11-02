// Lớp gọi API dùng chung (TV3 có thể chèn hàng đợi offline/đồng bộ tại đây).
// Dùng đường dẫn tương đối "api/..." nên chạy được ở bất kỳ cổng/domain nào.
(() => {
  'use strict';
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  async function api(path, { method = 'GET', body = null, form = null } = {}) {
    const opt = { method, headers: { 'X-CSRF-Token': csrf(), Accept: 'application/json' }, credentials: 'same-origin' };
    if (form) opt.body = form;                       // multipart (upload tệp)
    else if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }

    let res;
    try { res = await fetch('api/' + path, opt); }
    catch { throw Object.assign(new Error('Không kết nối được máy chủ. Hãy kiểm tra mạng.'), { status: 0 }); }

    let data = {};
    try { data = await res.json(); } catch { /* phản hồi không phải JSON */ }

    if (res.status === 401) { location.href = 'login.php'; throw Object.assign(new Error('Hết phiên đăng nhập.'), { status: 401 }); }
    if (!res.ok) throw Object.assign(new Error(data.error || 'Có lỗi xảy ra (' + res.status + ').'), { status: res.status, data });
    return data;
  }
  window.api = api;
})();

// ---------- TV1: gửi các form có thuộc tính data-api tới API JSON ----------
document.addEventListener('submit', async function (event) {
  const form = event.target.closest('form[data-api]');
  if (!form) return;
  event.preventDefault();
  const button = form.querySelector('button[type="submit"], button:not([type])');
  if (button) { button.disabled = true; button.dataset.oldText = button.textContent; button.textContent = 'Đang xử lý...'; }
  try {
    const formData = new FormData(form);
    const csrf = formData.get('_csrf') || '';
    const options = { method: 'POST', headers: { 'X-CSRF-Token': csrf } };
    if (form.enctype === 'multipart/form-data') {
      formData.delete('_csrf'); options.body = formData;
    } else {
      const data = Object.fromEntries(formData.entries()); delete data._csrf;
      options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data);
    }
    const response = await fetch(form.dataset.api, options);
    const result = await response.json();
    let box = form.querySelector('.api-message');
    if (!box) { box = document.createElement('div'); box.className = 'api-message mb-3'; form.prepend(box); }
    box.className = 'api-message alert ' + (result.success ? 'alert-success' : 'alert-danger');
    box.textContent = result.message || 'Có lỗi xảy ra.';
    if (result.success) {
      if (result.data?.redirect) setTimeout(() => location.href = result.data.redirect, 700);
      else if (form.dataset.api.includes('reset-password.php') && result.data?.step === 'newpw') setTimeout(() => location.reload(), 500);
      else if (form.dataset.api.includes('profile/update.php') || form.dataset.api.includes('preferences/update.php')) setTimeout(() => location.reload(), 700);
    }
  } catch (error) { alert('Không thể kết nối máy chủ. Vui lòng thử lại.'); }
  finally { if (button) { button.disabled = false; button.textContent = button.dataset.oldText; } }
});

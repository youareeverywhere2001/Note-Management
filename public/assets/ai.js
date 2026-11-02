// TV3: AI Tóm tắt và AI Hỏi đáp. Chỉ gọi API của máy chủ (api/ai/*), khóa Gemini không bao giờ có mặt ở trình duyệt.
(() => {
  'use strict';
  const NMAI = (window.NMAI = {});
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const lines = s => esc(s).replace(/\n/g, '<br>');

  // Gọi API AI; nếu ghi chú đang khóa (423) thì hỏi mật khẩu rồi gọi lại một lần.
  async function call(path, body) {
    try { return await api(path, { method: 'POST', body }); }
    catch (err) {
      if (err.status === 423 && await NM.unlockNote(body.note_id)) return api(path, { method: 'POST', body });
      throw err;
    }
  }

  NMAI.summary = async id => {
    const r = await call('ai/summary.php', { note_id: id });
    return `<div class="nm-ai-answer">${lines(r.summary)}</div>${r.truncated ? '<p class="small text-secondary mt-2">Ghi chú dài nên chỉ phần đầu được tóm tắt.</p>' : ''}`;
  };
  NMAI.answerHtml = r => {
    const refs = (r.references || []).length
      ? `<div class="mt-3"><div class="small fw-semibold mb-1">Trích từ ghi chú</div>${r.references.map(q => `<blockquote class="nm-ref">${esc(q)}</blockquote>`).join('')}</div>`
      : `<p class="small text-secondary mt-2">${r.found === false ? 'Ghi chú không có thông tin để trả lời.' : 'AI không đưa ra đoạn trích nào kiểm chứng được từ ghi chú.'}</p>`;
    return `<div class="nm-ai-answer">${lines(r.answer)}</div>${refs}`;
  };
  NMAI.ask = async (id, question) => NMAI.answerHtml(await call('ai/qa.php', { note_id: id, question }));

  // ----- Nút trong trình soạn thảo (trang chủ) -----
  const bar = document.getElementById('nt-adv');
  if (bar && window.NoteApp) {
    bar.addEventListener('click', async e => {
      const b = e.target.closest('[data-adv="summary"],[data-adv="qa"]');
      if (!b) return;
      const id = await NoteApp.ensureSaved();
      if (!id) { NoteApp.toast('Hãy nhập nội dung trước khi dùng AI.', 'warning'); return; }
      try {
        if (b.dataset.adv === 'summary') {
         b.disabled = true;
b.textContent = 'Đang tóm tắt...';
try {
  await NM.panel('Tóm tắt ghi chú', await NMAI.summary(id));
} finally {
  b.disabled = false;
  b.textContent = 'Tóm tắt AI';
}
        } else {
          await NM.form({
            title: 'Hỏi về ghi chú này', 
            intro: 'AI chỉ trả lời dựa trên nội dung ghi chú.',
            fields: [{ name: 'question', label: 'Câu hỏi của bạn', type: 'textarea' }], submit: 'Gửi câu hỏi',
            onSubmit: async v => { const html = await NMAI.ask(id, v.question); setTimeout(() => NM.panel('Câu trả lời từ AI', `<div class="nm-q">${lines(v.question)}</div>${html}`), 0); },
          });
        }
      } catch (err) { NoteApp.toast(err.message); }
    });
  }

  // ----- Trang summary.php / qa.php -----
  const page = document.getElementById('ai-page');
  if (!page) return;
  const sel = document.getElementById('ai-note'), out = document.getElementById('ai-out'), go = document.getElementById('ai-go');
  const isQa = page.dataset.mode === 'qa';
  const q = document.getElementById('ai-question');
  const preselect = new URLSearchParams(location.search).get('id');

  (async () => {
    try {
      const r = await api('ai/notes.php');
      sel.innerHTML = r.notes.length
  ? r.notes.map(n => `<option value="${n.id}">${n.is_locked ? '[Đã khóa] ' : ''}${esc(n.title || '(Không có tiêu đề)')}${n.is_owner ? '' : ' — của ' + esc(n.owner_name)}</option>`).join('')
  : '<option value="">Chưa có ghi chú nào</option>';
      if (preselect && [...sel.options].some(o => o.value === preselect)) sel.value = preselect;
      go.disabled = !r.notes.length;
    } catch (err) { out.innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`; }
  })();

  go.addEventListener('click', async () => {
    const id = +sel.value;
    if (!id) return;
    if (isQa && !q.value.trim()) { out.innerHTML = '<div class="alert alert-warning">Vui lòng nhập câu hỏi.</div>'; return; }
    go.disabled = true;
    out.innerHTML = '<div class="text-secondary">Đang chờ AI...</div>';
    try {
      const html = isQa ? await NMAI.ask(id, q.value.trim()) : await NMAI.summary(id);
      out.innerHTML = `<div class="card"><div class="card-body">${isQa ? `<div class="nm-q">${lines(q.value.trim())}</div>` : ''}${html}</div></div>`;
    } catch (err) { out.innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`; }
    finally { go.disabled = false; }
  });
})();
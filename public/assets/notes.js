// Trang ghi chú (TV2): hiển thị, tìm kiếm, nhãn, soạn thảo + tự động lưu, đính kèm.
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const app = $('nt-app');
  const state = { notes: [], labels: [], active: new Set(), view: app.dataset.view === 'list' ? 'list' : 'grid' };

  // ---------- Tiện ích ----------
  const fmtDate = s => s
    ? new Date(s.replace(' ', 'T') + 'Z').toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    })
    : '';
  const fmtSize = b => b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : (b / 1048576).toFixed(1) + ' MB';

  // Thông báo: nếu đang mở hộp thoại thì hiện trong hộp thoại đó (nếu không sẽ bị che)
  function toast(msg, type = 'danger') {
    const dlg = [...document.querySelectorAll('dialog[open]')].pop();
    let host = $('nt-toasts');
    if (dlg) {
      host = dlg.querySelector('.nt-toasts-in');
      if (!host) { host = document.createElement('div'); host.className = 'nt-toasts-in'; dlg.appendChild(host); }
    }
    const d = document.createElement('div');
    d.className = `alert alert-${type} shadow-sm py-2`;
    d.textContent = msg;
    host.appendChild(d);
    setTimeout(() => d.remove(), 5000);
  }

  // Hộp thoại xác nhận (dùng thẻ <dialog> để nằm được trên hộp thoại soạn thảo)
  function confirmBox(msg, okLabel = 'Xóa') {
    return new Promise(res => {
      const d = $('nt-confirm');
      $('nt-confirm-msg').textContent = msg;
      $('nt-confirm-ok').textContent = okLabel;
      d.returnValue = '';
      d.addEventListener('close', () => res(d.returnValue === 'ok'), { once: true });
      d.showModal();
    });
  }

  // Đóng hộp thoại khi bấm ra ngoài (chỉ khi nhấn chuột xuống VÀ nhả ra ngoài, tránh đóng nhầm khi bôi đen chữ)
  function closeOnBackdrop(d) {
    let downOutside = false;
    const out = e => { const r = d.getBoundingClientRect(); return e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom; };
    d.addEventListener('mousedown', e => { downOutside = e.target === d && out(e); });
    d.addEventListener('click', e => { if (downOutside && e.target === d && out(e)) d.close(); downOutside = false; });
  }

  // Chữ trên thẻ ghi chú: đen/trắng tùy độ sáng của "màu ghi chú" trong Tùy chọn
  function setupContrast() {
    const bg = getComputedStyle(document.documentElement).getPropertyValue('--note-bg').trim();
    const m = /^#([0-9a-f]{6})$/i.exec(bg);
    if (!m) return;
    const n = parseInt(m[1], 16);
    const lum = (0.299 * (n >> 16 & 255) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255;
    app.style.setProperty('--nt-fg', lum > 0.55 ? '#212529' : '#f8f9fa');
  }

  // ---------- Nhãn (thanh lọc) ----------
  async function loadLabels() {
    const r = await api('labels/list.php');
    state.labels = r.labels;
    for (const id of [...state.active]) if (!state.labels.some(l => l.id === id)) state.active.delete(id);
    renderChips();
  }
  function renderChips() {
    $('nt-chips').innerHTML = state.labels.length
      ? state.labels.map(l => `<button type="button" class="btn btn-sm rounded-pill ${state.active.has(l.id) ? 'btn-primary' : 'btn-outline-secondary'}" data-lid="${l.id}" aria-pressed="${state.active.has(l.id)}">#${esc(l.name)}</button>`).join('')
      : '<span class="text-secondary small">Chưa có nhãn</span>';
  }
  $('nt-chips').addEventListener('click', e => {
    const b = e.target.closest('[data-lid]');
    if (!b) return;
    const id = +b.dataset.lid;
    state.active.has(id) ? state.active.delete(id) : state.active.add(id);   // chọn nhiều nhãn
    renderChips();
    loadNotes();
  });

  // ---------- Danh sách ghi chú ----------
  let reqSeq = 0;   // bỏ qua kết quả của yêu cầu cũ nếu có yêu cầu mới hơn
  async function loadNotes() {
    const seq = ++reqSeq;
    const p = new URLSearchParams({ q: $('nt-search').value.trim(), sort: $('nt-sort').value });
    if (state.active.size) p.set('labels', [...state.active].join(','));
    try {
      const r = await api('notes/list.php?' + p);
      if (seq !== reqSeq) return;
      state.notes = r.notes;
      renderNotes();
    } catch (err) { if (seq === reqSeq) { toast(err.message); $('nt-notes').innerHTML = '<div class="nt-empty">Không tải được ghi chú. Hãy thử tải lại trang.</div>'; } }
  }

  
function cardHtml(n) {
    const tags = n.label_ids
        .map(id => state.labels.find(l => l.id === id))
        .filter(Boolean)
        .map(l => `<span class="nt-tag">#${esc(l.name)}</span>`)
        .join('');

    const icons = [
        n.is_locked ? ['Đã khóa', 'Ghi chú có mật khẩu'] : null,
        n.is_shared ? ['Đã chia sẻ', 'Ghi chú được chia sẻ'] : null,
        n.attach_count ? ['Tệp đính kèm', n.attach_count + ' tệp đính kèm'] : null
    ].filter(Boolean).map(([text, title]) =>
        `<span class="nt-status-tag" title="${esc(title)}">${text}</span>`
    ).join('');

    const when = $('nt-sort').value === 'created'
        ? fmtDate(n.created_at)
        : fmtDate(n.updated_at);

    const pinTxt = n.is_pinned ? 'Bỏ ghim' : 'Ghim lên đầu';

    return `
        <article class="nt-card" tabindex="0" data-id="${n.id}">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h2 class="h6 nt-title">
                    ${esc(n.title) || '<span class="opacity-50">Không có tiêu đề</span>'}
                </h2>

                <button type="button"
        class="nt-pin ${n.is_pinned ? 'is-on' : ''}"
        data-pin="${n.id}"
        aria-pressed="${n.is_pinned}"
        aria-label="${pinTxt}"
        title="${pinTxt}">
    <svg xmlns="http://www.w3.org/2000/svg"
         width="34" height="18"
         viewBox="0 0 24 24"
         fill="none"
         stroke="currentColor"
         stroke-width="1.8"
         stroke-linecap="round"
         stroke-linejoin="round">
        <path d="m16 3 5 5-7 1-4 4-1 7-5-5 7-1 4-4 1-7Z"/>
        <path d="m9 15-6 6"/>
    </svg>
</button>
            </div>

            <p class="nt-snippet">${
                n.is_locked
                    ? '<em>Ghi chú được bảo vệ bằng mật khẩu</em>'
                    : esc(n.snippet)
            }</p>

            <div class="nt-meta">
                ${icons ? `<span class="nt-icons">${icons}</span>` : ''}
                ${tags}
                <span>${when}</span>
            </div>
        </article>
    `;
}

  function renderNotes() {
    const n = state.notes.length;
    $('nt-count').textContent = n + ' ghi chú';
    const filtering = $('nt-search').value.trim() || state.active.size;
    $('nt-notes').innerHTML = n ? state.notes.map(cardHtml).join('')
      : `<div class="nt-empty">${filtering ? 'Không tìm thấy ghi chú phù hợp.' : 'Chưa có ghi chú nào. Hãy bấm "Ghi chú mới" để bắt đầu!'}</div>`;
  }

  $('nt-notes').addEventListener('click', e => {
    const pin = e.target.closest('[data-pin]');
    if (pin) { togglePin(+pin.dataset.pin); return; }
    const card = e.target.closest('[data-id]');
    if (card) openNote(+card.dataset.id);
  });
  $('nt-notes').addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.nt-card')) { e.preventDefault(); openNote(+e.target.dataset.id); }
  });

  async function togglePin(id) {
    const n = state.notes.find(x => x.id === id);
    if (!n) return;
    try { await api('notes/pin.php', { method: 'POST', body: { id, pinned: !n.is_pinned } }); loadNotes(); }
    catch (err) { toast(err.message); }
  }

  // Tìm kiếm trực tiếp, trễ 300ms, không có nút "Tìm"
  let searchTimer;
  $('nt-search').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadNotes, 300); });
  $('nt-sort').addEventListener('change', loadNotes);

  // Chế độ xem lưới/danh sách (được lưu vào tùy chọn của người dùng)
  function setView(v, persist) {
    state.view = v;
    $('nt-notes').className = v === 'list' ? 'nt-list' : 'nt-grid';
    for (const k of ['grid', 'list']) {
      const b = $('nt-view-' + k);
      b.classList.toggle('active', k === v);
      b.setAttribute('aria-pressed', k === v);
    }
    if (persist) api('preferences/view-mode.php', { method: 'POST', body: { view_mode: v } }).catch(() => {});
  }
  $('nt-view-grid').addEventListener('click', () => setView('grid', true));
  $('nt-view-list').addEventListener('click', () => setView('list', true));

  // ---------- Trình soạn thảo + tự động lưu ----------
  // Mỗi lần mở là một "phiên" (ed). Các lần lưu xếp hàng trong `chain` nên không bao giờ chạy chồng nhau:
  // lần đầu tạo ghi chú xong (có id) thì các lần sau mới cập nhật => không sinh ghi chú trùng.
  let ed = null;
  let chain = Promise.resolve();
  const setEd = t => { $('nt-editor-status').textContent = t || 'Tự động lưu'; };

  function openEditor(note) {
    ed = { id: note ? note.id : null, labelIds: new Set(note ? note.label_ids : []), atts: note ? [...note.attachments] : [], timer: null, dead: false };
    $('nt-title').value = note ? note.title : '';
    $('nt-content').value = note ? note.content : '';
    $('nt-delete').style.display = note ? '' : 'none';
    setEd('');
    renderEditorLabels();
    renderAtts();
    $('nt-editor').showModal();
    (note ? $('nt-content') : $('nt-title')).focus();
    document.dispatchEvent(new CustomEvent('nt:editor-open', { detail: { note } }));   // TV3 (advanced.js) cập nhật nút khóa/chia sẻ
  }

  async function openNote(id) {
    try { openEditor((await api('notes/get.php?id=' + id)).note); }
    catch (err) {
      if (err.status === 423) {   // ghi chú khóa: TV3 gắn hàm này để hiện hộp thoại nhập mật khẩu
        if (typeof window.onLockedNoteOpen === 'function') window.onLockedNoteOpen(id);
        else toast('Ghi chú này đang được khóa bằng mật khẩu.', 'warning');
      } else toast(err.message);
    }
  }

  function renderEditorLabels() {
    $('nt-editor-labels').innerHTML = '<span class="small text-secondary">Nhãn:</span>' + (state.labels.length
      ? state.labels.map(l => `<input type="checkbox" class="btn-check" id="nt-el-${l.id}" value="${l.id}" ${ed.labelIds.has(l.id) ? 'checked' : ''} autocomplete="off"><label class="btn btn-sm btn-outline-secondary rounded-pill" for="nt-el-${l.id}">${esc(l.name)}</label>`).join('')
      : '<span class="small text-secondary">Chưa có nhãn. Dùng "Quản lý nhãn" ở trang chính để tạo.</span>');
  }
  $('nt-editor-labels').addEventListener('change', e => {
    if (!ed || e.target.type !== 'checkbox') return;
    const id = +e.target.value;
    e.target.checked ? ed.labelIds.add(id) : ed.labelIds.delete(id);
    scheduleSave();
  });

  async function doSave({ s, force, title, content, labels }) {
    if (s.id === null) {
      if (!force && !title && !content) { if (ed === s) setEd(''); return; }   // chưa nhập gì thì chưa tạo
      const r = await api('notes/create.php', { method: 'POST', body: { title, content, label_ids: labels } });
      s.id = r.note.id;
      if (ed === s) $('nt-delete').style.display = '';
    } else {
      await api('notes/update.php', { method: 'POST', body: { id: s.id, title, content, label_ids: labels } });
    }
    if (ed === s) setEd('Đã lưu lúc ' + new Date().toLocaleTimeString('vi-VN'));
  }

  // Chụp nội dung ngay lúc gọi, rồi xếp vào hàng đợi
  function flush(force = false) {
    const s = ed;
    if (!s || s.dead) return chain;
    clearTimeout(s.timer);
    const snap = { s, force, title: $('nt-title').value.trim(), content: $('nt-content').value, labels: [...s.labelIds] };
    chain = chain.then(() => doSave(snap)).catch(err => { if (ed === s) setEd('Lỗi lưu'); toast(err.message); });
    return chain;
  }
  function scheduleSave() {
    const s = ed;
    if (!s) return;
    setEd('Đang lưu...');
    clearTimeout(s.timer);
    s.timer = setTimeout(() => { if (ed === s) flush(); }, 500);
  }
  $('nt-title').addEventListener('input', scheduleSave);
  $('nt-content').addEventListener('input', scheduleSave);

  // Đóng bằng bất kỳ cách nào (Xong, ✕, Esc, bấm ra ngoài) đều lưu nốt phần chưa lưu
  $('nt-editor').addEventListener('close', async () => {
    const p = flush();
    ed = null;
    await p;
    loadNotes();
  });
  $('nt-done').addEventListener('click', () => $('nt-editor').close());
  $('nt-editor-x').addEventListener('click', () => $('nt-editor').close());
  closeOnBackdrop($('nt-editor'));
  $('nt-new').addEventListener('click', () => openEditor(null));

  $('nt-delete').addEventListener('click', async () => {
    const s = ed;
    if (!s) return;
    if (!await confirmBox('Xóa ghi chú này? Hành động không thể hoàn tác.')) return;
    s.dead = true;
    clearTimeout(s.timer);
    try {
      await chain;   // đợi lần lưu đang chạy để chắc chắn đã có id
      if (s.id !== null) await api('notes/delete.php', { method: 'POST', body: { id: s.id } });
    } catch (err) { s.dead = false; toast(err.message); return; }
    $('nt-editor').close();
    toast('Đã xóa ghi chú.', 'success');
  });

  // ---------- Tệp đính kèm ----------
  function attHtml(a) {
    const url = 'api/attachments/download.php?id=' + a.id;

    const prev = a.mime_type.startsWith('image/')
        ? `<img class="nt-att-img" src="${url}" alt="${esc(a.file_name)}" loading="lazy">`
        : a.mime_type.startsWith('video/')
            ? `<video class="nt-att-video" src="${url}" controls preload="metadata"></video>`
            : `<a class="nt-att-file" href="${url}" download>${esc(a.file_name)}</a>`;

    return `<div class="nt-att">
        ${prev}
        <div class="d-flex justify-content-between align-items-center gap-2 small">
            <span class="text-truncate">${esc(a.file_name)} · ${fmtSize(a.file_size)}</span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-del-att="${a.id}">
                Xóa
            </button>
        </div>
    </div>`;
}
  function renderAtts() { $('nt-atts').innerHTML = ed.atts.map(attHtml).join(''); }

  $('nt-attach').addEventListener('click', () => $('nt-file').click());
  $('nt-file').addEventListener('change', async e => {
    const files = [...e.target.files];
    e.target.value = '';
    const s = ed;
    if (!files.length || !s) return;
    setEd('Đang tải tệp lên...');
    await flush(true);                       // ghi chú mới cần có id trước khi đính kèm
    if (s.id === null) { setEd('Lỗi lưu'); return; }
    const fd = new FormData();
    fd.append('note_id', s.id);
    files.forEach(f => fd.append('files[]', f));
    try {
      const r = await api('attachments/upload.php', { method: 'POST', form: fd });
      s.atts.push(...r.attachments);
      r.errors.forEach(m => toast(m, 'warning'));
    } catch (err) {
      if (err.data?.errors) err.data.errors.forEach(m => toast(m, 'warning')); else toast(err.message);
    }
    if (ed === s) { renderAtts(); setEd('Đã lưu lúc ' + new Date().toLocaleTimeString('vi-VN')); }
  });
  $('nt-atts').addEventListener('click', async e => {
    const b = e.target.closest('[data-del-att]');
    if (!b || !ed) return;
    const id = +b.dataset.delAtt;
    if (!await confirmBox('Xóa tệp đính kèm này?')) return;
    try {
      await api('attachments/delete.php', { method: 'POST', body: { id } });
      ed.atts = ed.atts.filter(a => a.id !== id);
      renderAtts();
    } catch (err) { toast(err.message); }
  });

  // ---------- Quản lý nhãn: danh sách, thêm, đổi tên, xóa ----------
  function renderLabelRows() {
    $('nt-label-rows').innerHTML = state.labels.length ? state.labels.map(l => `
      <li class="list-group-item d-flex align-items-center gap-2" data-lid="${l.id}">
        <input class="form-control form-control-sm nt-lname" value="${esc(l.name)}" maxlength="100" aria-label="Tên nhãn">
        <span class="badge text-bg-secondary" title="Số ghi chú">${l.note_count}</span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-act="rename">Lưu</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-act="delete">Xóa</button>
      </li>`).join('') : '<li class="list-group-item text-secondary">Chưa có nhãn nào.</li>';
  }
  async function addLabel() {
    const name = $('nt-label-new').value.trim();
    if (!name) return;
    try {
      await api('labels/save.php', { method: 'POST', body: { name } });
      $('nt-label-new').value = '';
      await loadLabels();
      renderLabelRows();
    } catch (err) { toast(err.message); }
  }
  $('nt-manage').addEventListener('click', async () => {
    try { await loadLabels(); } catch (err) { toast(err.message); return; }
    renderLabelRows();
    $('nt-labels').showModal();
  });
  $('nt-label-add').addEventListener('click', addLabel);
  $('nt-label-new').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addLabel(); } });
  $('nt-label-rows').addEventListener('click', async e => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const li = b.closest('[data-lid]');
    const id = +li.dataset.lid;
    try {
      if (b.dataset.act === 'rename') {
        await api('labels/save.php', { method: 'POST', body: { id, name: li.querySelector('.nt-lname').value } });
        toast('Đã đổi tên nhãn.', 'success');
      } else {
        const l = state.labels.find(x => x.id === id);
        if (!await confirmBox(`Xóa nhãn "${l ? l.name : ''}"? Các ghi chú đang dùng nhãn này vẫn được giữ lại.`)) return;
        await api('labels/delete.php', { method: 'POST', body: { id } });
      }
      await loadLabels();
      renderLabelRows();
    } catch (err) { toast(err.message); }
  });
  $('nt-labels-x').addEventListener('click', () => $('nt-labels').close());
  $('nt-labels').addEventListener('close', () => { renderChips(); loadNotes(); });   // ghi chú tự hiện tên nhãn mới
  closeOnBackdrop($('nt-labels'));

  // ---------- Giao diện cho TV3 (advanced.js, ai.js) ----------
  window.NoteApp = {
    currentId: () => (ed ? ed.id : null),
    // Lưu ngay (tạo ghi chú nếu mới) rồi trả về id: các chức năng khóa/chia sẻ/AI cần ghi chú đã có trong CSDL
    async ensureSaved() { await flush(true); return ed ? ed.id : null; },
    // Đóng trình soạn thảo sau khi lưu nốt, không lưu thêm lần nữa (dùng trước khi khóa lại ghi chú)
    async closeEditor() { await flush(); if (ed) ed.dead = true; $('nt-editor').close(); },
    open: openNote,
    reload: loadNotes,
    toast,
  };

  // ---------- Khởi động ----------
  setupContrast();
  setView(state.view, false);
  $('nt-notes').innerHTML = '<div class="nt-empty">Đang tải ghi chú...</div>';
  (async () => {
    try { await loadLabels(); } catch (err) { toast(err.message); }
    loadNotes();
  })();
})();

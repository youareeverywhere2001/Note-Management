<?php
// TV3: trang cộng tác thời gian thực (WebSocket). Dùng cho chủ sở hữu và người được chia sẻ.
// Quyền Read-only / Edit được máy chủ kiểm tra ở api/shared/*, api/collab/* và máy chủ WebSocket.
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
$u = require_login();
$id = (int)($_GET['id'] ?? 0);
page_header('Cộng tác ghi chú', true, ['assets/notes.css']);
?>
<div id="co-app" data-id="<?= $id ?>" style="max-width:860px">
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="shared.php">‹ Được chia sẻ</a>
    <a class="btn btn-outline-secondary btn-sm" href="index.php">Ghi chú của tôi</a>
    <a class="btn btn-outline-primary btn-sm" href="summary.php?id=<?= $id ?>">Tóm tắt AI</a>
    <a class="btn btn-outline-primary btn-sm" href="qa.php?id=<?= $id ?>">Hỏi AI</a>
  </div>
  <div id="co-error" class="alert alert-danger d-none" role="alert"></div>
  <div id="co-main" class="card d-none"><div class="card-body p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
      <div class="flex-grow-1">
        <input id="co-title" class="form-control form-control-lg fw-bold border-0 px-0" maxlength="255" placeholder="Tiêu đề" aria-label="Tiêu đề">
        <div id="co-meta" class="small text-secondary d-flex align-items-center gap-2"></div>
      </div>
      <div class="text-md-end">
        <span id="co-badge" class="badge"></span>
        <div id="co-status" class="small mt-1" aria-live="polite"></div>
        <div id="co-users" class="small text-secondary"></div>
      </div>
    </div>
    <div id="co-warn"></div>
    <textarea id="co-text" class="form-control co-text" aria-label="Nội dung ghi chú" placeholder="Nội dung ghi chú..."></textarea>
    <div id="co-atts" class="mt-3 small"></div>
  </div></div>
</div>
<?php page_footer(['assets/api.js', 'assets/advanced.js', 'assets/collaborate.js']);

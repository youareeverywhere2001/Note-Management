<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
$u = require_login();

$st = db()->prepare('SELECT id, title, (password_hash IS NOT NULL) AS is_locked FROM notes WHERE user_id = ? ORDER BY updated_at DESC, id DESC');
$st->execute([$u['id']]);
$notes = $st->fetchAll();
$pre = (int)($_GET['id'] ?? 0);

page_header('Chia sẻ ghi chú', true, ['assets/notes.css']);
?>

<div class="share-page">

  <div class="share-header">
    <div>
      <h1 class="share-title">Chia sẻ</h1>
      <p class="share-subtitle">Chia sẻ ghi chú và quản lý quyền truy cập.</p>
    </div>

    <a class="share-back" href="index.php">
      <svg viewBox="0 0 24 24" width="18" height="18"
           fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
        <path d="m15 18-6-6 6-6"/>
      </svg>
      Ghi chú của tôi
    </a>
  </div>

  <?php if (!$notes): ?>

    <div class="share-empty">
      <div class="share-empty-icon">
        <svg viewBox="0 0 24 24" width="30" height="30"
             fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"/>
          <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>
        </svg>
      </div>
      <h2>Bạn chưa có ghi chú nào</h2>
      <p>Tạo ghi chú trước khi chia sẻ với người khác.</p>
      <a href="index.php" class="share-primary-btn">Tạo ghi chú</a>
    </div>

  <?php else: ?>

    <div class="share-panel">
      <div class="share-panel-heading">
        <div>
          <h2>Chia sẻ ghi chú</h2>
          <p>Chọn ghi chú và nhập email người nhận.</p>
        </div>
      </div>

      <div class="share-field">
        <label for="sh-note">Ghi chú</label>
        <select class="form-select" id="sh-note">
          <?php foreach ($notes as $n): ?>
            <option value="<?= (int)$n['id'] ?>"
              <?= $pre === (int)$n['id'] ? 'selected' : '' ?>>
              <?= $n['is_locked'] ? '[Đã khóa] ' : '' ?><?= e($n['title'] !== '' ? $n['title'] : '(Không có tiêu đề)') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <form id="sh-form" class="share-form">
        <div class="share-field share-email-field">
          <label for="sh-email">Email người nhận</label>
          <input class="form-control" id="sh-email"
                 type="email" required
                 placeholder="example@email.com">
        </div>

        <div class="share-field share-perm-field">
          <label for="sh-perm">Quyền truy cập</label>
          <select class="form-select" id="sh-perm">
            <option value="read">Chỉ xem</option>
            <option value="edit">Được chỉnh sửa</option>
          </select>
        </div>

        <div class="share-submit-field">
          <button class="share-primary-btn" type="submit">
            Chia sẻ
          </button>
        </div>
      </form>

      <div id="sh-msg" class="share-message" aria-live="polite"></div>
    </div>

    <div class="share-panel share-members-panel">
      <div class="share-panel-heading">
        <div>
          <h2>Người đang được chia sẻ</h2>
          <p>Quản lý những người có quyền truy cập ghi chú này.</p>
        </div>
      </div>

      <div id="sh-list" class="list-group share-list"></div>

      <a id="sh-open" class="share-collab-btn" href="#">
        <svg viewBox="0 0 24 24" width="18" height="18"
             fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Mở trang cộng tác
      </a>
    </div>

  <?php endif; ?>

</div>

<?php page_footer(['assets/api.js', 'assets/share.js']); ?>

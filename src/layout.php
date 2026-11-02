<?php
// page_header($title) / page_footer(). Mọi trang (TV1, TV2, TV3) dùng chung hai hàm này.
function home_icon(int $size = 18): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" style="vertical-align:-3px"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/></svg>';
}

function page_header(string $title, bool $nav = true, array $css = []): void {
    $u = current_user();
    $prefs = $u ? get_preferences((int)$u['id']) : ['font_size' => 'medium', 'theme' => 'light', 'note_color' => '#ffffff'];
    $sizes = ['small' => '14px', 'medium' => '16px', 'large' => '19px'];
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $onHome = $cur === 'index.php';
    ?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= e($prefs['theme']) ?>" style="font-size:<?= $sizes[$prefs['font_size']] ?? '16px' ?>;--note-bg:<?= e($prefs['note_color']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> – NoteApp</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link href="assets/style.css" rel="stylesheet">
<?php foreach ($css as $c): ?><link href="<?= e($c) ?>" rel="stylesheet">
<?php endforeach; ?>
</head>
<body>
<?php if ($nav): ?>
<nav class="navbar navbar-expand-md border-bottom bg-body-tertiary">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php">NoteApp</a>
    <?php if ($u): ?>
    <a class="btn btn-outline-primary btn-sm ms-auto me-2 d-md-none<?= $onHome ? ' active' : '' ?>" href="index.php" title="Về trang Ghi chú của tôi" aria-label="Trang chủ"><?= home_icon() ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Mở menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
        <li class="nav-item"><a class="nav-link<?= $onHome ? ' active fw-semibold' : '' ?>" href="index.php" title="Về trang Ghi chú của tôi"<?= $onHome ? ' aria-current="page"' : '' ?>><?= home_icon() ?> Trang chủ</a></li>
        <li class="nav-item"><a class="nav-link<?= $cur === 'shared.php' ? ' active fw-semibold' : '' ?>" href="shared.php">Được chia sẻ</a></li>
        <li class="nav-item"><a class="nav-link<?= $cur === 'share.php' ? ' active fw-semibold' : '' ?>" href="share.php">Quản lý chia sẻ</a></li>
        <li class="nav-item"><a class="nav-link<?= $cur === 'summary.php' ? ' active fw-semibold' : '' ?>" href="summary.php">Tóm tắt AI</a></li>
        <li class="nav-item"><a class="nav-link<?= $cur === 'qa.php' ? ' active fw-semibold' : '' ?>" href="qa.php">Hỏi đáp AI</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Tài khoản</a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="profile.php">Hồ sơ</a></li>
            <li><a class="dropdown-item" href="preferences.php">Giao diện</a></li>
            <li><a class="dropdown-item" href="change-password.php">Đổi mật khẩu</a></li>
          </ul>
        </li>
        <li class="nav-item d-flex align-items-center gap-2"><?= avatar_html($u, 32) ?><span><?= e($u['display_name']) ?></span></li>
        <li class="nav-item">
          <form method="post" action="logout.php" class="m-0" data-api="api/auth/logout.php"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">Đăng xuất</button></form>
        </li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</nav>
<?php if ($u && !$u['is_verified']): ?>
<div class="alert alert-warning rounded-0 mb-0 text-center" role="alert">
  <strong>Tài khoản chưa được kích hoạt.</strong> Hãy kiểm tra email của bạn để hoàn tất kích hoạt.
  <form method="post" action="resend-activation.php" class="d-inline" data-api="api/auth/resend-activation.php"><?= csrf_field() ?><button class="btn btn-link p-0 align-baseline">Gửi lại email</button></form>
</div>
<?php endif; endif; ?>
<main class="container py-4">
<?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?>" role="alert"><?= e($m) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?>
<?php }

function page_footer(array $js = []): void {
    if (!in_array('assets/api.js', $js, true)) array_unshift($js, 'assets/api.js');
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php foreach ($js as $s): ?><script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php }

function show_errors(array $errors): void {
    if (!$errors) return;
    echo '<div class="alert alert-danger" role="alert"><ul class="mb-0">';
    foreach ($errors as $er) echo '<li>' . e($er) . '</li>';
    echo '</ul></div>';
}

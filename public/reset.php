<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
if (current_user()) redirect('index.php');

const MAX_OTP_TRIES = 5;
$errors = [];

// Token trong email được xác thực bởi API JavaScript.
if (isset($_GET['token'])) {
    $tokenForApi = $_GET['token'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'otp') {
        // Cách 2: nhập OTP
        $email = strtolower(trim($_POST['email'] ?? ''));
        $otp = trim($_POST['otp'] ?? '');
        $st = db()->prepare('SELECT r.* FROM password_resets r JOIN users u ON u.id = r.user_id
                             WHERE u.email = ? AND r.used = 0 AND r.expires_at > NOW() AND r.attempts < ?');
        $st->execute([$email, MAX_OTP_TRIES]);
        $r = $st->fetch();
        if ($r && password_verify($otp, $r['otp_hash'])) {
            $_SESSION['reset_id'] = (int)$r['id'];
            redirect('reset.php');
        }
        if ($r) db()->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')->execute([$r['id']]);
        $errors[] = 'Email hoặc mã OTP không đúng, hoặc mã đã hết hạn.';
    } elseif (($_POST['action'] ?? '') === 'newpw' && !empty($_SESSION['reset_id'])) {
        $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
        if ($err = validate_password($pw)) $errors[] = $err;
        if ($pw !== $pw2) $errors[] = 'Hai mật khẩu không khớp.';
        if (!$errors) {
            $st = db()->prepare('SELECT * FROM password_resets WHERE id = ? AND used = 0 AND expires_at > NOW()');
            $st->execute([$_SESSION['reset_id']]);
            if ($r = $st->fetch()) {
                db()->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1, failed_attempts = 0, locked_until = NULL WHERE id = ?')
                    ->execute([password_hash($pw, PASSWORD_BCRYPT), $r['user_id']]);
                db()->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$r['id']]);
                unset($_SESSION['reset_id']);
                flash('success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập.');
                redirect('login.php'); // không tự đăng nhập
            }
            unset($_SESSION['reset_id']);
            $errors[] = 'Phiên đặt lại mật khẩu đã hết hạn. Hãy yêu cầu lại.';
        }
    }
}
$step = !empty($_SESSION['reset_id']) ? 'newpw' : 'otp';
page_header('Đặt lại mật khẩu', false);
?>
<div class="card auth-card"><div class="card-body p-4">
  <h1 class="h4 mb-3">Đặt lại mật khẩu</h1>
  <?php show_errors($errors); ?>
  <?php if ($step === 'newpw'): ?>
  <form method="post" data-api="api/auth/reset-password.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="newpw">
    <div class="mb-3"><label class="form-label" for="pw">Mật khẩu mới</label>
      <input class="form-control" id="pw" name="password" type="password" required autocomplete="new-password"></div>
    <div class="mb-3"><label class="form-label" for="pw2">Nhập lại mật khẩu mới</label>
      <input class="form-control" id="pw2" name="password2" type="password" required autocomplete="new-password"></div>
    <button class="btn btn-primary w-100">Đặt lại mật khẩu</button>
  </form>
  <?php else: ?>
  <p class="text-secondary">Nhập email và mã OTP 6 số đã gửi đến email của bạn.</p>
  <form method="post" data-api="api/auth/reset-password.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="otp">
    <div class="mb-3"><label class="form-label" for="email">Email</label>
      <input class="form-control" id="email" name="email" type="email" required></div>
    <div class="mb-3"><label class="form-label" for="otp">Mã OTP</label>
      <input class="form-control" id="otp" name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}" required autocomplete="one-time-code"></div>
    <button class="btn btn-primary w-100">Xác nhận</button>
  </form>
  <?php endif; ?>
  <p class="mt-3 mb-0 text-center"><a href="login.php">Quay lại đăng nhập</a></p>
</div></div>
<?php if (!empty($tokenForApi)): ?>
<script>
fetch('api/auth/reset-password.php?token=<?= e(rawurlencode($tokenForApi)) ?>')
 .then(r => r.json()).then(data => {
   if (data.success) location.href = 'reset.php';
   else alert(data.message);
 }).catch(() => alert('Không thể kết nối máy chủ.'));
</script>
<?php endif; ?>
<?php page_footer();

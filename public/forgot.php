<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/mailer.php';

if (current_user()) redirect('index.php');

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);

        if ($u = $st->fetch()) {
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')
                ->execute([$u['id']]);

            $token = bin2hex(random_bytes(32));
            $otp = (string)random_int(100000, 999999);

            db()->prepare('INSERT INTO password_resets (user_id, token_hash, otp_hash, expires_at) VALUES (?,?,?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')
                ->execute([
                    $u['id'],
                    hash('sha256', $token),
                    password_hash($otp, PASSWORD_BCRYPT)
                ]);

            send_reset_email($u, $token, $otp);
        }

        $done = true;
    }
}

page_header('Quên mật khẩu', false, ['assets/notes.css']);
?>

<div class="login-page">
    <div class="login-card forgot-card">

        <div class="login-brand">
            <div class="login-brand-icon">
                <span></span>
            </div>
            <div class="login-brand-name">Note Management</div>
        </div>

        <div class="login-heading">
            <h1>Quên mật khẩu?</h1>
            <p>Nhập email đã đăng ký để nhận hướng dẫn đặt lại mật khẩu.</p>
        </div>

        <?php show_errors($errors); ?>

        <?php if ($done): ?>

            <div class="forgot-success">
                Nếu email tồn tại trong hệ thống, chúng tôi đã gửi liên kết và mã OTP đặt lại mật khẩu.
            </div>

            <a class="login-submit forgot-reset-link" href="reset.php">
                Nhập mã OTP
            </a>

        <?php else: ?>

            <form method="post" data-api="api/auth/forgot-password.php">
                <?= csrf_field() ?>

                <div class="login-field">
                    <input id="email" name="email" type="email"
                        placeholder=" " required autocomplete="email">
                    <label for="email">Email đã đăng ký</label>
                </div>

                <button type="submit" class="login-submit">
                    Gửi hướng dẫn
                </button>
            </form>

        <?php endif; ?>

        <div class="login-register">
            <a href="login.php">Quay lại đăng nhập</a>
        </div>

    </div>

    <div class="login-footer">
        Ghi chú của bạn, được sắp xếp gọn gàng.
    </div>
</div>

<?php page_footer(); ?>

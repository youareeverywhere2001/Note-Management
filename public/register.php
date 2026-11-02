
<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
require __DIR__ . '/../src/mailer.php';

if (current_user()) redirect('index.php');

$errors = [];
$email = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $name  = trim($_POST['display_name'] ?? '');
    $pw    = $_POST['password'] ?? '';
    $pw2   = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) $errors[] = 'Email không hợp lệ.';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Tên hiển thị phải từ 2 đến 100 ký tự.';
    if ($err = validate_password($pw)) $errors[] = $err;
    if ($pw !== $pw2) $errors[] = 'Hai mật khẩu không khớp.';

    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) $errors[] = 'Email này đã được đăng ký.';
    }

    if (!$errors) {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        db()->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?,?,?)')->execute([$email, $name, $hash]);

        $id = (int)db()->lastInsertId();

        db()->prepare('INSERT INTO user_preferences (user_id) VALUES (?)')->execute([$id]);

        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $user = $st->fetch();

        $sent = send_activation_email($user);
        login_user($user);

        flash($sent ? 'success' : 'warning', $sent
            ? 'Đăng ký thành công. Chúng tôi đã gửi email kích hoạt đến ' . $email . '.'
            : 'Đăng ký thành công nhưng chưa gửi được email kích hoạt. Hãy dùng nút "Gửi lại email".');

        redirect('index.php');
    }
}

page_header('Đăng ký', false, ['assets/notes.css']);
?>

<div class="login-page">
    <div class="login-card register-card">

        <div class="login-brand">
            <div class="login-brand-icon">
                <span></span>
            </div>
            <div class="login-brand-name">Note Management</div>
        </div>

        <div class="login-heading">
            <h1>Tạo tài khoản</h1>
            <p>Đăng ký để bắt đầu quản lý ghi chú của bạn.</p>
        </div>

        <?php show_errors($errors); ?>

        <form method="post" novalidate data-api="api/auth/register.php">
            <?= csrf_field() ?>

            <div class="login-field">
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="<?= e($email) ?>"
                    placeholder=" "
                    required
                    autocomplete="email"
                >
                <label for="email">Email</label>
            </div>

            <div class="login-field">
                <input
                    id="dn"
                    name="display_name"
                    type="text"
                    value="<?= e($name) ?>"
                    placeholder=" "
                    maxlength="100"
                    required
                >
                <label for="dn">Tên hiển thị</label>
            </div>

            <div class="login-field">
                <input
                    id="pw"
                    name="password"
                    type="password"
                    placeholder=" "
                    required
                    autocomplete="new-password"
                >
                <label for="pw">Mật khẩu</label>
                <div class="register-hint">Tối thiểu 8 ký tự, gồm chữ và số.</div>
            </div>

            <div class="login-field">
                <input
                    id="pw2"
                    name="password2"
                    type="password"
                    placeholder=" "
                    required
                    autocomplete="new-password"
                >
                <label for="pw2">Nhập lại mật khẩu</label>
            </div>

            <button type="submit" class="login-submit">
                Đăng ký
            </button>
        </form>

        <div class="login-register">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </div>

    </div>

    <div class="login-footer">
        Ghi chú của bạn, được sắp xếp gọn gàng.
    </div>
</div>

<?php page_footer(); ?>

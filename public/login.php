<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

if (current_user()) redirect('index.php');

const MAX_FAILS = 5;

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
        $errors[] = 'Tài khoản tạm khóa do nhập sai nhiều lần. Vui lòng thử lại sau vài phút.';
    } elseif ($u && password_verify($pw, (string)$u['password_hash'])) {
        db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
            ->execute([$u['id']]);

        login_user($u);
        redirect('index.php');
    } else {
        if ($u) {
            $f = (int)$u['failed_attempts'] + 1;

            if ($f >= MAX_FAILS) {
                db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?')
                    ->execute([$u['id']]);
            } else {
                db()->prepare('UPDATE users SET failed_attempts = ? WHERE id = ?')
                    ->execute([$f, $u['id']]);
            }
        } else {
            password_verify($pw, '$2y$10$abcdefghijklmnopqrstuuYhZ0Pz0y0y0y0y0y0y0y0y0y0y0y0y.');
        }

        $errors[] = 'Email hoặc mật khẩu không đúng.';
    }
}

page_header('Đăng nhập', false, ['assets/notes.css']);
?>

<div class="login-page">
    <div class="login-card">

        <div class="login-brand">
            <div class="login-brand-icon">
                <span></span>
            </div>
            <div class="login-brand-name">Note Management</div>
        </div>

        <div class="login-heading">
            <h1>Chào mừng trở lại</h1>
            <p>Đăng nhập để tiếp tục quản lý ghi chú của bạn.</p>
        </div>

        <?php show_errors($errors); ?>

        <form method="post" data-api="api/auth/login.php">
            <?= csrf_field() ?>
            <div class="login-field">
                <input id="email" name="email" type="email"
                    value="<?= e($email) ?>" placeholder=" "
                    required autocomplete="email">
                <label for="email">Email</label>
            </div>

            <div class="login-field">
                <input
                    id="pw"
                    name="password"
                    type="password"
                    placeholder=" "
                    required
                    autocomplete="current-password"
                >
                <label for="pw">Mật khẩu</label>
            </div>

            <div class="login-forgot">
                <a href="forgot.php">Quên mật khẩu?</a>
            </div>

            <button type="submit" class="login-submit">
                Đăng nhập
            </button>
        </form>

        <div class="login-register">
            Chưa có tài khoản?
            <a href="register.php">Đăng ký</a>
        </div>

    </div>

    <div class="login-footer">
        Ghi chú của bạn, được sắp xếp gọn gàng.
    </div>
</div>

<?php page_footer(); ?>
<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old = $_POST['old_password'] ?? '';
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (!password_verify($old, (string)$u['password_hash'])) {
        $errors[] = 'Mật khẩu hiện tại không đúng.';
    }

    if ($err = validate_password($pw)) {
        $errors[] = $err;
    }

    if ($pw !== $pw2) {
        $errors[] = 'Hai mật khẩu mới không khớp.';
    }

    if (!$errors && hash_equals($old, $pw)) {
        $errors[] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')
            ->execute([password_hash($pw, PASSWORD_BCRYPT), $u['id']]);

        // Các thiết bị khác bị đăng xuất; phiên hiện tại được cấp ID mới và giữ đăng nhập
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$u['id']]);
        login_user($st->fetch());

        flash('success', 'Đổi mật khẩu thành công. Các thiết bị khác đã bị đăng xuất.');
        redirect('change-password.php');
    }
}

page_header('Đổi mật khẩu', true, ['assets/notes.css']);
?>

<div class="password-page">

    <div class="password-header">
        <div class="password-eyebrow">BẢO MẬT TÀI KHOẢN</div>
        <h1 class="password-title">Đổi mật khẩu</h1>
        <p class="password-subtitle">
            Cập nhật mật khẩu để bảo vệ tài khoản của bạn.
        </p>
    </div>

    <?php show_errors($errors); ?>

    <div class="password-panel">

        <div class="password-panel-heading">
            <h2>Thông tin mật khẩu</h2>
            <p>Vui lòng nhập mật khẩu hiện tại và mật khẩu mới.</p>
        </div>

        <form method="post" data-api="api/auth/change-password.php">
            <?= csrf_field() ?>

            <div class="password-field">
                <label for="o">Mật khẩu hiện tại</label>
                <input
                    id="o"
                    name="old_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Nhập mật khẩu hiện tại">
            </div>

            <div class="password-divider"></div>

            <div class="password-field">
                <label for="p">Mật khẩu mới</label>
                <input
                    id="p"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nhập mật khẩu mới">
            </div>

            <div class="password-field">
                <label for="p2">Nhập lại mật khẩu mới</label>
                <input
                    id="p2"
                    name="password2"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nhập lại mật khẩu mới">
            </div>

            <div class="password-hint">
                Hãy sử dụng mật khẩu đủ mạnh và không dùng lại mật khẩu cũ.
            </div>

            <div class="password-actions">
                <button type="submit" class="password-save-btn">
                    Đổi mật khẩu
                </button>
            </div>
        </form>

    </div>
</div>

<?php page_footer(); ?>

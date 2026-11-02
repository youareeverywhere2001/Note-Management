
<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['display_name'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Tên hiển thị phải từ 2 đến 100 ký tự.';
    }

    $newAvatar = null;
    $f = $_FILES['avatar'] ?? null;

    if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Tải ảnh lên thất bại, hãy thử lại.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ảnh đại diện tối đa 2MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $map = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($map[$mime]) || @getimagesize($f['tmp_name']) === false) {
                $errors[] = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ.';
            } else {
                $newAvatar = bin2hex(random_bytes(16)) . '.' . $map[$mime];
            }
        }
    }

    if (!$errors) {
        if ($newAvatar) {
            $dir = __DIR__ . '/uploads/avatars/';

            if (!move_uploaded_file($f['tmp_name'], $dir . $newAvatar)) {
                $errors[] = 'Không lưu được ảnh đại diện.';
            } else {
                if ($u['avatar']) {
                    @unlink($dir . basename($u['avatar']));
                }

                db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')
                    ->execute([$newAvatar, $u['id']]);
            }
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET display_name = ? WHERE id = ?')
                ->execute([$name, $u['id']]);

            flash('success', 'Đã cập nhật hồ sơ.');
            redirect('profile.php');
        }
    }
}

page_header('Hồ sơ', true, ['assets/notes.css']);
?>

<div class="profile-page">

    <div class="profile-header">
        <div class="profile-eyebrow">NOTE MANAGEMENT</div>
        <h1 class="profile-title">Hồ sơ cá nhân</h1>
        <p class="profile-subtitle">
            Quản lý thông tin và ảnh đại diện của tài khoản.
        </p>
    </div>

    <a href="index.php" class="profile-back-link">
        <?= home_icon(16) ?>
        <span>Về trang chủ</span>
    </a>

    <div class="profile-panel">

        <div class="profile-panel-heading">
            <h2>Thông tin tài khoản</h2>
            <p>Thông tin cá nhân đang được sử dụng.</p>
        </div>

        <?php show_errors($errors); ?>

        <div class="profile-user">
            <div class="profile-avatar">
                <?= avatar_html($u, 80) ?>
            </div>

            <div class="profile-user-info">
                <div class="profile-user-name">
                    <?= e($u['display_name']) ?>
                </div>
                <div class="profile-user-email">
                    <?= e($u['email']) ?>
                </div>
            </div>
        </div>

        <div class="profile-divider"></div>

        <form method="post" enctype="multipart/form-data"
              data-api="api/profile/update.php">

            <?= csrf_field() ?>

            <div class="profile-field">
                <label for="dn">Tên hiển thị</label>
                <input
                    class="form-control"
                    id="dn"
                    name="display_name"
                    value="<?= e($u['display_name']) ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="profile-field">
                <label for="av">Ảnh đại diện</label>
                <input
                    class="form-control"
                    id="av"
                    name="avatar"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                >
                <p class="profile-hint">
                    Hỗ trợ JPG, PNG hoặc WEBP. Dung lượng tối đa 2MB.
                </p>
            </div>

            <button type="submit" class="profile-save-btn">
                Lưu thay đổi
            </button>

        </form>

    </div>
</div>

<?php page_footer(); ?>

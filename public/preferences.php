<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fs = $_POST['font_size'] ?? '';
    $th = $_POST['theme'] ?? '';
    $col = $_POST['note_color'] ?? '';

    if (!in_array($fs, ['small', 'medium', 'large'], true)) {
        $errors[] = 'Cỡ chữ không hợp lệ.';
    }

    if (!in_array($th, ['light', 'dark'], true)) {
        $errors[] = 'Giao diện không hợp lệ.';
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $col)) {
        $errors[] = 'Màu ghi chú không hợp lệ.';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO user_preferences (user_id, font_size, note_color, theme) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE font_size = VALUES(font_size), note_color = VALUES(note_color), theme = VALUES(theme)')
            ->execute([$u['id'], $fs, strtolower($col), $th]);

        flash('success', 'Đã lưu tùy chọn.');
        redirect('preferences.php');
    }
}

$p = get_preferences((int)$u['id']);

page_header('Tùy chọn giao diện', true, ['assets/notes.css']);
?>

<div class="preferences-page">

    <div class="preferences-header">
        <div>
            <div class="preferences-eyebrow">CÀI ĐẶT</div>
            <h1 class="preferences-title">Tùy chọn giao diện</h1>
            <p class="preferences-subtitle">
                Cá nhân hóa cách hiển thị ghi chú của bạn.
            </p>
        </div>
    </div>

    <?php show_errors($errors); ?>

    <div class="preferences-panel">

        <div class="preferences-section-heading">
            <h2>Hiển thị</h2>
            <p>Điều chỉnh giao diện theo sở thích của bạn.</p>
        </div>

        <form method="post" data-api="api/preferences/update.php">
            <?= csrf_field() ?>

            <div class="preferences-field">
                <label for="fs">Cỡ chữ</label>

                <select id="fs" name="font_size">
                    <?php foreach (['small' => 'Nhỏ', 'medium' => 'Vừa', 'large' => 'Lớn'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $p['font_size'] === $k ? 'selected' : '' ?>>
                            <?= $v ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="preferences-hint">
                    Chọn kích thước chữ phù hợp khi đọc ghi chú.
                </div>
            </div>

            <div class="preferences-divider"></div>

            <div class="preferences-field">
                <label for="th">Chế độ giao diện</label>

                <select id="th" name="theme">
                    <option value="light" <?= $p['theme'] === 'light' ? 'selected' : '' ?>>
                        Sáng
                    </option>
                    <option value="dark" <?= $p['theme'] === 'dark' ? 'selected' : '' ?>>
                        Tối
                    </option>
                </select>

                <div class="preferences-hint">
                    Chọn giao diện sáng hoặc tối cho ứng dụng.
                </div>
            </div>

            <div class="preferences-divider"></div>

            <div class="preferences-field">
                <label for="nc">Màu ghi chú</label>

                <div class="preferences-color-row">
                    <input
                        id="nc"
                        name="note_color"
                        type="color"
                        value="<?= e($p['note_color']) ?>">

                    <span id="color-value"><?= e($p['note_color']) ?></span>
                </div>

                <div class="preferences-hint">
                    Chọn màu nền mặc định cho ghi chú.
                </div>
            </div>

            <div class="preferences-preview-wrap">
                <div class="preferences-preview-label">XEM TRƯỚC</div>

                <div class="note-preview" id="prev">
                    <div class="preferences-preview-title">Ghi chú của tôi</div>
                    <div class="preferences-preview-text">
                        Đây là phần xem trước màu nền ghi chú của bạn.
                    </div>
                </div>
            </div>

            <div class="preferences-actions">
                <button type="submit" class="preferences-save-btn">
                    Lưu tùy chọn
                </button>
            </div>

        </form>
    </div>
</div>

<script>
const colorInput = document.getElementById('nc');
const preview = document.getElementById('prev');
const colorValue = document.getElementById('color-value');

colorInput.addEventListener('input', function () {
    preview.style.background = this.value;
    colorValue.textContent = this.value;
});
</script>

<?php page_footer(); ?>
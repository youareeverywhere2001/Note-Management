<?php
// TV3: AI Tóm tắt ghi chú.
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
$u = require_login();

page_header('AI Tóm tắt', true, ['assets/notes.css']);
?>

<div class="ai-page" id="ai-page" data-mode="summary">

    <div class="ai-header">
        <div>
            <h1 class="ai-title">AI Tóm tắt</h1>
            <p class="ai-subtitle">
                Tóm tắt ghi chú và nắm bắt những ý chính nhanh chóng.
            </p>
        </div>
    </div>

    <div class="ai-panel">

        <div class="ai-panel-heading">
            <h2>Chọn ghi chú</h2>
            <p>Chọn nội dung bạn muốn AI phân tích.</p>
        </div>

        <div class="ai-field">
            <label for="ai-note">Ghi chú</label>
            <select class="form-select" id="ai-note">
                <option value="">Đang tải ghi chú...</option>
            </select>
        </div>

        <button id="ai-go" type="button" class="ai-primary-btn" disabled>
            Tóm tắt ghi chú
        </button>

    </div>

    <div id="ai-out" class="ai-output" aria-live="polite"></div>

</div>

<?php page_footer(['assets/api.js', 'assets/advanced.js', 'assets/ai.js']); ?>

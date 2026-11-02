<?php
// TV3: AI Hỏi đáp về nội dung ghi chú.
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();

page_header('AI Hỏi đáp', true, ['assets/notes.css']);
?>

<div class="ai-page" id="ai-page" data-mode="qa">

    <div class="ai-header">
        <div>
            <h1 class="ai-title">AI Hỏi đáp</h1>
            <p class="ai-subtitle">
                Đặt câu hỏi và tìm kiếm thông tin trong ghi chú của bạn.
            </p>
        </div>
    </div>

    <div class="ai-panel">

        <div class="ai-panel-heading">
            <h2>Đặt câu hỏi về ghi chú</h2>
            <p>AI sẽ trả lời dựa trên nội dung ghi chú và cung cấp đoạn trích liên quan.</p>
        </div>

        <div class="ai-field">
            <label for="ai-note">Chọn ghi chú</label>
            <select class="form-select" id="ai-note">
                <option value="">Đang tải ghi chú...</option>
            </select>
        </div>

        <div class="ai-field ai-question-field">
            <label for="ai-question">Câu hỏi của bạn</label>
            <textarea
                class="form-control"
                id="ai-question"
                rows="4"
                maxlength="1000"
                placeholder="Bạn muốn hỏi điều gì về ghi chú này?"
            ></textarea>
            <div class="ai-input-hint">Tối đa 1000 ký tự</div>
        </div>

        <button
            id="ai-go"
            type="button"
            class="ai-primary-btn"
            disabled
        >
            Gửi câu hỏi
        </button>

    </div>

    <div id="ai-out" class="ai-output" aria-live="polite"></div>

</div>

<?php page_footer(['assets/api.js', 'assets/advanced.js', 'assets/ai.js']); ?>
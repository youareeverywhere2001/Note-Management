<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();
$prefs = get_preferences((int)$u['id']);
$view = ($prefs['view_mode'] ?? 'grid') === 'list' ? 'list' : 'grid';

page_header('Ghi chú', true, ['assets/notes.css']);
?>

<div id="nt-app" data-view="<?= e($view) ?>" class="nt-app">

    <!-- Tiêu đề -->
    <header class="nt-heading">
        <div>
            <h1>Ghi chú</h1>
            <p class="nt-subtitle">Ghi lại những điều quan trọng của bạn.</p>
        </div>

        <button id="nt-new" type="button" class="btn btn-primary">
            + Ghi chú
        </button>
    </header>

    <!-- Tìm kiếm -->
    <section class="nt-toolbar">

        <div class="nt-search">
            <input
                id="nt-search"
                type="search"
                class="form-control"
                placeholder="Tìm kiếm"
                aria-label="Tìm kiếm ghi chú"
                autocomplete="off"
            >
        </div>

        <div class="nt-controls">

            <select id="nt-sort" class="form-select" aria-label="Sắp xếp ghi chú">
                <option value="updated">Sửa gần nhất</option>
                <option value="created">Mới tạo nhất</option>
            </select>

            <div class="btn-group nt-view-switch" role="group" aria-label="Chế độ xem">
                <button
                    id="nt-view-grid"
                    type="button"
                    class="btn btn-outline-secondary"
                >
                    Lưới
                </button>

                <button
                    id="nt-view-list"
                    type="button"
                    class="btn btn-outline-secondary"
                >
                    Danh sách
                </button>
            </div>

        </div>
    </section>

    <!-- Nhãn -->
    <section class="nt-label-section">

        <div class="nt-label-heading">
            <span>Nhãn</span>

            <button
                id="nt-manage"
                type="button"
                class="btn btn-sm btn-outline-secondary"
            >
                Quản lý
            </button>
        </div>

        <div id="nt-chips" class="d-flex flex-wrap gap-2"></div>

    </section>

    <!-- Danh sách ghi chú -->
    <section class="nt-notes-section">

        <div class="nt-list-info">
            <span id="nt-count"></span>
            <span id="nt-status" aria-live="polite"></span>
        </div>

        <div id="nt-notes" class="nt-grid"></div>

    </section>

</div>


<!-- ========================================
     NOTE EDITOR
======================================== -->

<dialog id="nt-editor" class="nt-dialog" aria-label="Soạn ghi chú">

    <div class="nt-editor-header">

        <span>Ghi chú</span>

        <button
            id="nt-editor-x"
            type="button"
            class="btn-close"
            aria-label="Đóng"
        ></button>

    </div>

    <div class="nt-editor-main">

        <input
            id="nt-title"
            class="form-control form-control-lg border-0 px-0 fw-bold"
            maxlength="255"
            placeholder="Tiêu đề"
            aria-label="Tiêu đề"
        >

        <textarea
            id="nt-content"
            class="form-control border-0 px-0"
            rows="12"
            placeholder="Bắt đầu viết..."
            aria-label="Nội dung"
        ></textarea>

    </div>

    <!-- Nhãn -->
    <div
        id="nt-editor-labels"
        class="d-flex flex-wrap align-items-center gap-2 py-2 border-top"
    ></div>

    <!-- Tệp đính kèm -->
    <div id="nt-atts" class="d-grid gap-2 mb-2"></div>

    <!-- Chức năng nâng cao -->
    <div id="nt-adv" class="nt-advanced">

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="lock">
            Khóa ghi chú
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="change" hidden>
            Đổi mật khẩu khóa
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="remove" hidden>
            Gỡ khóa
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="relock" hidden>
            Khóa lại
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="share">
            Chia sẻ
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="collab">
            Cộng tác
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="summary">
            Tóm tắt AI
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-adv="qa">
            Hỏi đáp AI
        </button>

    </div>

    <!-- Thanh công cụ -->
    <div class="nt-editor-footer">

        <button
            id="nt-delete"
            type="button"
            class="btn btn-outline-danger btn-sm"
        >
            Xóa
        </button>

        <button
            id="nt-attach"
            type="button"
            class="btn btn-outline-secondary btn-sm"
        >
            Đính kèm
        </button>

        <input
            id="nt-file"
            type="file"
            multiple
            hidden
            accept="image/*,video/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
        >

        <span
            id="nt-editor-status"
            class="ms-auto small text-secondary"
            aria-live="polite"
        >
            Tự động lưu
        </span>

        <button id="nt-done" type="button" class="btn btn-primary btn-sm">
            Xong
        </button>

    </div>

</dialog>


<!-- ========================================
     LABEL MANAGER
======================================== -->

<dialog id="nt-labels" class="nt-dialog nt-narrow" aria-label="Quản lý nhãn">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2 class="h5 mb-0">Quản lý nhãn</h2>

        <button
            id="nt-labels-x"
            type="button"
            class="btn-close"
            aria-label="Đóng"
        ></button>

    </div>

    <div class="input-group mb-3">

        <input
            id="nt-label-new"
            class="form-control"
            maxlength="100"
            placeholder="Tên nhãn mới"
            aria-label="Tên nhãn mới"
        >

        <button id="nt-label-add" type="button" class="btn btn-primary">
            Thêm
        </button>

    </div>

    <ul id="nt-label-rows" class="list-group"></ul>

</dialog>


<!-- ========================================
     CONFIRM DIALOG
======================================== -->

<dialog id="nt-confirm" class="nt-dialog nt-narrow" aria-label="Xác nhận">

    <form method="dialog">

        <p id="nt-confirm-msg" class="mb-3"></p>

        <div class="d-flex justify-content-end gap-2">

            <button value="cancel" class="btn btn-outline-secondary">
                Hủy
            </button>

            <button id="nt-confirm-ok" value="ok" class="btn btn-danger">
                Xóa
            </button>

        </div>

    </form>

</dialog>


<!-- Thông báo -->
<div id="nt-toasts" class="nt-toasts" role="status"></div>


<?php
page_footer([
    'assets/api.js',
    'assets/notes.js',
    'assets/advanced.js',
    'assets/ai.js'
]);

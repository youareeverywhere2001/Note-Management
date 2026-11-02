<?php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_response(bool $success, string $message = '', array $data = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success'=>$success, 'message'=>$message, 'data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : $_POST;
}
function api_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        json_response(false, 'Phiên làm việc hết hạn hoặc không hợp lệ. Hãy tải lại trang.', [], 403);
    }
}
function api_login(): array {
    $u = current_user();
    if (!$u) json_response(false, 'Vui lòng đăng nhập.', [], 401);
    return $u;
}
function api_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) json_response(false, 'Phương thức không được hỗ trợ.', [], 405);
}

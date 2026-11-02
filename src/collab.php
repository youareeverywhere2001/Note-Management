<?php
// Token ngắn hạn cho WebSocket: PHP (đã biết người dùng + quyền) ký bằng WS_SECRET,
// máy chủ Node chỉ cần kiểm tra chữ ký, không cần đọc session PHP.
require_once __DIR__ . '/config.php';

function ws_secret(): string { return (string)env('WS_SECRET', ''); }

function ws_make_token(array $payload): string {
    $body = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    return $body . '.' . hash_hmac('sha256', $body, ws_secret());
}

// Địa chỉ WebSocket mà TRÌNH DUYỆT dùng để kết nối
function ws_public_url(): string {
    $u = env('WS_PUBLIC_URL');
    if ($u) return $u;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($secure ? 'wss://' : 'ws://') . $host . ':' . (int)env('WS_PORT', 8081);
}

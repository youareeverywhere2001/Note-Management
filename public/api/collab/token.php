<?php
// GET ?note_id= : cấp token WebSocket ngắn hạn (120 giây) cho người dùng đang đăng nhập có quyền với ghi chú.
require __DIR__ . '/../../../src/api.php';
require __DIR__ . '/../../../src/collab.php';
$u = api_user();
$n = load_note_access((int)($_GET['note_id'] ?? 0), (int)$u['id'], 'read');
require_note_open($n);
session_write_close();
if (ws_secret() === '') json_error('Cộng tác thời gian thực chưa được cấu hình (thiếu WS_SECRET).', 503);
$perm = $n['role'] === 'owner' ? 'edit' : $n['role'];
json_out([
    'token'      => ws_make_token(['uid' => (int)$u['id'], 'name' => $u['display_name'], 'nid' => (int)$n['id'], 'perm' => $perm, 'exp' => time() + 120]),
    'ws_url'     => ws_public_url(),
    'permission' => $perm,
]);

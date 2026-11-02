<?php
// POST JSON {id} : khóa lại ghi chú trong phiên hiện tại (không đổi mật khẩu).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$n = load_note_access((int)($in['id'] ?? 0), (int)$u['id'], 'read');
unset($_SESSION['unlocked_notes'][(int)$n['id']]);
json_out(['ok' => true]);

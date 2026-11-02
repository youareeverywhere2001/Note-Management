<?php
// POST JSON {id, password} : mở khóa ghi chú (chủ sở hữu hoặc người được chia sẻ) cho phiên hiện tại.
// Sau khi đúng mật khẩu, phiên được đánh dấu đã mở khóa => mọi API của TV2/TV3 cho phép truy cập.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$n = load_note_access((int)($in['id'] ?? 0), (int)$u['id'], 'read');
if (empty($n['password_hash'])) json_out(['ok' => true, 'unlocked' => true]);   // ghi chú không bị khóa
verify_note_password($n, (string)($in['password'] ?? ''));
mark_note_unlocked((int)$n['id'], $n['password_hash']);
json_out(['ok' => true, 'unlocked' => true]);

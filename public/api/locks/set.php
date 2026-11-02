<?php
// POST JSON {id, password} : đặt mật khẩu khóa cho ghi chú của chính mình (TV3, trước đây set_password.php).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note_access((int)($in['id'] ?? 0), $uid, 'owner');
if (!empty($n['password_hash'])) json_error('Ghi chú đã có mật khẩu. Hãy dùng "Đổi mật khẩu".', 409);
$pw = (string)($in['password'] ?? '');
validate_note_password($pw);
$hash = password_hash($pw, PASSWORD_DEFAULT);
db()->prepare('UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?')->execute([$hash, $n['id'], $uid]);
mark_note_unlocked((int)$n['id'], $hash);   // chủ sở hữu vừa đặt khóa nên phiên này vẫn đang mở
json_out(['ok' => true, 'is_locked' => true]);

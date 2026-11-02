<?php
// POST JSON {id, password} : gỡ khóa (tắt mật khẩu) — phải nhập đúng mật khẩu hiện tại (chỉ chủ sở hữu).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note_access((int)($in['id'] ?? 0), $uid, 'owner');
if (empty($n['password_hash'])) json_error('Ghi chú chưa được khóa.', 409);
verify_note_password($n, (string)($in['password'] ?? ''));
db()->prepare('UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?')->execute([$n['id'], $uid]);
unset($_SESSION['unlocked_notes'][(int)$n['id']]);
json_out(['ok' => true, 'is_locked' => false]);

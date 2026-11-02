<?php
// POST JSON {id, old_password, new_password} : đổi mật khẩu khóa (chỉ chủ sở hữu).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note_access((int)($in['id'] ?? 0), $uid, 'owner');
if (empty($n['password_hash'])) json_error('Ghi chú chưa được khóa.', 409);
verify_note_password($n, (string)($in['old_password'] ?? ''));
$new = (string)($in['new_password'] ?? '');
validate_note_password($new);
if (hash_equals((string)$in['old_password'], $new)) json_error('Mật khẩu mới phải khác mật khẩu hiện tại.', 422);
$hash = password_hash($new, PASSWORD_DEFAULT);
db()->prepare('UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?')->execute([$hash, $n['id'], $uid]);
mark_note_unlocked((int)$n['id'], $hash);   // phiên của chủ sở hữu giữ mở; các phiên khác (kể cả người được chia sẻ) bị khóa lại
json_out(['ok' => true]);

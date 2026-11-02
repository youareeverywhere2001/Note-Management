<?php
// POST JSON {note_id, email, permission:'read'|'edit'} : chia sẻ ghi chú cho người dùng đã đăng ký (chỉ chủ sở hữu).
// Nếu đã chia sẻ cho người này rồi thì cập nhật quyền.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note_access((int)($in['note_id'] ?? 0), $uid, 'owner');

$email = strtolower(trim((string)($in['email'] ?? '')));
$perm = (string)($in['permission'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Email không hợp lệ.', 422);
if (!in_array($perm, ['read', 'edit'], true)) json_error('Quyền truy cập không hợp lệ.', 422);

$st = db()->prepare('SELECT id, email, display_name FROM users WHERE email = ?');
$st->execute([$email]);
$target = $st->fetch();
if (!$target) json_error('Không tìm thấy người dùng đã đăng ký với email này.', 404);
if ((int)$target['id'] === $uid) json_error('Không thể chia sẻ ghi chú cho chính mình.', 422);

$pdo = db();
$st = $pdo->prepare('SELECT id FROM note_shares WHERE note_id = ? AND shared_with_user_id = ?');
$st->execute([$n['id'], $target['id']]);
$existing = $st->fetchColumn();
if ($existing) {
    $pdo->prepare('UPDATE note_shares SET permission = ?, owner_id = ? WHERE id = ?')->execute([$perm, $uid, $existing]);
    $shareId = (int)$existing;
} else {
    $pdo->prepare('INSERT INTO note_shares (note_id, owner_id, shared_with_user_id, permission) VALUES (?,?,?,?)')
        ->execute([$n['id'], $uid, $target['id'], $perm]);
    $shareId = (int)$pdo->lastInsertId();
}
json_out(['ok' => true, 'updated' => (bool)$existing,
          'share' => ['id' => $shareId, 'email' => $target['email'], 'display_name' => $target['display_name'], 'permission' => $perm]], $existing ? 200 : 201);

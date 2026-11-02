<?php
// GET : danh sách ghi chú người dùng được phép dùng AI (ghi chú của mình + ghi chú được chia sẻ).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$uid = (int)$u['id'];
session_write_close();
$st = db()->prepare('SELECT n.id, n.title, (n.password_hash IS NOT NULL) AS is_locked, (n.user_id = ?) AS is_owner, o.display_name AS owner_name
                     FROM notes n JOIN users o ON o.id = n.user_id
                     WHERE n.user_id = ? OR EXISTS (SELECT 1 FROM note_shares s WHERE s.note_id = n.id AND s.shared_with_user_id = ?)
                     ORDER BY n.updated_at DESC, n.id DESC LIMIT 500');
$st->execute([$uid, $uid, $uid]);
json_out(['notes' => array_map(fn($r) => [
    'id' => (int)$r['id'], 'title' => $r['title'], 'is_locked' => (bool)$r['is_locked'],
    'is_owner' => (bool)$r['is_owner'], 'owner_name' => $r['owner_name'],
], $st->fetchAll())]);

<?php
// GET ?note_id= : danh sách người được chia sẻ ghi chú này (chỉ chủ sở hữu).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$n = load_note_access((int)($_GET['note_id'] ?? 0), (int)$u['id'], 'owner');
session_write_close();
$st = db()->prepare('SELECT s.id, s.permission, s.created_at, us.email, us.display_name
                     FROM note_shares s JOIN users us ON us.id = s.shared_with_user_id
                     WHERE s.note_id = ? ORDER BY s.created_at, s.id');
$st->execute([$n['id']]);
json_out(['shares' => array_map(fn($r) => [
    'id' => (int)$r['id'], 'email' => $r['email'], 'display_name' => $r['display_name'],
    'permission' => $r['permission'], 'created_at' => $r['created_at'],
], $st->fetchAll())]);

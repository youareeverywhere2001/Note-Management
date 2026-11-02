<?php
// GET: danh sách nhãn của người dùng kèm số ghi chú đang dùng nhãn
require __DIR__ . '/../../../src/api.php';
$u = api_user();
session_write_close();
$st = db()->prepare('SELECT l.id, l.name, (SELECT COUNT(*) FROM note_labels nl WHERE nl.label_id = l.id) AS note_count
                     FROM labels l WHERE l.user_id = ? ORDER BY l.name');
$st->execute([$u['id']]);
json_out(['labels' => array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'note_count' => (int)$r['note_count']], $st->fetchAll())]);

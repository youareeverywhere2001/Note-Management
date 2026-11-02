<?php
// POST JSON {id} : xóa ghi chú (giao diện phải hỏi xác nhận trước khi gọi)
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note((int)($in['id'] ?? 0), $uid);
require_note_open($n);

$st = db()->prepare('SELECT file_path FROM attachments WHERE note_id = ?');
$st->execute([$n['id']]);
$files = $st->fetchAll(PDO::FETCH_COLUMN);

db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?')->execute([$n['id'], $uid]); // cascade: note_labels, attachments
foreach ($files as $f) @unlink(attach_dir() . '/' . basename($f));
json_out(['ok' => true]);

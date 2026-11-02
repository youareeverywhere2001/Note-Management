<?php
// POST JSON {id} : xóa nhãn. Chỉ xóa liên kết (note_labels), KHÔNG xóa ghi chú.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$st = db()->prepare('DELETE FROM labels WHERE id = ? AND user_id = ?');
$st->execute([(int)($in['id'] ?? 0), $u['id']]);
if ($st->rowCount() === 0) json_error('Không tìm thấy nhãn.', 404);
json_out(['ok' => true]);

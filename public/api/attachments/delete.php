<?php
// POST JSON {id} : xóa một tệp đính kèm
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$st = db()->prepare('SELECT a.id, a.file_path, n.id AS note_id, n.password_hash FROM attachments a
                     JOIN notes n ON n.id = a.note_id WHERE a.id = ? AND n.user_id = ?');
$st->execute([(int)($in['id'] ?? 0), $u['id']]);
$a = $st->fetch();
if (!$a) json_error('Không tìm thấy tệp.', 404);
require_note_open(['id' => $a['note_id'], 'password_hash' => $a['password_hash']]);
db()->prepare('DELETE FROM attachments WHERE id = ?')->execute([$a['id']]);
@unlink(attach_dir() . '/' . basename($a['file_path']));
json_out(['ok' => true]);

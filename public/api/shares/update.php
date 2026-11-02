<?php
// POST JSON {share_id, permission} : đổi quyền Read-only / Edit (chỉ chủ sở hữu ghi chú).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$perm = (string)($in['permission'] ?? '');
if (!in_array($perm, ['read', 'edit'], true)) json_error('Quyền truy cập không hợp lệ.', 422);
$st = db()->prepare('SELECT s.id FROM note_shares s JOIN notes n ON n.id = s.note_id WHERE s.id = ? AND n.user_id = ?');
$st->execute([(int)($in['share_id'] ?? 0), $u['id']]);
if (!$st->fetch()) json_error('Không tìm thấy bản chia sẻ.', 404);
db()->prepare('UPDATE note_shares SET permission = ? WHERE id = ?')->execute([$perm, (int)$in['share_id']]);
json_out(['ok' => true, 'permission' => $perm]);

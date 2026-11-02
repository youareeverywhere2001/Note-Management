<?php
// POST JSON {share_id} : thu hồi chia sẻ. Chủ sở hữu thu hồi được mọi bản chia sẻ của ghi chú mình;
// người được chia sẻ chỉ "rời khỏi" bản chia sẻ của chính mình.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$st = db()->prepare('SELECT s.id, s.shared_with_user_id, n.user_id AS owner_id FROM note_shares s
                     JOIN notes n ON n.id = s.note_id WHERE s.id = ?');
$st->execute([(int)($in['share_id'] ?? 0)]);
$s = $st->fetch();
$uid = (int)$u['id'];
if (!$s || ((int)$s['owner_id'] !== $uid && (int)$s['shared_with_user_id'] !== $uid)) json_error('Không tìm thấy bản chia sẻ.', 404);
db()->prepare('DELETE FROM note_shares WHERE id = ?')->execute([$s['id']]);
json_out(['ok' => true]);

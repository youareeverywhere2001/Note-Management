<?php
// POST JSON {id, pinned:true|false} : ghim / bỏ ghim. Không làm đổi "thời gian chỉnh sửa".
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note((int)($in['id'] ?? 0), $uid);
$pin = !empty($in['pinned']) ? 1 : 0;
db()->prepare('UPDATE notes SET is_pinned = ?, pinned_at = IF(? = 1, NOW(), NULL), updated_at = updated_at WHERE id = ? AND user_id = ?')
    ->execute([$pin, $pin, $n['id'], $uid]);
json_out(['ok' => true, 'is_pinned' => (bool)$pin]);

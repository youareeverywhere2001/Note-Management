<?php
// POST JSON {view_mode:'grid'|'list'} : lưu chế độ xem ghi chú vào user_preferences (bảng của TV1)
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$vm = $in['view_mode'] ?? '';
if (!in_array($vm, ['grid', 'list'], true)) json_error('Chế độ xem không hợp lệ.', 422);
db()->prepare('INSERT INTO user_preferences (user_id, view_mode) VALUES (?, ?) ON DUPLICATE KEY UPDATE view_mode = VALUES(view_mode)')
    ->execute([$u['id'], $vm]);
json_out(['ok' => true]);

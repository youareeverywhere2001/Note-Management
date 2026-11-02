<?php
// POST JSON {id, title, content, label_ids[]} : cập nhật ghi chú (dùng cho tự động lưu)
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$n = load_note((int)($in['id'] ?? 0), $uid);
require_note_open($n);
[$title, $content] = note_fields($in);

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
        ->execute([$title, $content, $n['id'], $uid]);
    if (array_key_exists('label_ids', $in)) sync_note_labels((int)$n['id'], $uid, (array)$in['label_ids']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('update note: ' . $e->getMessage());
    json_error('Không lưu được ghi chú.', 500);
}
$r = db()->prepare('SELECT updated_at FROM notes WHERE id = ?');
$r->execute([$n['id']]);
json_out(['ok' => true, 'updated_at' => $r->fetchColumn()]);

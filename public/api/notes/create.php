<?php
// POST JSON {title, content, label_ids[]} : tạo ghi chú (chỉ tiêu đề + nội dung là bắt buộc nhập)
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
[$title, $content] = note_fields($in);
$uid = (int)$u['id'];

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)')->execute([$uid, $title, $content]);
    $id = (int)$pdo->lastInsertId();
    sync_note_labels($id, $uid, (array)($in['label_ids'] ?? []));
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('create note: ' . $e->getMessage());
    json_error('Không tạo được ghi chú.', 500);
}
json_out(['note' => note_detail($id, $uid)], 201);

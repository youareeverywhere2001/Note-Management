<?php
// POST JSON {id, title?, content?} : lưu ghi chú bằng phiên đăng nhập (dự phòng khi không có WebSocket).
// Chủ sở hữu hoặc người được chia sẻ quyền Edit mới được ghi; Read-only bị từ chối ở máy chủ.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$n = load_note_access((int)($in['id'] ?? 0), (int)$u['id'], 'edit');
require_note_open($n);

$sets = []; $args = [];
if (array_key_exists('title', $in)) {
    $title = trim((string)$in['title']);
    if (mb_strlen($title) > 255) json_error('Tiêu đề tối đa 255 ký tự.', 422);
    $sets[] = 'title = ?'; $args[] = $title;
}
if (array_key_exists('content', $in)) {
    $content = (string)$in['content'];
    if (mb_strlen($content) > NOTE_MAX_CONTENT) json_error('Nội dung quá dài.', 422);
    $sets[] = 'content = ?'; $args[] = $content;
}
if (!$sets) json_error('Không có gì để lưu.', 422);
$args[] = $n['id'];
db()->prepare('UPDATE notes SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
$r = db()->prepare('SELECT updated_at FROM notes WHERE id = ?');
$r->execute([$n['id']]);
json_out(['ok' => true, 'updated_at' => $r->fetchColumn()]);

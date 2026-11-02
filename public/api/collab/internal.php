<?php
// Endpoint NỘI BỘ cho máy chủ WebSocket (Node), không dùng cho trình duyệt.
// Xác thực bằng header X-Internal-Secret = WS_SECRET. Quyền luôn được tính lại từ cơ sở dữ liệu.
// POST JSON {action:'get'|'perm'|'save', note_id, user_id, content?}
require __DIR__ . '/../../../src/api.php';
require __DIR__ . '/../../../src/collab.php';
session_write_close();

$secret = ws_secret();
if ($secret === '' || !hash_equals($secret, (string)($_SERVER['HTTP_X_INTERNAL_SECRET'] ?? ''))) json_error('Forbidden', 403);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Method not allowed', 405);

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) json_error('Bad request', 400);
$noteId = (int)($in['note_id'] ?? 0);
$uid = (int)($in['user_id'] ?? 0);
$n = $uid > 0 ? note_access($noteId, $uid) : null;
if (!$n) json_error('Không có quyền truy cập ghi chú.', 403);
$perm = $n['role'] === 'owner' ? 'edit' : $n['role'];

switch ($in['action'] ?? '') {
    case 'perm':
        json_out(['permission' => $perm]);
    case 'get':
        json_out(['permission' => $perm, 'content' => $n['content'], 'title' => $n['title']]);
    case 'save':
        if ($perm !== 'edit') json_error('Chỉ có quyền xem.', 403);
        $content = (string)($in['content'] ?? '');
        if (mb_strlen($content) > NOTE_MAX_CONTENT) json_error('Nội dung quá dài.', 422);
        db()->prepare('UPDATE notes SET content = ? WHERE id = ?')->execute([$content, $n['id']]);
        $r = db()->prepare('SELECT updated_at FROM notes WHERE id = ?');
        $r->execute([$n['id']]);
        json_out(['ok' => true, 'updated_at' => $r->fetchColumn()]);
    default:
        json_error('Hành động không hợp lệ.', 400);
}

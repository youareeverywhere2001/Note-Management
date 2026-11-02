<?php
// GET ?id= : nội dung ghi chú + vai trò (owner/edit/read) + thông tin người chia sẻ. Dùng cho trang cộng tác.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$n = load_note_access((int)($_GET['id'] ?? 0), (int)$u['id'], 'read');
require_note_open($n);   // ghi chú khóa: HTTP 423 cho tới khi nhập đúng mật khẩu
session_write_close();
json_out(['note' => [
    'id'          => (int)$n['id'],
    'title'       => $n['title'],
    'content'     => $n['content'],
    'role'        => $n['role'],
    'owner'       => owner_public($n),
    'is_locked'   => !empty($n['password_hash']),
    'attachments' => note_attachments((int)$n['id']),
    'updated_at'  => $n['updated_at'],
]]);

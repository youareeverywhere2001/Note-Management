<?php
// POST JSON {name} => thêm nhãn mới; {id, name} => đổi tên nhãn.
// Ghi chú gắn nhãn tham chiếu theo label_id nên đổi tên là mọi ghi chú tự hiển thị tên mới.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$id = (int)($in['id'] ?? 0);
$name = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')) ?? '');
if ($name === '' || mb_strlen($name) > 100) json_error('Tên nhãn phải từ 1 đến 100 ký tự.', 422);

// Trùng tên (không phân biệt hoa/thường) trong cùng tài khoản
$st = db()->prepare('SELECT id FROM labels WHERE user_id = ? AND name = ? AND id <> ?');
$st->execute([$uid, $name, $id]);
if ($st->fetch()) json_error('Nhãn này đã tồn tại.', 409);

try {
    if ($id > 0) {
        $st = db()->prepare('SELECT id FROM labels WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if (!$st->fetch()) json_error('Không tìm thấy nhãn.', 404);
        db()->prepare('UPDATE labels SET name = ? WHERE id = ? AND user_id = ?')->execute([$name, $id, $uid]);
    } else {
        db()->prepare('INSERT INTO labels (user_id, name) VALUES (?, ?)')->execute([$uid, $name]);
        $id = (int)db()->lastInsertId();
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') json_error('Nhãn này đã tồn tại.', 409); // hai yêu cầu trùng nhau cùng lúc
    throw $e;
}
json_out(['label' => ['id' => $id, 'name' => $name]]);

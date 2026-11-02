<?php
// POST multipart: note_id + files[] : tải một hoặc nhiều tệp (ảnh, video, tài liệu) lên ghi chú.
// Tệp lưu ngoài thư mục web, tên ngẫu nhiên; chỉ tải về được qua download.php (có kiểm tra quyền).
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$in = api_input();
$uid = (int)$u['id'];
$note = load_note((int)($in['note_id'] ?? 0), $uid);
require_note_open($note);

$files = normalize_files($_FILES['files'] ?? null);
if (!$files) json_error('Chưa chọn tệp nào.', 422);
if (count($files) > 10) json_error('Chỉ được tải tối đa 10 tệp mỗi lần.', 422);

$dir = attach_dir();
if (!is_dir($dir) && !mkdir($dir, 0775, true)) json_error('Không ghi được thư mục lưu tệp.', 500);

$cnt = db()->prepare('SELECT COUNT(*) FROM attachments WHERE note_id = ?');
$cnt->execute([$note['id']]);
$count = (int)$cnt->fetchColumn();

$types = allowed_upload_types();
$fi = new finfo(FILEINFO_MIME_TYPE);
$saved = []; $errors = [];

foreach ($files as $f) {
    $name = clean_file_name((string)$f['name']);
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "$name: tải lên thất bại" . ($f['error'] === UPLOAD_ERR_INI_SIZE ? ' (tệp quá lớn).' : '.');
        continue;
    }
    if ($f['size'] > ATTACH_MAX_BYTES) { $errors[] = "$name: vượt quá 20MB."; continue; }
    if ($count >= ATTACH_MAX_PER_NOTE) { $errors[] = "$name: mỗi ghi chú tối đa " . ATTACH_MAX_PER_NOTE . ' tệp.'; continue; }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = $fi->file($f['tmp_name']);              // đọc loại thật từ nội dung, không tin tên file/Content-Type của client
    if (!isset($types[$ext]) || !in_array($mime, $types[$ext], true)) { $errors[] = "$name: loại tệp không được hỗ trợ."; continue; }
    if (str_starts_with($mime, 'image/') && @getimagesize($f['tmp_name']) === false) { $errors[] = "$name: ảnh không hợp lệ."; continue; }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext; // tên và đuôi do server quyết định
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) { $errors[] = "$name: không lưu được tệp."; continue; }
    // Với tệp Office (docx...) finfo có thể trả application/zip; lưu theo loại đã được kiểm tra
    db()->prepare('INSERT INTO attachments (note_id, file_name, file_path, mime_type, file_size) VALUES (?,?,?,?,?)')
        ->execute([$note['id'], $name, $stored, $mime, (int)$f['size']]);
    $saved[] = ['id' => (int)db()->lastInsertId(), 'file_name' => $name, 'mime_type' => $mime, 'file_size' => (int)$f['size']];
    $count++;
}
json_out(['attachments' => $saved, 'errors' => $errors], $saved || !$errors ? 201 : 422);

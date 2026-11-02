<?php
// GET ?id= : xem/tải tệp đính kèm. Chủ ghi chú hoặc người được chia sẻ (đọc/sửa) mới tải được; ghi chú khóa phải được mở trước.
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$st = db()->prepare('SELECT a.file_name, a.file_path, a.mime_type, n.id AS note_id, n.password_hash FROM attachments a
                     JOIN notes n ON n.id = a.note_id
                     WHERE a.id = ? AND (n.user_id = ? OR EXISTS (SELECT 1 FROM note_shares s WHERE s.note_id = n.id AND s.shared_with_user_id = ?))');
$st->execute([(int)($_GET['id'] ?? 0), $u['id'], $u['id']]);
$a = $st->fetch();
if (!$a) json_error('Không tìm thấy tệp.', 404);
require_note_open(['id' => $a['note_id'], 'password_hash' => $a['password_hash']]);
session_write_close(); // để video (nhiều request Range) không bị khóa session

$path = attach_dir() . '/' . basename($a['file_path']);
if (!is_file($path)) json_error('Tệp không còn tồn tại.', 404);
$size = filesize($path);

// Chỉ ảnh/video/PDF mở trực tiếp; còn lại buộc tải về. nosniff để trình duyệt không đoán lại loại tệp.
$inline = preg_match('#^(image/|video/|application/pdf$)#', $a['mime_type']) === 1;
$ctype = $inline ? $a['mime_type'] : 'application/octet-stream';
$ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $a['file_name']);
header('Content-Type: ' . $ctype);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($a['file_name']));
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

// Hỗ trợ Range để video tua được
$start = 0; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
    if ($m[1] === '') { $start = max(0, $size - (int)$m[2]); }
    else { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $size - 1); }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, min(8192, $left));
    if ($chunk === false) break;
    echo $chunk;
    $left -= strlen($chunk);
}
fclose($fh);

<?php
// Hàm dùng chung cho các endpoint JSON trong public/api/ (TV2 viết, TV3 có thể dùng lại).
require_once __DIR__ . '/auth.php';

const ATTACH_MAX_BYTES    = 20 * 1024 * 1024;  // 20MB / tệp
const ATTACH_MAX_PER_NOTE = 20;                // số tệp tối đa / ghi chú
const NOTE_MAX_CONTENT    = 500000;            // số ký tự tối đa của nội dung

function attach_dir(): string {
    return rtrim(env('ATTACH_DIR', __DIR__ . '/../storage/attachments'), '/');
}

// ---------- Phản hồi JSON ----------
function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_error(string $msg, int $code = 400, array $extra = []): never {
    json_out(['error' => $msg] + $extra, $code);
}

// ---------- Xác thực + CSRF ----------
function api_user(): array {
    $u = current_user();
    if (!$u) json_error('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.', 401);
    return $u;
}
// Chỉ nhận POST + token CSRF (header X-CSRF-Token). Trả về dữ liệu gửi lên (JSON hoặc form).
function api_input(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Phương thức không hợp lệ.', 405);
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    // Gửi quá post_max_size thì PHP bỏ trống $_POST/$_FILES
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && stripos($ct, 'multipart/') === 0) {
        json_error('Tổng dung lượng tệp vượt quá giới hạn cho phép.', 413);
    }
    if (!csrf_valid()) json_error('Phiên làm việc không hợp lệ. Hãy tải lại trang.', 403);
    if (stripos($ct, 'application/json') === 0) {
        $d = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}

// ---------- Ghi chú ----------
// Chỉ lấy ghi chú của chính người dùng; ghi chú người khác => 404 (không lộ là có tồn tại).
function load_note(int $id, int $uid): array {
    $st = db()->prepare('SELECT * FROM notes WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    $n = $st->fetch();
    if (!$n) json_error('Không tìm thấy ghi chú.', 404);
    return $n;
}
// Ghi chú có mật khẩu: chỉ mở được sau khi nhập đúng mật khẩu (api/locks/unlock.php).
// Phiên lưu "dấu vân tay" của hash khóa hiện tại, nên nếu chủ sở hữu đổi mật khẩu ghi chú thì
// mọi phiên đã mở khóa trước đó tự động bị khóa lại.
function lock_fp(string $hash): string { return hash('sha256', $hash); }
function mark_note_unlocked(int $noteId, string $hash): void { $_SESSION['unlocked_notes'][$noteId] = lock_fp($hash); }
function note_locked(array $n): bool {
    if (empty($n['password_hash'])) return false;
    $fp = $_SESSION['unlocked_notes'][(int)$n['id']] ?? '';
    return !is_string($fp) || !hash_equals(lock_fp($n['password_hash']), $fp);
}
function require_note_open(array $n): void {
    if (note_locked($n)) json_error('Ghi chú đang được khóa bằng mật khẩu.', 423, ['locked' => true]);
}

function note_fields(array $in): array {
    $title = trim((string)($in['title'] ?? ''));
    $content = (string)($in['content'] ?? '');
    if (mb_strlen($title) > 255) json_error('Tiêu đề tối đa 255 ký tự.', 422);
    if (mb_strlen($content) > NOTE_MAX_CONTENT) json_error('Nội dung quá dài.', 422);
    return [$title, $content];
}

// Gắn nhãn cho ghi chú: chỉ nhận nhãn thuộc về chính người dùng. Gọi bên trong transaction.
function sync_note_labels(int $noteId, int $uid, array $labelIds): void {
    $ids = array_values(array_unique(array_filter(array_map('intval', $labelIds), fn($x) => $x > 0)));
    $valid = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT id FROM labels WHERE user_id = ? AND id IN ($in)");
        $st->execute(array_merge([$uid], $ids));
        $valid = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    db()->prepare('DELETE FROM note_labels WHERE note_id = ?')->execute([$noteId]);
    $ins = db()->prepare('INSERT INTO note_labels (note_id, label_id) VALUES (?, ?)');
    foreach ($valid as $lid) $ins->execute([$noteId, $lid]);
}

function note_attachments(int $noteId): array {
    $st = db()->prepare('SELECT id, file_name, mime_type, file_size FROM attachments WHERE note_id = ? ORDER BY id');
    $st->execute([$noteId]);
    return array_map(fn($a) => ['id' => (int)$a['id'], 'file_name' => $a['file_name'], 'mime_type' => $a['mime_type'], 'file_size' => (int)$a['file_size']], $st->fetchAll());
}

// Chi tiết đầy đủ của một ghi chú (dùng cho trình soạn thảo). KHÔNG trả password_hash.
function note_detail(int $id, int $uid): array {
    $n = load_note($id, $uid);
    $st = db()->prepare('SELECT label_id FROM note_labels WHERE note_id = ?');
    $st->execute([$id]);
    return [
        'id'          => (int)$n['id'],
        'title'       => $n['title'],
        'content'     => $n['content'],
        'is_pinned'   => (bool)$n['is_pinned'],
        'is_locked'   => !empty($n['password_hash']),
        'label_ids'   => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)),
        'attachments' => note_attachments($id),
        'created_at'  => $n['created_at'],
        'updated_at'  => $n['updated_at'],
    ];
}

// ---------- Tệp đính kèm ----------
// Phần mở rộng được phép => các MIME mà finfo (đọc nội dung thật) được chấp nhận.
function allowed_upload_types(): array {
    $office = ['application/zip', 'application/octet-stream'];
    return [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'mp4' => ['video/mp4'], 'webm' => ['video/webm'], 'mov' => ['video/quicktime'],
        'pdf' => ['application/pdf'], 'txt' => ['text/plain'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $office),
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $office),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $office),
        'zip' => ['application/zip'],
    ];
}
function normalize_files($f): array {
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        $out[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}
function clean_file_name(string $name): string {
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = trim($name);
    if (mb_strlen($name) > 200) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 180) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name === '' ? 'tep-dinh-kem' : $name;
}

// =====================================================================
// TV3: quyền truy cập (chủ sở hữu / được chia sẻ), khóa ghi chú, mật khẩu ghi chú
// =====================================================================
const NOTE_PASSWORD_MIN = 6;

// Trả về ghi chú kèm vai trò của người dùng: 'owner' | 'edit' | 'read'; null nếu không có quyền gì.
// Quyền luôn tính ở phía máy chủ: chủ sở hữu theo notes.user_id, người được chia sẻ theo bảng note_shares.
function note_access(int $noteId, int $uid): ?array {
    $st = db()->prepare(
        'SELECT n.*, u.display_name AS owner_name, u.email AS owner_email, u.avatar AS owner_avatar,
                (SELECT s.permission FROM note_shares s WHERE s.note_id = n.id AND s.shared_with_user_id = ? LIMIT 1) AS share_perm
         FROM notes n JOIN users u ON u.id = n.user_id WHERE n.id = ?');
    $st->execute([$uid, $noteId]);
    $n = $st->fetch();
    if (!$n) return null;
    if ((int)$n['user_id'] === $uid)      $n['role'] = 'owner';
    elseif ($n['share_perm'] === 'edit')  $n['role'] = 'edit';
    elseif ($n['share_perm'] === 'read')  $n['role'] = 'read';
    else return null;   // không có quyền => coi như không tồn tại
    return $n;
}
// $need: 'read' (xem) | 'edit' (sửa) | 'owner' (chỉ chủ sở hữu)
function load_note_access(int $id, int $uid, string $need = 'read'): array {
    $n = note_access($id, $uid);
    if (!$n) json_error('Không tìm thấy ghi chú.', 404);
    if ($need === 'edit' && $n['role'] === 'read') json_error('Bạn chỉ có quyền xem ghi chú này.', 403);
    if ($need === 'owner' && $n['role'] !== 'owner') json_error('Chỉ chủ sở hữu mới thực hiện được thao tác này.', 403);
    return $n;
}
function owner_public(array $n): array {
    return [
        'name'   => $n['owner_name'] ?: $n['owner_email'],
        'email'  => $n['owner_email'],
        'avatar' => !empty($n['owner_avatar']) ? 'uploads/avatars/' . $n['owner_avatar'] : null,
    ];
}

// ---- Chống đoán mật khẩu ghi chú: sai 5 lần => chờ 60 giây (theo phiên đăng nhập) ----
function lock_throttle_check(int $noteId): void {
    $f = $_SESSION['lock_fail'][$noteId] ?? null;
    if ($f && ($f['until'] ?? 0) > time()) json_error('Nhập sai nhiều lần. Hãy thử lại sau ' . ($f['until'] - time()) . ' giây.', 429);
}
function lock_throttle_fail(int $noteId): void {
    $f = $_SESSION['lock_fail'][$noteId] ?? ['n' => 0, 'until' => 0];
    $f['n'] = (int)$f['n'] + 1;
    if ($f['n'] >= 5) $f = ['n' => 0, 'until' => time() + 60];
    $_SESSION['lock_fail'][$noteId] = $f;
}
// Kiểm tra mật khẩu của ghi chú; sai thì dừng với lỗi JSON.
function verify_note_password(array $n, string $pw): void {
    $id = (int)$n['id'];
    lock_throttle_check($id);
    if (empty($n['password_hash']) || !password_verify($pw, $n['password_hash'])) {
        lock_throttle_fail($id);
        json_error('Mật khẩu không đúng.', 403);
    }
    unset($_SESSION['lock_fail'][$id]);
}
function validate_note_password(string $pw): void {
    if (mb_strlen($pw) < NOTE_PASSWORD_MIN) json_error('Mật khẩu ghi chú phải có ít nhất ' . NOTE_PASSWORD_MIN . ' ký tự.', 422);
    if (strlen($pw) > 72) json_error('Mật khẩu ghi chú tối đa 72 byte.', 422);
}

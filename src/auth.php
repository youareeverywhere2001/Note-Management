<?php
// Hàm dùng chung: session, CSRF, xác thực, tùy chọn. Mọi thành viên đều include file này.
require_once __DIR__ . '/db.php';

session_name('note_mgmt');
session_set_cookie_params([
    'httponly' => true,
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
// Chấp nhận token từ form (_csrf) hoặc từ header X-CSRF-Token (fetch/AJAX)
function csrf_valid(): bool {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function verify_csrf(): void {
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Phiên làm việc không hợp lệ (CSRF). Hãy tải lại trang.');
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    // session_version thay đổi (đổi/reset mật khẩu) => các session cũ bị vô hiệu
    if (!$u || (int)$u['session_version'] !== (int)($_SESSION['sv'] ?? -1)) {
        $_SESSION = [];
        return $cache = null;
    }
    return $cache = $u;
}
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['sv']  = (int)$u['session_version'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['unlocked_notes'] = [];   // TV3: phiên mới thì mọi ghi chú khóa đều khóa lại
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function validate_password(string $pw): ?string {
    if (strlen($pw) < 8) return 'Mật khẩu phải có ít nhất 8 ký tự.';
    if (strlen($pw) > 72) return 'Mật khẩu tối đa 72 ký tự.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Mật khẩu phải gồm cả chữ và số.';
    return null;
}

function get_preferences(int $uid): array {
    $st = db()->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
    $st->execute([$uid]);
    return $st->fetch() ?: ['user_id' => $uid, 'font_size' => 'medium', 'note_color' => '#ffffff', 'theme' => 'light', 'view_mode' => 'grid'];
}

function avatar_url(array $u): ?string {
    return !empty($u['avatar']) ? 'uploads/avatars/' . $u['avatar'] : null;
}
function avatar_html(array $u, int $size = 40): string {
    $url = avatar_url($u);
    if ($url) return '<img class="avatar" width="' . $size . '" height="' . $size . '" src="' . e($url) . '" alt="Ảnh đại diện">';
    $ch = mb_strtoupper(mb_substr($u['display_name'] ?: $u['email'], 0, 1));
    return '<span class="avatar avatar-default" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . ($size / 2) . 'px">' . e($ch) . '</span>';
}

// Địa chỉ gốc của website (dùng trong link email). Nên đặt APP_URL trong .env;
// nếu bỏ trống sẽ tự suy ra, kể cả khi chạy trong thư mục con của XAMPP (/Note-Management/public).
function app_url(): string {
    $u = env('APP_URL');
    if ($u) return rtrim($u, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (($p = strpos($script, '/public/')) !== false)      $base = substr($script, 0, $p + 7);
    elseif (($p = strpos($script, '/api/')) !== false)     $base = substr($script, 0, $p);
    else                                                   $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base;
}

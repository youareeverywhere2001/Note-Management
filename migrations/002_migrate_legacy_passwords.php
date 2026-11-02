<?php
// Chuyển mật khẩu cũ của TV3 (cột `users`.`password`) sang cột password_hash của hệ thống hợp nhất.
//
//   php migrations/002_migrate_legacy_passwords.php                  # chạy thử (không ghi gì)
//   php migrations/002_migrate_legacy_passwords.php --apply          # ghi vào CSDL
//   php migrations/002_migrate_legacy_passwords.php --apply --clear-legacy   # ghi rồi xóa giá trị cũ trong cột `password`
//
// Với mỗi tài khoản chưa có password_hash:
//   - Đã là hash của PHP (bcrypt/argon2): sao chép nguyên vẹn (vẫn dùng được với password_verify()).
//   - Trông như văn bản thường: băm bằng password_hash() — mật khẩu của người dùng KHÔNG bị đổi.
//   - Trông như hash cũ không thể đảo ngược (MD5/SHA...): KHÔNG chuyển được; tài khoản đó phải dùng "Quên mật khẩu".
// Script không bao giờ in mật khẩu hay hash ra màn hình, chỉ in id và email.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Chỉ chạy bằng dòng lệnh.\n"); }
require __DIR__ . '/../src/db.php';

$apply = in_array('--apply', $argv, true);
$clear = in_array('--clear-legacy', $argv, true);
$pdo = db();

$hasLegacy = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password'")->fetchColumn();
if (!$hasLegacy) { exit("Không có cột `password` cũ trong bảng users: không cần chuyển mật khẩu.\n"); }

$rows = $pdo->query("SELECT id, email, `password` AS legacy FROM users
    WHERE (password_hash IS NULL OR password_hash = '') AND `password` IS NOT NULL AND `password` <> ''")->fetchAll();
if (!$rows) { exit("Không có tài khoản nào cần chuyển mật khẩu.\n"); }

$copy = $hash = $skip = 0;
$upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND (password_hash IS NULL OR password_hash = \'\')');
$clr = $pdo->prepare('UPDATE users SET `password` = NULL WHERE id = ?');
echo ($apply ? "ĐANG GHI vào CSDL" : "CHẠY THỬ (thêm --apply để ghi)") . "\n";

foreach ($rows as $r) {
    $v = (string)$r['legacy'];
    $info = password_get_info($v);
    if ($info['algo'] !== null && $info['algo'] !== 0) { $new = $v; $kind = 'đã là hash PHP -> sao chép'; $copy++; }
    elseif (preg_match('/^[a-f0-9]{32}$|^[a-f0-9]{40}$|^[a-f0-9]{64}$/i', $v) || preg_match('/^\$(1|5|6|apr1)\$/', $v)) {
        echo "  #{$r['id']} {$r['email']}: hash cũ không thể đảo ngược -> BỎ QUA (dùng 'Quên mật khẩu')\n"; $skip++; continue;
    } else { $new = password_hash($v, PASSWORD_DEFAULT); $kind = 'văn bản thường -> băm bằng password_hash()'; $hash++; }
    echo "  #{$r['id']} {$r['email']}: $kind\n";
    if ($apply) {
        $upd->execute([$new, $r['id']]);
        if ($clear) $clr->execute([$r['id']]);
    }
}
echo "\nSao chép: $copy | Băm mới: $hash | Bỏ qua: $skip\n";
if (!$apply) echo "Chưa ghi gì. Chạy lại với --apply để áp dụng.\n";

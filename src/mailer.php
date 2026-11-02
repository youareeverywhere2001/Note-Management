<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// MAIL_DRIVER=smtp (mặc định) gửi qua SMTP bằng PHPMailer (cần chạy `composer install`).
// MAIL_DRIVER=log chỉ ghi email vào storage/mail/mail.log: tiện khi chạy XAMPP không có SMTP
// (mở file log để lấy link kích hoạt / mã OTP).
function send_mail(string $to, string $subject, string $html): bool {
    if (strtolower((string)env('MAIL_DRIVER', 'smtp')) === 'log') {
        $dir = __DIR__ . '/../storage/mail';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $entry = '[' . date('c') . "] TO: $to | SUBJECT: $subject\n" . trim(strip_tags(str_replace(['</p>', '<br>'], "\n", $html))) . "\n" . str_repeat('-', 60) . "\n";
        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Mail error: chưa cài PHPMailer (chạy "composer install") hoặc đặt MAIL_DRIVER=log.');
        return false;
    }
    require_once $autoload;
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host = env('SMTP_HOST', 'mailpit');
        $m->Port = (int)env('SMTP_PORT', 1025);
        $user = env('SMTP_USER', '');
        if ($user !== '') {
            $m->SMTPAuth = true; $m->Username = $user; $m->Password = env('SMTP_PASS', '');
            $m->SMTPSecure = ((int)$m->Port === 465) ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else { $m->SMTPAuth = false; $m->SMTPAutoTLS = false; }
        $m->CharSet = 'UTF-8';
        $m->setFrom(env('MAIL_FROM', 'no-reply@noteapp.local'), 'NoteApp');
        $m->addAddress($to);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = $html;
        $m->send();
        return true;
    } catch (Throwable $ex) {
        error_log('Mail error: ' . $ex->getMessage());
        return false;
    }
}

function send_activation_email(array $user): bool {
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET activation_token_hash = ?, activation_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?')
        ->execute([hash('sha256', $token), $user['id']]);
    $link = app_url() . '/activate.php?token=' . $token;
    $html = '<p>Xin chào ' . e($user['display_name']) . ',</p><p>Nhấn vào liên kết sau để kích hoạt tài khoản (hiệu lực 24 giờ):</p>'
          . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>';
    return send_mail($user['email'], 'Kích hoạt tài khoản NoteApp', $html);
}

function send_reset_email(array $user, string $token, string $otp): bool {
    $link = app_url() . '/reset.php?token=' . $token;
    $html = '<p>Xin chào ' . e($user['display_name']) . ',</p>'
          . '<p>Bạn có thể đặt lại mật khẩu bằng một trong hai cách (hiệu lực 15 phút):</p>'
          . '<p>1) Nhấn vào liên kết: <a href="' . e($link) . '">' . e($link) . '</a></p>'
          . '<p>2) Nhập mã OTP: <b style="font-size:20px;letter-spacing:3px">' . e($otp) . '</b></p>'
          . '<p>Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>';
    return send_mail($user['email'], 'Đặt lại mật khẩu NoteApp', $html);
}

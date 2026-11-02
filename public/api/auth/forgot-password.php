<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf();
$d=json_input(); $email=strtolower(trim($d['email']??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) json_response(false,'Email không hợp lệ.',[],422);
$st=db()->prepare('SELECT * FROM users WHERE email=?');$st->execute([$email]);$u=$st->fetch();
if($u){
 db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$u['id']]);
 $token=bin2hex(random_bytes(32));$otp=(string)random_int(100000,999999);
 db()->prepare('INSERT INTO password_resets(user_id,token_hash,otp_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))')
 ->execute([$u['id'],hash('sha256',$token),password_hash($otp,PASSWORD_BCRYPT)]);
 send_reset_email($u,$token,$otp);
}
json_response(true,'Nếu email tồn tại trong hệ thống, hướng dẫn đặt lại mật khẩu đã được gửi.');

<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf();
$d = json_input();
$email = strtolower(trim($d['email'] ?? '')); $name = trim($d['display_name'] ?? '');
$pw = (string)($d['password'] ?? ''); $pw2 = (string)($d['password2'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>255) json_response(false,'Email không hợp lệ.',[],422);
if (mb_strlen($name)<2 || mb_strlen($name)>100) json_response(false,'Tên hiển thị phải từ 2 đến 100 ký tự.',[],422);
if ($err=validate_password($pw)) json_response(false,$err,[],422);
if ($pw!==$pw2) json_response(false,'Hai mật khẩu không khớp.',[],422);
$st=db()->prepare('SELECT id FROM users WHERE email=?'); $st->execute([$email]);
if ($st->fetch()) json_response(false,'Email này đã được đăng ký.',[],409);
try {
 db()->beginTransaction();
 db()->prepare('INSERT INTO users(email,display_name,password_hash) VALUES(?,?,?)')->execute([$email,$name,password_hash($pw,PASSWORD_BCRYPT)]);
 $id=(int)db()->lastInsertId();
 db()->prepare('INSERT INTO user_preferences(user_id) VALUES(?)')->execute([$id]);
 db()->commit();
} catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); error_log($e->getMessage()); json_response(false,'Không thể tạo tài khoản lúc này.',[],500); }
$st=db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$id]); $u=$st->fetch();
$sent=send_activation_email($u); login_user($u);
json_response(true,$sent?'Đăng ký thành công. Email kích hoạt đã được gửi.':'Đăng ký thành công nhưng chưa gửi được email kích hoạt.', ['redirect'=>'index.php','email_sent'=>$sent],201);

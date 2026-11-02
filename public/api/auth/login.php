<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf();
$d=json_input(); $email=strtolower(trim($d['email']??'')); $pw=(string)($d['password']??'');
$st=db()->prepare('SELECT * FROM users WHERE email=?'); $st->execute([$email]); $u=$st->fetch();
if ($u && $u['locked_until'] && strtotime($u['locked_until'])>time()) json_response(false,'Tài khoản tạm khóa do nhập sai nhiều lần. Vui lòng thử lại sau vài phút.',[],429);
if ($u && password_verify($pw,(string)$u['password_hash'])) {
 db()->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL WHERE id=?')->execute([$u['id']]);
 login_user($u); json_response(true,'Đăng nhập thành công.',['redirect'=>'index.php']);
}
if($u){$f=(int)$u['failed_attempts']+1;if($f>=5) db()->prepare('UPDATE users SET failed_attempts=0,locked_until=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?')->execute([$u['id']]); else db()->prepare('UPDATE users SET failed_attempts=? WHERE id=?')->execute([$f,$u['id']]);}
else password_verify($pw,'$2y$10$abcdefghijklmnopqrstuuYhZ0Pz0y0y0y0y0y0y0y0y0y0y0y0y.');
json_response(false,'Email hoặc mật khẩu không đúng.',[],401);

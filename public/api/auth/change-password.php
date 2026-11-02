<?php
require_once __DIR__ . '/../common.php';api_method('POST');api_csrf();$u=api_login();$d=json_input();
$old=(string)($d['old_password']??'');$pw=(string)($d['password']??'');$pw2=(string)($d['password2']??'');
if(!password_verify($old,(string)$u['password_hash']))json_response(false,'Mật khẩu hiện tại không đúng.',[],422);
if($err=validate_password($pw))json_response(false,$err,[],422);
if($pw!==$pw2)json_response(false,'Hai mật khẩu mới không khớp.',[],422);
if(hash_equals($old,$pw))json_response(false,'Mật khẩu mới phải khác mật khẩu hiện tại.',[],422);
db()->prepare('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?')->execute([password_hash($pw,PASSWORD_BCRYPT),$u['id']]);
$st=db()->prepare('SELECT * FROM users WHERE id=?');$st->execute([$u['id']]);login_user($st->fetch());
json_response(true,'Đổi mật khẩu thành công. Các thiết bị khác đã bị đăng xuất.');

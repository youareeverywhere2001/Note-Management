<?php
require_once __DIR__ . '/../common.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
 $token=$_GET['token']; $st=db()->prepare('SELECT id FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>NOW()');
 $st->execute([hash('sha256',$token)]); $r=$st->fetch();
 if(!$r) json_response(false,'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',[],400);
 $_SESSION['reset_id']=(int)$r['id']; json_response(true,'Liên kết hợp lệ.',['step'=>'newpw']);
}
api_method('POST'); api_csrf(); $d=json_input();
if(($d['action']??'otp')==='otp'){
 $email=strtolower(trim($d['email']??''));$otp=trim($d['otp']??'');
 $st=db()->prepare('SELECT r.* FROM password_resets r JOIN users u ON u.id=r.user_id WHERE u.email=? AND r.used=0 AND r.expires_at>NOW() AND r.attempts<5');
 $st->execute([$email]);$r=$st->fetch();
 if(!$r || !password_verify($otp,$r['otp_hash'])){if($r)db()->prepare('UPDATE password_resets SET attempts=attempts+1 WHERE id=?')->execute([$r['id']]);json_response(false,'Email hoặc mã OTP không đúng, hoặc mã đã hết hạn.',[],400);}
 $_SESSION['reset_id']=(int)$r['id']; json_response(true,'Mã OTP hợp lệ.',['step'=>'newpw']);
}
if(($d['action']??'')==='token'){
 $token=$d['token']??'';$st=db()->prepare('SELECT id FROM password_resets WHERE token_hash=? AND used=0 AND expires_at>NOW()');
 $st->execute([hash('sha256',$token)]);$r=$st->fetch();
 if(!$r) json_response(false,'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',[],400);
 $_SESSION['reset_id']=(int)$r['id'];json_response(true,'Liên kết hợp lệ.',['step'=>'newpw']);
}
if(($d['action']??'')==='newpw' && !empty($_SESSION['reset_id'])){
 $pw=(string)($d['password']??'');$pw2=(string)($d['password2']??'');
 if($err=validate_password($pw))json_response(false,$err,[],422);
 if($pw!==$pw2)json_response(false,'Hai mật khẩu không khớp.',[],422);
 $st=db()->prepare('SELECT * FROM password_resets WHERE id=? AND used=0 AND expires_at>NOW()');$st->execute([$_SESSION['reset_id']]);$r=$st->fetch();
 if(!$r){unset($_SESSION['reset_id']);json_response(false,'Phiên đặt lại mật khẩu đã hết hạn. Hãy yêu cầu lại.',[],400);}
 db()->prepare('UPDATE users SET password_hash=?,session_version=session_version+1,failed_attempts=0,locked_until=NULL WHERE id=?')->execute([password_hash($pw,PASSWORD_BCRYPT),$r['user_id']]);
 db()->prepare('UPDATE password_resets SET used=1 WHERE id=?')->execute([$r['id']]);unset($_SESSION['reset_id']);
 json_response(true,'Đặt lại mật khẩu thành công. Vui lòng đăng nhập.',['redirect'=>'login.php']);
}
json_response(false,'Yêu cầu đặt lại mật khẩu không hợp lệ.',[],400);

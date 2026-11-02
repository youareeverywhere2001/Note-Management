<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf(); $u=api_login();
if($u['is_verified']) json_response(true,'Tài khoản đã được kích hoạt.');
if(time()-($_SESSION['last_resend']??0)<60) json_response(false,'Vui lòng đợi 1 phút trước khi gửi lại.',[],429);
$_SESSION['last_resend']=time(); $ok=send_activation_email($u);
json_response($ok,$ok?'Đã gửi lại email kích hoạt.':'Không gửi được email, hãy thử lại sau.',[], $ok?200:500);

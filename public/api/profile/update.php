<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf(); $u=api_login();
$name=trim($_POST['display_name']??'');
if(mb_strlen($name)<2||mb_strlen($name)>100)json_response(false,'Tên hiển thị phải từ 2 đến 100 ký tự.',[],422);
$newAvatar=null;$f=$_FILES['avatar']??null;
if($f && $f['error']!==UPLOAD_ERR_NO_FILE){
 if($f['error']!==UPLOAD_ERR_OK)json_response(false,'Tải ảnh lên thất bại.',[],422);
 if($f['size']>2*1024*1024)json_response(false,'Ảnh đại diện tối đa 2MB.',[],422);
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 if(!isset($map[$mime])||@getimagesize($f['tmp_name'])===false)json_response(false,'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP hợp lệ.',[],422);
 $newAvatar=bin2hex(random_bytes(16)).'.'.$map[$mime];$dir=__DIR__.'/../../uploads/avatars/';
 if(!move_uploaded_file($f['tmp_name'],$dir.$newAvatar))json_response(false,'Không lưu được ảnh đại diện.',[],500);
}
if($newAvatar){db()->prepare('UPDATE users SET display_name=?,avatar=? WHERE id=?')->execute([$name,$newAvatar,$u['id']]);if($u['avatar'])@unlink(__DIR__.'/../../uploads/avatars/'.basename($u['avatar']));}
else db()->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$name,$u['id']]);
json_response(true,'Đã cập nhật hồ sơ.');

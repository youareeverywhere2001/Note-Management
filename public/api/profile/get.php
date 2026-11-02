<?php
require_once __DIR__ . '/../common.php'; api_method('GET'); $u=api_login();
json_response(true,'Lấy hồ sơ thành công.',['user'=>['id'=>(int)$u['id'],'email'=>$u['email'],'display_name'=>$u['display_name'],'avatar'=>$u['avatar']?'uploads/avatars/'.$u['avatar']:null,'is_verified'=>(bool)$u['is_verified'] ]]);

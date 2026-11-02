<?php
require_once __DIR__ . '/../common.php'; api_method('GET');
$token=$_GET['token']??'';
if(!preg_match('/^[a-f0-9]{64}$/',$token)) json_response(false,'Liên kết kích hoạt không hợp lệ.',[],400);
$st=db()->prepare('SELECT id FROM users WHERE activation_token_hash=? AND activation_expires>NOW()');$st->execute([hash('sha256',$token)]);
$r=$st->fetch(); if(!$r) json_response(false,'Liên kết kích hoạt sai hoặc đã hết hạn.',[],400);
db()->prepare('UPDATE users SET is_verified=1,activation_token_hash=NULL,activation_expires=NULL WHERE id=?')->execute([$r['id']]);
json_response(true,'Kích hoạt tài khoản thành công.',['redirect'=>'index.php']);

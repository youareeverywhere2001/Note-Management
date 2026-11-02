<?php
require_once __DIR__ . '/../common.php';api_method('POST');api_csrf();$u=api_login();$d=json_input();
$fs=$d['font_size']??'';$th=$d['theme']??'';$col=$d['note_color']??'';
if(!in_array($fs,['small','medium','large'],true))json_response(false,'Cỡ chữ không hợp lệ.',[],422);
if(!in_array($th,['light','dark'],true))json_response(false,'Giao diện không hợp lệ.',[],422);
if(!preg_match('/^#[0-9a-fA-F]{6}$/',$col))json_response(false,'Màu ghi chú không hợp lệ.',[],422);
db()->prepare('INSERT INTO user_preferences(user_id,font_size,note_color,theme) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE font_size=VALUES(font_size),note_color=VALUES(note_color),theme=VALUES(theme)')
->execute([$u['id'],$fs,strtolower($col),$th]);
json_response(true,'Đã lưu tùy chọn.');

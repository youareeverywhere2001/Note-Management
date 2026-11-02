<?php
require_once __DIR__ . '/../common.php'; api_method('GET');$u=api_login();
json_response(true,'Lấy tùy chọn thành công.',['preferences'=>get_preferences((int)$u['id'])]);

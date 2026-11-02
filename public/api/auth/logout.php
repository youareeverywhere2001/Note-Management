<?php
require_once __DIR__ . '/../common.php'; api_method('POST'); api_csrf();
logout_user(); json_response(true,'Đăng xuất thành công.',['redirect'=>'login.php']);

<?php
require __DIR__ . '/../src/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); logout_user(); }
redirect('login.php');

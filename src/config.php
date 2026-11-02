<?php
// Cấu hình tập trung. Giá trị lấy từ biến môi trường (Docker) hoặc từ file .env ở thư mục gốc dự án (XAMPP).
// Biến môi trường thật luôn được ưu tiên hơn file .env.
(function () {
    $file = __DIR__ . '/../.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
        if ($k !== '' && getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
})();

function env(string $k, $d = null) { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }

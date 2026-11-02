<?php
// MỘT kết nối PDO duy nhất cho cả website (TV1, TV2, TV3 đều gọi db()).
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306')
             . ';dbname=' . env('DB_NAME', 'note_management') . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        } catch (PDOException $e) {
            error_log('DB connect failed: ' . $e->getMessage());
            http_response_code(500);
            $msg = 'Không kết nối được cơ sở dữ liệu. Hãy kiểm tra cấu hình DB_* (file .env hoặc docker-compose.yml).';
            if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['success' => false, 'message' => $msg, 'error' => $msg], JSON_UNESCAPED_UNICODE));
            }
            exit($msg);
        }
    }
    return $pdo;
}

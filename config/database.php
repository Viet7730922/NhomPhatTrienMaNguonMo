<?php
/**
 * Kết nối CSDL bằng PDO.
 * Dùng: $pdo = db();  rồi $stmt = $pdo->prepare('SELECT ... WHERE id = ?');
 */
require_once __DIR__ . '/config.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'elearning');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP mặc định không có mật khẩu

function db(): PDO
{
    static $pdo = null;          // chỉ tạo kết nối 1 lần cho mỗi request

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // dùng prepared statement thật
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit(DEBUG
                ? 'Lỗi kết nối CSDL: ' . htmlspecialchars($ex->getMessage())
                : 'Không thể kết nối cơ sở dữ liệu.');
        }
    }
    return $pdo;
}

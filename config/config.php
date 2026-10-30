<?php
/**
 * Cấu hình chung của website.
 * File này phải được require đầu tiên ở mọi trang (thông qua includes/auth.php).
 */

// ---- Chế độ phát triển: hiện lỗi để dễ debug. Khi nộp/demo thật có thể đặt false ----
define('DEBUG', true);
if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ---- Thông tin site ----
define('SITE_NAME', 'E-Learning');
define('BASE_URL', 'http://localhost/elearning');   // KHÔNG có dấu / ở cuối

// ---- Đường dẫn trên ổ đĩa (dùng cho include / upload) ----
define('ROOT_PATH', dirname(__DIR__));              // D:\xampp\htdocs\elearning
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// ---- Upload ----
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);         // 5 MB
define('ALLOWED_UPLOAD_EXT', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png']);

date_default_timezone_set('Asia/Ho_Chi_Minh');

// ---- Session (bảo mật cookie) ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

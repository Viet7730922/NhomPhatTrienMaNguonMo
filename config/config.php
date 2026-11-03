<?php
/**
 * Cấu hình chung của website.
 * File này phải được require đầu tiên ở mọi trang (thông qua includes/auth.php).
 */

// ---- Chế độ phát triển: hiện lỗi để dễ debug. Khi nộp/demo thật PHẢI đặt false ----
define('DEBUG', true);
if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');          // không để lộ đường dẫn/SQL ra trình duyệt
    ini_set('log_errors', '1');
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

// ---- Header bảo mật cơ bản (Bước 6) ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');                       // không đoán MIME
    header('X-Frame-Options: SAMEORIGIN');                           // chống clickjacking
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// ---- Session (bảo mật cookie) ----
const SESSION_IDLE_SECONDS = 2 * 3600;              // 2 giờ không thao tác thì tự đăng xuất

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');         // không nhận session id do client tự đặt
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,                      // bật tự động khi chạy HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!empty($_SESSION['user']) && isset($_SESSION['last_active'])
    && time() - (int) $_SESSION['last_active'] > SESSION_IDLE_SECONDS) {
    unset($_SESSION['user']);                        // hết hạn phiên: bắt đăng nhập lại
}
$_SESSION['last_active'] = time();

// Tham số dạng mảng (?q[]=x, name[]=x) không hợp lệ ở mọi trang, TRỪ các trường mảng có chủ đích bên dưới.
// Mỗi trang dùng các trường này vẫn phải tự kiểm tra kiểu và giá trị từng phần tử.
const ALLOWED_ARRAY_POST_FIELDS = [
    'remove_files',                 // student/assignment.php
    'answers',                      // student/quiz_attempt.php  (answers[qid] hoặc answers[qid][])
    'opt_text', 'opt_correct',      // teacher/question_form.php
    'points', 'feedback',           // teacher/quiz_grade.php
];
foreach ($_GET as $k => $v) {
    if (is_array($v)) { $_GET[$k] = ''; }
}
foreach ($_POST as $k => $v) {
    if (is_array($v) && !in_array($k, ALLOWED_ARRAY_POST_FIELDS, true)) { $_POST[$k] = ''; }
}
unset($k, $v);

<?php
/**
 * Các hàm tiện ích dùng chung.
 */

/** Escape HTML để chống XSS. LUÔN dùng khi in dữ liệu ra trang: <?= e($text) ?> */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Tạo URL tuyệt đối từ đường dẫn tương đối: url('login.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Chuyển hướng rồi dừng script. Phải gọi TRƯỚC khi in bất kỳ HTML nào. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---------- Flash message (thông báo hiện 1 lần) ----------
/** $type: success | danger | warning | info (theo Bootstrap) */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------- CSRF ----------
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** In input ẩn chứa token. Đặt trong mọi <form method="post">. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfVerify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- Form ----------
/** Giữ lại giá trị người dùng đã nhập khi form có lỗi. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

<?php
/**
 * Chống dò mật khẩu: giới hạn số lần đăng nhập sai theo cặp (email, IP).
 * Dùng cặp thay vì chỉ email để kẻ tấn công không thể khóa tài khoản của người khác từ IP lạ,
 * và thay vì chỉ IP để cả mạng ký túc xá / phòng máy (cùng 1 IP) không bị chặn oan.
 */
const LOGIN_MAX_FAILS   = 5;     // sai quá 5 lần
const LOGIN_WINDOW_MIN  = 15;    // trong 15 phút thì tạm khóa

function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);   // không tin X-Forwarded-For (giả mạo được)
}

function loginKey(string $email): string
{
    return mb_substr(mb_strtolower(trim($email)), 0, 150);
}

function loginThrottled(string $email): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . (int) LOGIN_WINDOW_MIN . ' MINUTE)'
    );
    $stmt->execute([loginKey($email), clientIp()]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS;
}

function recordLoginFailure(string $email): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([loginKey($email), clientIp()]);
    if (random_int(1, 50) === 1) {   // thỉnh thoảng dọn bản ghi cũ
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}

function clearLoginFailures(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?')->execute([loginKey($email), clientIp()]);
}

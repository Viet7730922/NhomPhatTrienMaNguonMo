<?php
/**
 * MẪU cấu hình gửi email. Cách dùng:
 *   1. Copy file này thành  config/mail.local.php   (file đó đã nằm trong .gitignore nên KHÔNG bị đưa lên GitHub)
 *   2. Điền thông tin SMTP thật vào config/mail.local.php
 *
 * KHÔNG bao giờ ghi mật khẩu vào file này (file này được commit lên GitHub).
 *
 * ---- Cách 1: Gmail (gửi thư thật) ----
 *   - Bật xác minh 2 bước cho tài khoản Google, rồi tạo "Mật khẩu ứng dụng" (App Password, 16 ký tự):
 *     https://myaccount.google.com/apppasswords
 *   - Dùng mật khẩu ứng dụng đó cho 'password' (KHÔNG dùng mật khẩu đăng nhập Gmail).
 *
 * ---- Cách 2: Mailtrap (khuyên dùng khi test: thư không tới hộp thư thật của ai) ----
 *   - Đăng ký miễn phí tại mailtrap.io -> Email Testing -> Inboxes -> SMTP Settings,
 *     chép host / port / username / password vào bên dưới (encryption để 'tls').
 */
return [
    'enabled'    => true,                       // false = tắt hẳn việc gửi mail (web vẫn chạy bình thường)
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',                      // 'tls' (cổng 587) | 'ssl' (cổng 465) | '' (không mã hóa, chỉ để thử trên máy)
    'username'   => 'ten-cua-ban@gmail.com',
    'password'   => 'xxxx xxxx xxxx xxxx',      // App Password (Gmail) hoặc mật khẩu SMTP của dịch vụ khác
    'from_email' => 'ten-cua-ban@gmail.com',    // Gmail bắt buộc trùng với username
    'from_name'  => 'E-Learning',
];

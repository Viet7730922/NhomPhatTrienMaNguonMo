<?php
require_once __DIR__ . '/includes/auth.php';

// Đăng xuất bằng POST + CSRF: một thẻ <img src="logout.php"> ở trang khác không thể ép người dùng đăng xuất
if (!isPost() || !csrfVerify()) {
    redirect(url('index.php'));
}

logoutUser();

// Session đã bị hủy, mở session mới để hiện thông báo
session_start();
flash('info', 'Bạn đã đăng xuất.');
redirect(url('login.php'));

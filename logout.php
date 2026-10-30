<?php
require_once __DIR__ . '/includes/auth.php';

logoutUser();

// Session đã bị hủy, mở session mới để hiện thông báo
session_start();
flash('info', 'Bạn đã đăng xuất.');
redirect(url('login.php'));

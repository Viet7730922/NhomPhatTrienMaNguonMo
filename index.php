<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Trang chủ';
require __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 bg-white rounded-3 shadow-sm text-center">
    <h1 class="display-6 fw-bold">Chào mừng đến với <?= e(SITE_NAME) ?></h1>
    <p class="lead text-muted">Danh sách khóa học sẽ được hiển thị ở đây (Bước 2).</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

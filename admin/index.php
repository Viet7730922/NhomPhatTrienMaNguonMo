<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Trang quản trị viên';
require __DIR__ . '/../includes/header.php';
?>

<h3>Trang quản trị viên</h3>
<p class="text-muted">Xin chào <strong><?= e(currentUser()['full_name']) ?></strong>. Chức năng sẽ được bổ sung ở các bước tiếp theo.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>

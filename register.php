<?php
require_once __DIR__ . '/includes/auth.php';
requireGuest();

$errors = [];

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $errors[] = 'Họ tên không được để trống và tối đa 100 ký tự.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Mật khẩu nhập lại không khớp.';
        }

        // Kiểm tra email đã tồn tại chưa
        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email này đã được đăng ký.';
            }
        }

        // Tạo tài khoản (đăng ký công khai luôn là học viên)
        if (!$errors) {
            $stmt = db()->prepare(
                'INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, "student")'
            );
            $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);

            flash('success', 'Đăng ký thành công! Hãy đăng nhập.');
            redirect(url('login.php'));
        }
    }
}

$pageTitle = 'Đăng ký';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center">Đăng ký tài khoản học viên</h4>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Họ và tên</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               value="<?= old('full_name') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= old('email') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu (tối thiểu 6 ký tự)</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Nhập lại mật khẩu</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đăng ký</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    Đã có tài khoản? <a href="<?= url('login.php') ?>">Đăng nhập</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

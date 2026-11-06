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

        // Tạo tài khoản (đăng ký công khai luôn là sinh viên)
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

<section class="auth-layout auth-layout-register" aria-labelledby="register-title">
    <div class="auth-card">
        <aside class="auth-aside">
            <a class="auth-brand" href="<?= url('index.php') ?>">
                <span class="auth-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <span><?= e(SITE_NAME) ?></span>
            </a>
            <div class="auth-aside-content">
                <span class="auth-eyebrow">BẮT ĐẦU NGAY HÔM NAY</span>
                <h2>Mở ra những cơ hội học tập mới.</h2>
                <p>Tạo tài khoản miễn phí để tham gia khóa học và phát triển kỹ năng theo cách của bạn.</p>
                <div class="auth-benefits">
                    <span><i class="bi bi-check-circle-fill"></i> Học tập theo tiến độ của riêng bạn</span>
                    <span><i class="bi bi-check-circle-fill"></i> Theo dõi bài tập và kết quả</span>
                    <span><i class="bi bi-check-circle-fill"></i> Truy cập mọi lúc, mọi nơi</span>
                </div>
            </div>
            <div class="auth-aside-footer"><i class="bi bi-stars"></i> Hành trình mới bắt đầu từ đây</div>
        </aside>

        <div class="auth-content">
            <div class="auth-heading">
                <span class="auth-mobile-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="auth-eyebrow">TẠO TÀI KHOẢN MIỄN PHÍ</span>
                <h1 id="register-title">Đăng ký</h1>
                <p>Điền thông tin để bắt đầu hành trình học tập của bạn.</p>
            </div>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger auth-alert" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="post" class="auth-form">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label" for="full_name">Họ và tên</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               placeholder="Nguyễn Văn A" value="<?= old('full_name') ?>"
                               maxlength="100" autocomplete="name" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Địa chỉ email</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope"></i>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="ban@example.com" value="<?= old('email') ?>" autocomplete="email" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu <span class="auth-label-hint">(ít nhất 6 ký tự)</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Tạo mật khẩu" autocomplete="new-password" minlength="6" required>
                        <button class="auth-password-toggle" type="button" data-password-toggle="password"
                                aria-label="Hiện mật khẩu" aria-pressed="false">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Nhập lại mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-shield-lock"></i>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               placeholder="Nhập lại mật khẩu" autocomplete="new-password" required>
                        <button class="auth-password-toggle" type="button" data-password-toggle="confirm_password"
                                aria-label="Hiện mật khẩu" aria-pressed="false">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback auth-password-feedback">Mật khẩu nhập lại không khớp.</div>
                </div>
                <button type="submit" class="btn auth-submit w-100">
                    Tạo tài khoản <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <p class="auth-switch">Đã có tài khoản? <a href="<?= url('login.php') ?>">Đăng nhập</a></p>
            <a class="auth-back-link" href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Quay về trang chủ</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

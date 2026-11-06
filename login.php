<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
requireGuest();

// Hash giả để tốn thời gian verify như thật khi email không tồn tại (chống dò email qua thời gian phản hồi)
const DUMMY_PASSWORD_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

$errors = [];

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Vui lòng nhập email và mật khẩu.';
        } elseif (loginThrottled($email)) {
            $errors[] = 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . LOGIN_WINDOW_MIN . ' phút.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $valid = password_verify($password, $user['password'] ?? DUMMY_PASSWORD_HASH) && $user !== false;

            // Thông báo chung để không lộ email nào tồn tại
            if (!$valid) {
                recordLoginFailure($email);
                $errors[] = 'Email hoặc mật khẩu không đúng.';
            } elseif (!$user['is_active']) {
                $errors[] = 'Tài khoản của bạn đã bị khóa.';
            } else {
                clearLoginFailures($email);
                loginUser($user);
                flash('success', 'Xin chào ' . $user['full_name'] . '!');
                redirect(roleHome($user['role']));
            }
        }
    }
}

$pageTitle = 'Đăng nhập';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-layout" aria-labelledby="login-title">
    <div class="auth-card">
        <aside class="auth-aside">
            <a class="auth-brand" href="<?= url('index.php') ?>">
                <span class="auth-brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <span><?= e(SITE_NAME) ?></span>
            </a>
            <div class="auth-aside-content">
                <span class="auth-eyebrow">CHÀO MỪNG BẠN TRỞ LẠI</span>
                <h2>Tiếp tục hành trình học tập của bạn.</h2>
                <p>Đăng nhập để khám phá khóa học, theo dõi tiến độ và hoàn thành mục tiêu của bạn.</p>
                <div class="auth-highlight">
                    <i class="bi bi-lightbulb"></i>
                    <span>Mỗi ngày học thêm một điều mới.</span>
                </div>
            </div>
            <div class="auth-aside-footer"><i class="bi bi-shield-check"></i> Không gian học tập an toàn</div>
        </aside>

        <div class="auth-content">
            <div class="auth-heading">
                <span class="auth-mobile-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="auth-eyebrow">TÀI KHOẢN CỦA BẠN</span>
                <h1 id="login-title">Đăng nhập</h1>
                <p>Chào mừng trở lại! Vui lòng nhập thông tin bên dưới.</p>
            </div>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger auth-alert" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= e($err) ?>
                </div>
            <?php endforeach; ?>

            <form method="post" class="auth-form">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Địa chỉ email</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-envelope"></i>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="ban@example.com" value="<?= old('email') ?>" autocomplete="email" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Nhập mật khẩu của bạn" autocomplete="current-password" required>
                        <button class="auth-password-toggle" type="button" data-password-toggle="password"
                                aria-label="Hiện mật khẩu" aria-pressed="false">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn auth-submit w-100">
                    Đăng nhập <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <p class="auth-switch">Chưa có tài khoản? <a href="<?= url('register.php') ?>">Đăng ký ngay</a></p>
            <a class="auth-back-link" href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Quay về trang chủ</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

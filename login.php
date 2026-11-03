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

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center">Đăng nhập</h4>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= old('email') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    Chưa có tài khoản? <a href="<?= url('register.php') ?>">Đăng ký</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

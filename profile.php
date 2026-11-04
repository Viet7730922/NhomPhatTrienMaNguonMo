<?php
/**
 * Thông tin cá nhân chi tiết (mọi vai trò: admin / giảng viên / sinh viên đều có trang này cho CHÍNH MÌNH).
 *
 * Luồng của ô Tỉnh/thành -> Xã/phường:
 *   chọn tỉnh -> form tự gửi (POST, KHÔNG có nút "save") -> server gọi API lấy xã/phường của tỉnh đó
 *   -> vẽ lại trang giữ nguyên mọi thứ người dùng đã gõ -> chọn xong bấm "Lưu thay đổi" (POST có "save").
 * Server luôn kiểm tra lại mã tỉnh/xã với danh sách từ API, không tin giá trị trình duyệt gửi lên.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/ttp.php';
requireLogin();

$userId = (int) currentUser()['id'];            // hồ sơ của ai luôn lấy từ session, KHÔNG nhận từ form/URL

$stmt = db()->prepare('SELECT full_name, email, role, created_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$account = $stmt->fetch();
$saved   = getUserProfile($userId);             // dữ liệu đang lưu trong CSDL

$errors   = [];
$warnings = [];

$posted = isPost();
if ($posted && !csrfVerify()) {
    $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    $posted   = false;                          // bỏ qua dữ liệu gửi lên, hiện lại dữ liệu đã lưu
}
$isSave = $posted && isset($_POST['save']);

// ---- Giá trị hiển thị trong form: vừa gửi lên (POST) hoặc đã lưu (lần đầu mở trang) ----
if ($posted) {
    $fullName     = post_str('full_name');
    $phone        = post_str('phone');
    $birthDate    = post_str('birth_date');
    $gender       = post_str('gender');
    $provinceCode = post_str('province_code');
    $wardCode     = post_str('ward_code');
    $addressLine  = post_str('address_line');
    $prevProvince = post_str('prev_province');  // tỉnh ở lần gửi trước, để biết người dùng có vừa đổi tỉnh không
} else {
    $fullName     = (string) $account['full_name'];
    $phone        = $saved['phone'];
    $birthDate    = $saved['birth_date'];
    $gender       = $saved['gender'];
    $provinceCode = $saved['province_code'];
    $wardCode     = $saved['ward_code'];
    $addressLine  = $saved['address_line'];
    $prevProvince = $provinceCode;
}

// ---- Tỉnh/thành: đối chiếu với danh sách từ API ----
$provinces       = ttp_provinces();
$addressEditable = (bool) $provinces;           // API lỗi + chưa có cache -> tạm khóa phần địa chỉ
$province        = null;

if (!$addressEditable) {
    $warnings[] = 'Không tải được danh sách tỉnh/thành lúc này nên tạm thời chưa sửa được địa chỉ. Các thông tin khác vẫn lưu được.';
} elseif ($provinceCode !== '') {
    $province = ttp_find($provinces, $provinceCode);
    if (!$province) {
        if ($posted) {
            $errors[] = 'Tỉnh/thành không hợp lệ.';
        }
        $provinceCode = '';
    }
}

// Đổi tỉnh thì bỏ xã/phường đã chọn (xã/phường cũ không thuộc tỉnh mới)
if ($provinceCode !== $prevProvince) {
    $wardCode = '';
}

// ---- Xã/phường của tỉnh đã chọn, rồi kiểm tra xã/phường thuộc đúng tỉnh đó ----
$wards = $province ? ttp_wards($provinceCode) : [];
if ($province && !$wards) {
    $warnings[] = 'Không tải được danh sách xã/phường của tỉnh này. Vui lòng tải lại trang sau ít phút.';
}

$ward = null;
if ($wardCode !== '' && $wards) {
    $ward = ttp_find($wards, $wardCode);
    if (!$ward) {
        if ($posted) {
            $errors[] = 'Xã/phường không hợp lệ hoặc không thuộc tỉnh đã chọn.';
        }
        $wardCode = '';
    }
}

// ---- Bấm "Lưu thay đổi": kiểm tra từng trường ----
if ($isSave) {
    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Họ và tên không được để trống và tối đa 100 ký tự.';
    }

    if ($phone !== '') {
        $normalized = normalizeVnPhone($phone);
        if ($normalized === null) {
            $errors[] = 'Số điện thoại không hợp lệ (số di động 10 chữ số, ví dụ 0912 345 678).';
        } else {
            $phone = $normalized;
        }
    }

    if ($birthDate !== '' && !isValidBirthDate($birthDate)) {
        $errors[] = 'Ngày sinh không hợp lệ (không được ở tương lai).';
    }

    if ($gender !== '' && !isset(PROFILE_GENDERS[$gender])) {
        $errors[] = 'Giới tính không hợp lệ.';
    }

    if ($addressEditable) {
        if (!$province) {
            if ($addressLine !== '') {
                $errors[] = 'Vui lòng chọn tỉnh/thành cho địa chỉ.';
            }
        } elseif (!$ward) {
            $errors[] = 'Vui lòng chọn xã/phường.';
        }
        if (mb_strlen($addressLine) > 255) {
            $errors[] = 'Địa chỉ chi tiết tối đa 255 ký tự.';
        }
    }

    if (!$errors) {
        // Tên tỉnh/xã lấy từ danh sách API (đã kiểm tra), KHÔNG lấy từ form
        if ($addressEditable) {
            $addr = [
                'province_code' => $province ? $province['code'] : '',
                'province_name' => $province ? ttp_label($province) : '',
                'ward_code'     => $ward ? $ward['code'] : '',
                'ward_name'     => $ward ? ttp_label($ward) : '',
                'address_line'  => $addressLine,
            ];
        } else {
            $addr = array_intersect_key($saved, array_flip(['province_code', 'province_name', 'ward_code', 'ward_name', 'address_line']));
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $userId]);
            // REPLACE: chưa có hồ sơ thì thêm, có rồi thì ghi đè (mỗi người 1 dòng, khóa chính = user_id)
            $pdo->prepare(
                'REPLACE INTO user_profiles
                    (user_id, phone, birth_date, gender, province_code, province_name, ward_code, ward_name, address_line)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $userId,
                nullIfEmpty($phone),
                nullIfEmpty($birthDate),
                nullIfEmpty($gender),
                nullIfEmpty($addr['province_code']),
                nullIfEmpty($addr['province_name']),
                nullIfEmpty($addr['ward_code']),
                nullIfEmpty($addr['ward_name']),
                nullIfEmpty($addr['address_line']),
            ]);
            $pdo->commit();

            $_SESSION['user']['full_name'] = $fullName;      // để tên trên thanh menu đổi ngay
            flash('success', 'Đã lưu thông tin cá nhân.');
            redirect(url('profile.php'));                    // Post/Redirect/Get: F5 không gửi lại form
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = DEBUG ? 'Lỗi: ' . $ex->getMessage() : 'Không thể lưu thông tin, vui lòng thử lại.';
        }
    }
}

$roleNames  = ['admin' => 'Quản trị viên', 'teacher' => 'Giảng viên', 'student' => 'Sinh viên'];
$completion = profileCompletion([
    'phone' => $saved['phone'], 'birth_date' => $saved['birth_date'], 'gender' => $saved['gender'],
    'province_code' => $saved['province_code'], 'ward_code' => $saved['ward_code'], 'address_line' => $saved['address_line'],
]);

$pageTitle = 'Thông tin cá nhân';
require __DIR__ . '/includes/header.php';
?>

<h3 class="mb-4">Thông tin cá nhân</h3>

<div class="row g-4">
    <!-- Cột trái: thông tin tài khoản (chỉ xem) -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 4rem"></i>
                    <div class="fw-bold fs-5"><?= e($account['full_name']) ?></div>
                    <span class="badge text-bg-primary"><?= e($roleNames[$account['role']] ?? $account['role']) ?></span>
                </div>
                <dl class="row small mb-3">
                    <dt class="col-4">Email</dt>
                    <dd class="col-8 text-break"><?= e($account['email']) ?></dd>
                    <dt class="col-4">Tham gia</dt>
                    <dd class="col-8"><?= e(date('d/m/Y', strtotime($account['created_at']))) ?></dd>
                </dl>
                <div class="small mb-1">Hồ sơ đã điền: <strong><?= $completion ?>%</strong></div>
                <div class="progress" style="height: 8px">
                    <div class="progress-bar" role="progressbar" style="width: <?= $completion ?>%"></div>
                </div>
                <div class="form-text">Email là tên đăng nhập nên không đổi ở đây.</div>
            </div>
        </div>
    </div>

    <!-- Cột phải: form -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <?php foreach ($warnings as $warn): ?>
                    <div class="alert alert-warning py-2"><?= e($warn) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="prev_province" value="<?= e($provinceCode) ?>">

                    <h5 class="mb-3">Thông tin chung</h5>
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100"
                               value="<?= e($fullName) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="phone">Số điện thoại</label>
                            <input type="tel" class="form-control" id="phone" name="phone" maxlength="20"
                                   placeholder="0912 345 678" value="<?= e($phone) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="birth_date">Ngày sinh</label>
                            <input type="date" class="form-control" id="birth_date" name="birth_date"
                                   min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($birthDate) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="gender">Giới tính</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">-- Không chọn --</option>
                            <?php foreach (PROFILE_GENDERS as $val => $label): ?>
                                <option value="<?= e($val) ?>"<?= $gender === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h5 class="mb-3">Địa chỉ</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="province_code">Tỉnh/Thành phố</label>
                            <?php render_select('province_code', $provinces, $provinceCode, '-- Chọn tỉnh/thành --', true, !$addressEditable); ?>
                            <noscript><button type="submit" name="refresh" value="1" class="btn btn-sm btn-outline-secondary mt-2">Tải xã/phường</button></noscript>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="ward_code">Xã/Phường</label>
                            <?php render_select('ward_code', $wards, $wardCode, '-- Chọn xã/phường --', false, !$wards); ?>
                        </div>
                    </div>

                    <?php if ($addressEditable): ?>
                        <div class="mb-4">
                            <label class="form-label" for="address_line">Số nhà, tên đường</label>
                            <input type="text" class="form-control" id="address_line" name="address_line" maxlength="255"
                                   value="<?= e($addressLine) ?>">
                        </div>
                    <?php elseif ($saved['province_name'] !== ''): ?>
                        <p class="mb-4"><span class="text-muted">Địa chỉ đang lưu:</span>
                            <?= e(trim($saved['address_line'] . ', ' . $saved['ward_name'] . ', ' . $saved['province_name'], ', ')) ?></p>
                    <?php endif; ?>

                    <button type="submit" name="save" value="1" class="btn btn-primary">Lưu thay đổi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Hàm cho trang Thông tin cá nhân (profile.php). Cần require auth.php trước.
 */

const PROFILE_GENDERS = ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'];

/** Các cột của bảng user_profiles mà form quản lý. */
const PROFILE_FIELDS = ['phone', 'birth_date', 'gender', 'province_code', 'province_name', 'ward_code', 'ward_name', 'address_line'];

/**
 * Lấy hồ sơ của 1 người dùng. LUÔN trả đủ khóa, người dùng chưa có hồ sơ thì mọi giá trị là chuỗi rỗng ''
 * (để form không phải kiểm tra isset từng ô).
 */
function getUserProfile(int $userId): array
{
    try {
        $stmt = db()->prepare('SELECT ' . implode(', ', PROFILE_FIELDS) . ' FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
    } catch (PDOException $ex) {
        if ($ex->getCode() === '42S02') {        // 42S02 = bảng không tồn tại
            exit('Chưa có bảng user_profiles. Hãy import file database/migrate_profile.sql vào phpMyAdmin.');
        }
        throw $ex;
    }

    $profile = [];
    foreach (PROFILE_FIELDS as $f) {
        $profile[$f] = (string) ($row[$f] ?? '');
    }
    return $profile;
}

/**
 * Chuẩn hóa số điện thoại di động Việt Nam về dạng 0xxxxxxxxx.
 * Chấp nhận: 0912 345 678, 091.234.5678, 091-234-5678, +84912345678, 84912345678.
 * Trả về null nếu không phải số di động hợp lệ (10 số, đầu 03/05/07/08/09).
 */
function normalizeVnPhone(string $raw): ?string
{
    $p = preg_replace('/[\s.\-()]/', '', $raw);          // bỏ khoảng trắng, dấu chấm, gạch, ngoặc

    if (strncmp($p, '+84', 3) === 0) {
        $p = '0' . substr($p, 3);                        // +84912... -> 0912...
    } elseif (strncmp($p, '84', 2) === 0 && strlen($p) === 11) {
        $p = '0' . substr($p, 2);                        // 84912... -> 0912...
    }

    return preg_match('/^0[35789]\d{8}$/', $p) ? $p : null;
}

/**
 * Kiểm tra ngày sinh dạng Y-m-d (giá trị của <input type="date">): đúng định dạng, có thật
 * (không có 2005-02-31), không ở tương lai và không trước năm 1900.
 */
function isValidBirthDate(string $value): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) {
        return false;                                    // sai định dạng hoặc ngày không có thật
    }
    return $d <= new DateTime('today') && (int) $d->format('Y') >= 1900;
}

/** Phần trăm hồ sơ đã điền (6 mục: SĐT, ngày sinh, giới tính, tỉnh, xã/phường, địa chỉ chi tiết). */
function profileCompletion(array $profile): int
{
    $keys   = ['phone', 'birth_date', 'gender', 'province_code', 'ward_code', 'address_line'];
    $filled = 0;
    foreach ($keys as $k) {
        if (($profile[$k] ?? '') !== '') {
            $filled++;
        }
    }
    return (int) round($filled / count($keys) * 100);
}

/** Chuỗi rỗng -> null để lưu NULL vào CSDL. */
function nullIfEmpty(string $value): ?string
{
    return $value === '' ? null : $value;
}

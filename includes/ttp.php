<?php
/**
 * Thư viện nhỏ gọi API địa chỉ hành chính https://tinhthanhpho.com
 * (không cần key, cấu trúc 2 cấp từ 1/7/2025: tỉnh/thành -> xã/phường).
 *
 * Dùng:  require_once __DIR__ . '/includes/ttp.php';
 *        $provinces = ttp_provinces();            // [['code'=>'79','type'=>'Thành phố','name'=>'Hồ Chí Minh'], ...]
 *        $wards     = ttp_wards('79');            // danh sách xã/phường của tỉnh mã 79
 *        render_select('province_code', $provinces, $selected, '-- Chọn tỉnh --');
 */

require_once __DIR__ . '/functions.php';   // e() chống XSS

if (!defined('TTP_API_BASE')) {
    define('TTP_API_BASE', 'https://tinhthanhpho.com/api/v1');
}
if (!defined('TTP_CACHE_TTL')) {
    define('TTP_CACHE_TTL', 86400);      // danh sách hành chính ít đổi nên cache 24 giờ
}

// ---------------------------------------------------------------
// Hàm tiện ích chung
// ---------------------------------------------------------------

if (!function_exists('post_str')) {
    /** Đọc $_POST an toàn: luôn trả về string đã trim (thiếu key hoặc gửi dạng mảng name[]=... thì trả ''). */
    function post_str(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }
}

// ---------------------------------------------------------------
// Gọi API
// ---------------------------------------------------------------

/**
 * Gọi GET tới API, trả về toàn bộ JSON đã giải mã.
 * Lỗi mạng, HTTP khác 200 hoặc JSON sai dạng => trả [] (không ném lỗi để trang vẫn mở được).
 */
function ttp_get(string $path, array $query = []): array
{
    $url = TTP_API_BASE . $path . ($query ? '?' . http_build_query($query) : '');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false || $status !== 200) {
        error_log('[ttp] gọi ' . $url . ' lỗi: ' . ($body === false ? curl_error($ch) : 'HTTP ' . $status));
        return [];
    }
    $json = json_decode($body, true);
    return (is_array($json) && !empty($json['success'])) ? $json : [];
}

/**
 * Lấy TOÀN BỘ các trang của một danh sách, trả về [['code'=>..., 'type'=>..., 'name'=>...], ...].
 * API mặc định chỉ trả 20 dòng/trang (34 tỉnh, có tỉnh hơn 100 phường/xã) nên phải lặp qua các trang
 * cho đến khi đủ metadata.total. Nếu không lấy đủ thì coi như lỗi (không trả danh sách thiếu).
 * Thứ tự ưu tiên: cache còn hạn -> gọi API -> cache cũ (khi API lỗi) -> [].
 */
function ttp_get_all(string $path, array $query = []): array
{
    $cacheFile = sys_get_temp_dir() . '/ttp_' . md5($path . '?' . http_build_query($query)) . '.json';
    $readCache = function (bool $ignoreTtl) use ($cacheFile): ?array {
        if (!is_file($cacheFile) || (!$ignoreTtl && time() - (int) filemtime($cacheFile) > TTP_CACHE_TTL)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($cacheFile), true);
        return is_array($data) ? $data : null;
    };

    if (($cached = $readCache(false)) !== null) {
        return $cached;
    }

    $items    = [];
    $complete = false;
    for ($page = 1; $page <= 20; $page++) {              // chặn vòng lặp vô hạn nếu API trả sai
        $res = ttp_get($path, $query + ['limit' => 100, 'page' => $page]);
        if (!isset($res['data']) || !is_array($res['data'])) {
            break;
        }
        foreach ($res['data'] as $it) {
            if (isset($it['code'], $it['name'])) {
                $code         = (string) $it['code'];
                $items[$code] = ['code' => $code, 'type' => (string) ($it['type'] ?? ''), 'name' => (string) $it['name']];
            }
        }
        $total = (int) ($res['metadata']['total'] ?? -1);
        if ($total >= 0 && count($items) >= $total) {
            $complete = true;
            break;
        }
        if (!$res['data']) {
            break;                                       // trang rỗng nhưng chưa đủ: API trả thiếu
        }
    }

    if ($complete) {
        $items = array_values($items);
        @file_put_contents($cacheFile, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $items;
    }
    return $readCache(true) ?? [];
}

/** Danh sách tỉnh/thành. */
function ttp_provinces(): array
{
    return ttp_get_all('/new-provinces');
}

/**
 * Danh sách xã/phường/đặc khu của một tỉnh.
 * $provinceCode PHẢI là mã đã kiểm tra bằng ttp_find() (không đưa thẳng dữ liệu người dùng vào đường dẫn).
 */
function ttp_wards(string $provinceCode): array
{
    return ttp_get_all('/new-provinces/' . rawurlencode($provinceCode) . '/wards');
}

/** Tìm một mục theo mã trong danh sách; null nếu không có. Dùng để kiểm tra lại giá trị người dùng gửi lên. */
function ttp_find(array $items, string $code): ?array
{
    foreach ($items as $it) {
        if ((string) $it['code'] === $code) {
            return $it;
        }
    }
    return null;
}

/** Nhãn hiển thị: "Phường An Đông", "Thành phố Hồ Chí Minh". */
function ttp_label(array $item): string
{
    return trim(($item['type'] ?? '') . ' ' . ($item['name'] ?? ''));
}

// ---------------------------------------------------------------
// Vẽ giao diện
// ---------------------------------------------------------------

/**
 * Vẽ một <select> từ danh sách [{code, type, name}, ...].
 * $autoSubmit: tự gửi form khi đổi lựa chọn (dùng cho ô tỉnh để tải danh sách phường/xã).
 * $disabled:   khóa ô chọn (ô phường/xã khi chưa chọn tỉnh).
 */
function render_select(string $name, array $items, string $selected, string $placeholder,
                       bool $autoSubmit = false, bool $disabled = false): void
{
    echo '<select class="form-select" name="' . e($name) . '" id="' . e($name) . '"'
       . ($autoSubmit ? ' onchange="this.form.submit()"' : '')
       . ($disabled ? ' disabled' : '') . '>';
    echo '<option value="">' . e($placeholder) . '</option>';
    foreach ($items as $it) {
        $code = (string) $it['code'];
        echo '<option value="' . e($code) . '"' . ($code === $selected ? ' selected' : '') . '>'
           . e(ttp_label($it)) . '</option>';
    }
    echo '</select>';
}

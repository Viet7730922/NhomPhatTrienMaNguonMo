-- =====================================================
-- Thông tin cá nhân chi tiết của người dùng (trang profile.php)
-- Chạy file này trong phpMyAdmin (chọn database elearning -> tab SQL -> dán -> Thực hiện).
-- An toàn khi chạy nhiều lần (CREATE TABLE IF NOT EXISTS), không ảnh hưởng dữ liệu cũ.
--
-- Quan hệ: users 1 - 0..1 user_profiles  (mỗi người dùng có tối đa 1 hồ sơ, khóa chính = user_id)
-- Lưu cả MÃ lẫn TÊN tỉnh/xã: tên để hiển thị ngay không cần gọi API, mã để đổ lại vào ô chọn.
--
-- LƯU Ý khi import lại elearning.sql từ đầu: phải xóa bảng này TRƯỚC khi xóa bảng users
--   DROP TABLE IF EXISTS user_profiles;
-- (vì user_profiles tham chiếu users bằng khóa ngoại).
-- =====================================================

CREATE TABLE IF NOT EXISTS user_profiles (
  user_id       INT UNSIGNED NOT NULL PRIMARY KEY,
  phone         VARCHAR(15)  DEFAULT NULL,               -- số di động đã chuẩn hóa: 0912345678
  birth_date    DATE         DEFAULT NULL,
  gender        ENUM('male','female','other') DEFAULT NULL,
  province_code VARCHAR(10)  DEFAULT NULL,               -- mã tỉnh/thành từ API tinhthanhpho.com
  province_name VARCHAR(100) DEFAULT NULL,               -- vd: Thành phố Hồ Chí Minh
  ward_code     VARCHAR(10)  DEFAULT NULL,               -- mã xã/phường/đặc khu
  ward_name     VARCHAR(150) DEFAULT NULL,               -- vd: Phường Bến Nghé
  address_line  VARCHAR(255) DEFAULT NULL,               -- số nhà, tên đường
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MIGRATE BƯỚC 3B: bảng lưu nhiều file cho mỗi bài nộp
-- Dành cho máy ĐÃ import elearning.sql cũ. Chạy 1 lần trong phpMyAdmin (tab SQL).
-- Máy mới import elearning.sql bản mới thì không cần chạy file này.
-- =====================================================
USE elearning;

CREATE TABLE IF NOT EXISTS submission_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,              -- tên file gốc học viên nộp (vd: caesar.cpp)
  stored_name   VARCHAR(100) NOT NULL,              -- tên ngẫu nhiên trên đĩa, không có đuôi
  file_size     INT UNSIGNED NOT NULL DEFAULT 0,    -- byte
  uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cột submissions.file_path của bản cũ không còn dùng (giữ nguyên, bỏ qua).

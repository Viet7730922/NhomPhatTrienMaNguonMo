-- =====================================================
-- DỮ LIỆU DEMO CHO BƯỚC 3B (chấm điểm)
-- Yêu cầu: đã import elearning.sql (bản có submission_files hoặc chạy migrate_3b.sql)
--          và đã chạy seed_courses_it.sql (khóa 9 do teacher3@elearning.local phụ trách).
-- Đăng nhập teacher3@elearning.local / 123456 để thử chấm điểm.
-- Chạy lại nhiều lần được (xóa bài tập demo cũ trước khi nạp lại).
-- =====================================================
USE elearning;
SET NAMES utf8mb4;

-- 2 sinh viên giả định (mật khẩu: 123456)
INSERT INTO users (id, full_name, email, password, role) VALUES
(6, 'Phạm Quốc Bảo', 'student2@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'student'),
(7, 'Võ Thị Lan',    'student3@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'student')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Cả 3 sinh viên cùng ghi danh khóa 9 (An toàn và bảo mật thông tin)
INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (3, 9), (6, 9), (7, 9);

-- Bài tập demo (CASCADE xóa kèm bài nộp cũ nếu chạy lại)
DELETE FROM assignments WHERE id = 4;
INSERT INTO assignments (id, course_id, title, description, due_date, max_score) VALUES
(4, 9, 'Bài tập lập trình mã hóa cổ điển',
'1. Tạo chương trình mã hóa, giải mã và phá mã khóa đối xứng Ceasar
2. Tạo chương trình mã hóa, giải mã khóa đối xứng Playfair
3. Tạo chương trình mã hóa và giải mã khóa đối xứng đa bảng Vigenere',
'2026-11-01 23:59:00', 10);

-- Bài nộp: Trần Thị Học (chờ chấm, 2 file), Phạm Quốc Bảo (đã chấm, 1 file), Võ Thị Lan (chưa nộp)
INSERT INTO submissions (assignment_id, student_id, answer_text, submitted_at, score, feedback, graded_at) VALUES
(4, 3, 'Em nộp bài 1 và bài 2, bài 3 em chưa hoàn thành xong ạ.', '2026-10-29 21:30:00', NULL, NULL, NULL),
(4, 6, 'Em nộp cả 3 bài trong 1 file.', '2026-10-30 09:10:00', 8.5,
 'Bài làm tốt, thuật toán đúng. Nên xử lý thêm trường hợp ký tự đặc biệt và khoảng trắng.', '2026-10-30 10:00:00');

INSERT INTO submission_files (submission_id, original_name, stored_name, file_size)
SELECT id, 'caesar.cpp', 'seed_demo_01.dat', 789 FROM submissions WHERE assignment_id = 4 AND student_id = 3;
INSERT INTO submission_files (submission_id, original_name, stored_name, file_size)
SELECT id, 'playfair.py', 'seed_demo_02.dat', 534 FROM submissions WHERE assignment_id = 4 AND student_id = 3;
INSERT INTO submission_files (submission_id, original_name, stored_name, file_size)
SELECT id, 'vigenere.py', 'seed_demo_03.dat', 578 FROM submissions WHERE assignment_id = 4 AND student_id = 6;

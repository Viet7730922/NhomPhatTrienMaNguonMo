-- =====================================================
-- DỮ LIỆU DEMO BƯỚC 6: 1 bài kiểm tra ở khóa 1 (Nhập môn lập trình)
-- Yêu cầu: đã chạy migrate_6.sql và seed_courses_it.sql.
-- Giảng viên: teacher@elearning.local | Sinh viên đã ghi danh: student@elearning.local
-- Chạy lại nhiều lần được.
-- =====================================================
USE elearning;
SET NAMES utf8mb4;

DELETE FROM quizzes WHERE id = 1;   -- CASCADE xóa câu hỏi, đáp án, lần làm bài cũ

INSERT INTO quizzes (id, course_id, title, description, time_limit_min, open_at, close_at,
                     max_attempts, grade_method, max_grade, shuffle, review_mode, is_published) VALUES
(1, 1, 'Kiểm tra giữa kỳ: Nhập môn lập trình',
 'Gồm 2 câu trắc nghiệm và 1 câu tự luận. Thời gian làm bài 20 phút, được làm tối đa 3 lần, tính điểm cao nhất.',
 20, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY),
 3, 'highest', 10, 1, 'immediate', 1);

INSERT INTO quiz_questions (id, quiz_id, type, text, points, sort_order) VALUES
(1, 1, 'single',   'Kiểu dữ liệu nào dùng để lưu số nguyên trong C++?', 1, 1),
(2, 1, 'multiple', 'Những cấu trúc lặp nào dưới đây có trong C++? (chọn tất cả đáp án đúng)', 2, 2),
(3, 1, 'essay',    'Trình bày sự khác nhau giữa vòng lặp while và do-while. Cho một ví dụ.', 2, 3);

INSERT INTO quiz_options (question_id, text, is_correct) VALUES
(1, 'int', 1), (1, 'float', 0), (1, 'string', 0), (1, 'bool', 0),
(2, 'for', 1), (2, 'while', 1), (2, 'do-while', 1), (2, 'repeat-until', 0);

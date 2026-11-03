-- =====================================================
-- MIGRATE BƯỚC 6: bài kiểm tra trực tuyến (trắc nghiệm / tự luận) + chống dò mật khẩu
-- Chạy 1 lần trong phpMyAdmin (tab SQL) trên database elearning đã có từ Bước 3B.
-- Chạy lại nhiều lần được (CREATE TABLE IF NOT EXISTS).
-- =====================================================
USE elearning;
SET NAMES utf8mb4;

-- 1. quizzes: cấu hình bài kiểm tra
CREATE TABLE IF NOT EXISTS quizzes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id      INT UNSIGNED NOT NULL,
  title          VARCHAR(200) NOT NULL,
  description    TEXT,
  time_limit_min SMALLINT UNSIGNED DEFAULT NULL,                 -- NULL = không giới hạn thời gian
  open_at        DATETIME DEFAULT NULL,                          -- NULL = mở ngay
  close_at       DATETIME DEFAULT NULL,                          -- NULL = không đóng
  max_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 1,            -- 0 = không giới hạn số lần
  grade_method   ENUM('highest','average','first','last') NOT NULL DEFAULT 'highest',
  max_grade      DECIMAL(5,2) NOT NULL DEFAULT 10,               -- thang điểm hiển thị (vd 10)
  shuffle        TINYINT(1) NOT NULL DEFAULT 0,                  -- xáo câu hỏi + đáp án
  review_mode    ENUM('never','after_close','immediate') NOT NULL DEFAULT 'after_close',
  is_published   TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. quiz_questions: câu hỏi
CREATE TABLE IF NOT EXISTS quiz_questions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id    INT UNSIGNED NOT NULL,
  type       ENUM('single','multiple','essay') NOT NULL,
  text       TEXT NOT NULL,
  points     DECIMAL(5,2) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. quiz_options: đáp án của câu trắc nghiệm
CREATE TABLE IF NOT EXISTS quiz_options (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  text        VARCHAR(500) NOT NULL,
  is_correct  TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. quiz_attempts: mỗi lần làm bài của sinh viên
CREATE TABLE IF NOT EXISTS quiz_attempts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id        INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  attempt_no     TINYINT UNSIGNED NOT NULL,
  status         ENUM('in_progress','waiting','finished') NOT NULL DEFAULT 'in_progress',
                 -- waiting = đã nộp, còn câu tự luận chờ giảng viên chấm
  started_at     DATETIME NOT NULL,
  deadline_at    DATETIME DEFAULT NULL,                          -- hạn chót của lần làm này (server tự tính)
  finished_at    DATETIME DEFAULT NULL,
  question_order TEXT,                                           -- JSON thứ tự câu hỏi của lần làm này
  max_marks      DECIMAL(6,2) NOT NULL DEFAULT 0,                -- tổng điểm tối đa (chốt lúc bắt đầu)
  score          DECIMAL(6,2) DEFAULT NULL,                      -- NULL = chưa có điểm (đang làm / chờ chấm)
  UNIQUE KEY uq_quiz_student_no (quiz_id, student_id, attempt_no),
  KEY idx_student (student_id),
  FOREIGN KEY (quiz_id)    REFERENCES quizzes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. quiz_answers: câu trả lời trong mỗi lần làm
CREATE TABLE IF NOT EXISTS quiz_answers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id     INT UNSIGNED NOT NULL,
  question_id    INT UNSIGNED NOT NULL,
  option_ids     VARCHAR(255) DEFAULT NULL,                      -- "12,15" (câu trắc nghiệm)
  answer_text    MEDIUMTEXT,                                     -- câu tự luận
  points_awarded DECIMAL(5,2) DEFAULT NULL,                      -- NULL = tự luận chờ chấm
  feedback       TEXT,
  UNIQUE KEY uq_attempt_question (attempt_id, question_id),
  FOREIGN KEY (attempt_id)  REFERENCES quiz_attempts(id)  ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. login_attempts: ghi lần đăng nhập sai để chống dò mật khẩu
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_ip_time (email, ip, attempted_at)
) ENGINE=InnoDB;

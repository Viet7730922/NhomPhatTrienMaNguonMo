-- E-LEARNING LMS - DATABASE SCHEMA
-- Import this file first to create the database and all tables.
-- WARNING: Re-importing drops and recreates the existing tables and data.
CREATE DATABASE IF NOT EXISTS elearning
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE elearning;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS quiz_options;
DROP TABLE IF EXISTS quiz_questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS submission_files;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- PHẦN 1: CẤU TRÚC BẢNG
-- =====================================================

-- 1. users: người dùng (admin / teacher / student)
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,              -- lưu hash bằng password_hash()
  role          ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  avatar        VARCHAR(255) DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = bị khóa
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_role_active (role, is_active)
) ENGINE=InnoDB;

-- 2. categories: danh mục khóa học
CREATE TABLE categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. courses: khóa học (do giảng viên tạo)
CREATE TABLE courses (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id    INT UNSIGNED NOT NULL,
  category_id   INT UNSIGNED DEFAULT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  thumbnail     VARCHAR(255) DEFAULT NULL,
  status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status_created (status, created_at),
  FOREIGN KEY (teacher_id)  REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. lessons: bài học trong khóa học
CREATE TABLE lessons (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  content       TEXT,                               -- nội dung bài giảng
  video_url     VARCHAR(255) DEFAULT NULL,          -- link YouTube nhúng
  file_path     VARCHAR(255) DEFAULT NULL,          -- tài liệu đính kèm (trong uploads/lessons/)
  sort_order    INT NOT NULL DEFAULT 0,             -- thứ tự bài học
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_course_sort (course_id, sort_order),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. enrollments: sinh viên ghi danh khóa học
CREATE TABLE enrollments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    INT UNSIGNED NOT NULL,
  course_id     INT UNSIGNED NOT NULL,
  enrolled_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_course (student_id, course_id),   -- 1 sinh viên chỉ ghi danh 1 lần
  KEY idx_course (course_id),
  FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. assignments: bài tập do giảng viên giao
CREATE TABLE assignments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  due_date      DATETIME DEFAULT NULL,
  max_score     DECIMAL(5,2) NOT NULL DEFAULT 10,
  submission_mode ENUM('file','online_text','both') NOT NULL DEFAULT 'both',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_course_due (course_id, due_date),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. submissions: bài nộp của sinh viên + điểm (đã bỏ cột file_path cũ, dùng submission_files)
CREATE TABLE submissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  answer_text   TEXT,
  answer_format ENUM('plain','html') NOT NULL DEFAULT 'plain',
  submitted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  score         DECIMAL(5,2) DEFAULT NULL,          -- NULL = chưa chấm
  feedback      TEXT,
  graded_at     DATETIME DEFAULT NULL,
  UNIQUE KEY uq_assignment_student (assignment_id, student_id),
  KEY idx_score (score),
  KEY idx_student (student_id),
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id)    REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. submission_files: các file trong một bài nộp (nhiều file mỗi bài)
CREATE TABLE submission_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,              -- tên file gốc sinh viên nộp
  stored_name   VARCHAR(100) NOT NULL,              -- tên ngẫu nhiên trên đĩa, không có đuôi
  file_size     INT UNSIGNED NOT NULL DEFAULT 0,    -- byte
  uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_submission (submission_id),
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. quizzes: cấu hình bài kiểm tra
CREATE TABLE quizzes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id      INT UNSIGNED NOT NULL,
  title          VARCHAR(200) NOT NULL,
  description    TEXT,
  time_limit_min SMALLINT UNSIGNED DEFAULT NULL,                 -- NULL = không giới hạn thời gian
  open_at        DATETIME DEFAULT NULL,                          -- NULL = mở ngay
  close_at       DATETIME DEFAULT NULL,                          -- NULL = không đóng
  max_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 1,            -- 0 = không giới hạn số lần
  grade_method   ENUM('highest','average','first','last') NOT NULL DEFAULT 'highest',
  max_grade      DECIMAL(5,2) NOT NULL DEFAULT 10,               -- thang điểm hiển thị
  shuffle        TINYINT(1) NOT NULL DEFAULT 0,                  -- xáo câu hỏi + đáp án
  review_mode    ENUM('never','after_close','immediate') NOT NULL DEFAULT 'after_close',
  is_published   TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_course_pub (course_id, is_published),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. quiz_questions: câu hỏi
CREATE TABLE quiz_questions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id    INT UNSIGNED NOT NULL,
  type       ENUM('single','multiple','essay') NOT NULL,
  text       TEXT NOT NULL,
  points     DECIMAL(5,2) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  KEY idx_quiz_sort (quiz_id, sort_order),
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. quiz_options: đáp án của câu trắc nghiệm
CREATE TABLE quiz_options (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  text        VARCHAR(500) NOT NULL,
  is_correct  TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_question (question_id),
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. quiz_attempts: mỗi lần làm bài của sinh viên
CREATE TABLE quiz_attempts (
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

-- 13. quiz_answers: câu trả lời trong mỗi lần làm
CREATE TABLE quiz_answers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id     INT UNSIGNED NOT NULL,
  question_id    INT UNSIGNED NOT NULL,
  option_ids     VARCHAR(255) DEFAULT NULL,                      -- "12,15" (câu trắc nghiệm)
  answer_text    MEDIUMTEXT,                                     -- câu tự luận
  points_awarded DECIMAL(5,2) DEFAULT NULL,                      -- NULL = tự luận chờ chấm
  feedback       TEXT,
  UNIQUE KEY uq_attempt_question (attempt_id, question_id),
  KEY idx_question (question_id),
  FOREIGN KEY (attempt_id)  REFERENCES quiz_attempts(id)  ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. login_attempts: ghi lần đăng nhập sai để chống dò mật khẩu
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_ip_time (email, ip, attempted_at)
) ENGINE=InnoDB;

-- =====================================================

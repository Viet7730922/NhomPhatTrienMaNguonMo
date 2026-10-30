-- =====================================================
-- E-LEARNING DATABASE
-- Import: phpMyAdmin > Import, hoặc: mysql -u root -p < elearning.sql
-- Tài khoản demo (mật khẩu chung: 123456)
--   admin@elearning.local    (admin)
--   teacher@elearning.local  (teacher)
--   student@elearning.local  (student)
-- =====================================================

CREATE DATABASE IF NOT EXISTS elearning
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE elearning;

-- Xóa bảng cũ (theo thứ tự khóa ngoại) để import lại nhiều lần
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- -----------------------------------------------------
-- 1. users: người dùng (admin / teacher / student)
-- -----------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,              -- lưu hash bằng password_hash()
  role          ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  avatar        VARCHAR(255) DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = bị khóa
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 2. categories: danh mục khóa học
-- -----------------------------------------------------
CREATE TABLE categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 3. courses: khóa học (do giảng viên tạo)
-- -----------------------------------------------------
CREATE TABLE courses (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id    INT UNSIGNED NOT NULL,
  category_id   INT UNSIGNED DEFAULT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  thumbnail     VARCHAR(255) DEFAULT NULL,
  status        ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id)  REFERENCES users(id)      ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 4. lessons: bài học trong khóa học
-- -----------------------------------------------------
CREATE TABLE lessons (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  content       TEXT,                               -- nội dung bài giảng
  video_url     VARCHAR(255) DEFAULT NULL,          -- link YouTube nhúng
  file_path     VARCHAR(255) DEFAULT NULL,          -- tài liệu đính kèm (trong uploads/)
  sort_order    INT NOT NULL DEFAULT 0,             -- thứ tự bài học
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 5. enrollments: học viên ghi danh khóa học
-- -----------------------------------------------------
CREATE TABLE enrollments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    INT UNSIGNED NOT NULL,
  course_id     INT UNSIGNED NOT NULL,
  enrolled_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_course (student_id, course_id),   -- 1 học viên chỉ ghi danh 1 lần
  FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 6. assignments: bài tập do giảng viên giao
-- -----------------------------------------------------
CREATE TABLE assignments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  due_date      DATETIME DEFAULT NULL,
  max_score     DECIMAL(5,2) NOT NULL DEFAULT 10,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- 7. submissions: bài nộp của học viên + điểm
-- -----------------------------------------------------
CREATE TABLE submissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) DEFAULT NULL,
  answer_text   TEXT,
  submitted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  score         DECIMAL(5,2) DEFAULT NULL,          -- NULL = chưa chấm
  feedback      TEXT,
  graded_at     DATETIME DEFAULT NULL,
  UNIQUE KEY uq_assignment_student (assignment_id, student_id),
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id)    REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB;


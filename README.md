# E-Learning Management System

Website học trực tuyến (LMS) dành cho quản lý khóa học, bài giảng, bài tập, quiz và tiến độ học tập của sinh viên.

## 1. Giới thiệu dự án

Dự án này là một hệ thống học tập trực tuyến theo mô hình quản lý khóa học và người dùng với 3 vai trò chính:

- Admin: quản lý người dùng, danh mục khóa học, kiểm tra hệ thống
- Teacher/Giảng viên: tạo khóa học, bài học, bài tập, quiz, chấm điểm
- Student/Sinh viên: đăng ký khóa học, học bài, nộp bài, làm quiz

Project được xây dựng bằng PHP + MySQL theo mô hình MVC đơn giản, kết hợp HTML/CSS/JavaScript và Bootstrap để tạo giao diện.

## 2. Công nghệ sử dụng

- PHP 8+
- MySQL / MariaDB
- PDO (database access)
- HTML5 / CSS3 / JavaScript
- Bootstrap 5
- XAMPP / Apache

## 3. Cấu trúc thư mục dự án

```text
NhomPhatTrienMaNguonMo/
├─ admin/                     # Trang quản trị admin
│  ├─ categories.php
│  ├─ courses.php
│  ├─ index.php
│  ├─ user_form.php
│  └─ users.php
├─ assets/                    # File CSS/JS giao diện
│  ├─ css/
│  └─ js/
├─ config/                    # Cấu hình ứng dụng và database
│  ├─ config.php
│  ├─ database.php
│  └─ mail.example.php
├─ database/                  # Schema và dữ liệu mẫu
│  ├─ database.sql            # Tạo database và các bảng
│  └─ seed_data.sql           # Dữ liệu mẫu
├─ includes/                  # Các thành phần dùng chung
│  ├─ assignments.php
│  ├─ auth.php
│  ├─ courses.php
│  ├─ footer.php
│  ├─ functions.php
│  ├─ header.php
│  ├─ mail.php
│  ├─ notify.php
│  ├─ profile.php
│  ├─ quiz.php
│  ├─ security.php
│  ├─ student.php
│  ├─ teacher.php
│  ├─ ttp.php
│  └─ upload.php
├─ student/                   # Giao diện sinh viên
│  ├─ assignment.php
│  ├─ assignments.php
│  ├─ download.php
│  ├─ enroll.php
│  ├─ index.php
│  ├─ quiz.php
│  ├─ quiz_attempt.php
│  ├─ quiz_review.php
│  ├─ quizzes.php
│  └─ ...
├─ teacher/                   # Giao diện giảng viên
│  ├─ assignment_delete.php
│  ├─ assignment_form.php
│  ├─ assignments.php
│  ├─ course_delete.php
│  ├─ course_form.php
│  ├─ download.php
│  ├─ grade.php
│  ├─ index.php
│  ├─ lesson_form.php
│  ├─ lessons.php
│  ├─ question_form.php
│  ├─ quiz_attempts.php
│  ├─ quiz_form.php
│  ├─ quiz_grade.php
│  ├─ quiz_questions.php
│  ├─ quizzes.php
│  ├─ submissions.php
│  └─ ...
├─ uploads/                   # File đính kèm bài giảng / bài nộp
│  ├─ lessons/
│  └─ submissions/
├─ index.php                  # Trang khóa học công khai
├─ course.php                 # Chi tiết khóa học
├─ lesson.php                 # Xem bài học
├─ lesson_file.php            # Tải file bài học
├─ login.php                  # Đăng nhập
├─ logout.php                 # Đăng xuất
├─ profile.php                # Hồ sơ người dùng
├─ register.php               # Đăng ký tài khoản
├─ LICENSE
├─ README.md
├─ TranVinh.txt
├─ VietVo.txt
└─ ...
```

## 4. Tính năng chính

- Quản lý tài khoản người dùng theo phân quyền
- Tạo và quản lý danh mục khóa học
- Tạo khóa học, bài học, tài liệu đính kèm
- Đăng ký khóa học cho sinh viên
- Giao bài tập và nộp bài
- Nhận bài tập bằng file, Online text có định dạng, hoặc cả hai
- Làm bài kiểm tra (quiz) với thời gian và điểm số
- Chấm điểm, xem kết quả và phản hồi
- Theo dõi tiến độ học tập
- Upload file tài liệu và bài nộp

## 5. Cài đặt và chạy dự án

### Bước 1: Cài đặt môi trường

- Cài đặt XAMPP hoặc PHP + MySQL tương đương
- Khởi động Apache và MySQL

### Bước 2: Import database

- Mở phpMyAdmin và import `database/database.sql` để tạo database cùng toàn bộ bảng.
- Tiếp theo import `database/seed_data.sql` để nạp dữ liệu mẫu.
- `database.sql` xóa và tạo lại các bảng, vì vậy không import lại vào database đang có dữ liệu cần giữ.

### Bước 3: Cấu hình ứng dụng

Mở file:

- `config/config.php`
- `config/database.php`

Kiểm tra các thông số sau:

```php
define('BASE_URL', 'http://localhost/elearning');
```

Nếu project của bạn đang chạy trong thư mục khác, ví dụ `NhomPhatTrienMaNguonMo`, hãy cập nhật BASE_URL cho đúng tên thư mục. Ví dụ:

```php
define('BASE_URL', 'http://localhost/NhomPhatTrienMaNguonMo');
```

Database config mặc định trong project:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'elearning');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Bước 4: Chạy ứng dụng

Mở trình duyệt và truy cập:

```text
http://localhost/NhomPhatTrienMaNguonMo
```

hoặc nếu bạn đặt project trong thư mục `elearning`:

```text
http://localhost/elearning
```

## 6. Tài khoản demo

Sau khi import database, các tài khoản demo có sẵn trong file SQL, ví dụ:

- Admin: `admin@elearning.local` / `123456`
- Teacher: `teacher@elearning.local` / `123456`
- Student: `student@elearning.local` / `123456`

## 7. Thông tin nhóm

- Trần Ngọc Phát
- Trần Quang Vinh
- Võ Quốc Việt
- Lớp học phần: SOT366_65.CNTT-1

## 8. Ghi chú

- `database/database.sql` chỉ chứa lệnh tạo cấu trúc database và bảng.
- `database/seed_data.sql` chỉ chứa dữ liệu mẫu; import sau `database.sql`.
- Thư mục `uploads/` chứa tài liệu bài học và sản phẩm nộp bài của sinh viên.
- Khi thay đổi cấu trúc project hoặc đường dẫn lưu file, cần cập nhật tương ứng trong `config/` và các file upload xử lý.

## 9. License

Dự án được cấp phép theo file `LICENSE`.

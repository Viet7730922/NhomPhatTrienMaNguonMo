-- =====================================================
-- DỮ LIỆU MẪU
-- =====================================================
USE elearning;
SET NAMES utf8mb4;

-- Mật khẩu: 123456 (bcrypt)
INSERT INTO users (full_name, email, password, role) VALUES
('Quản trị viên',   'admin@elearning.local',   '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'admin'),
('Nguyễn Văn Giảng','teacher@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'teacher'),
('Trần Thị Học',    'student@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'student');

INSERT INTO categories (name) VALUES
('Lập trình'), ('Thiết kế'), ('Ngoại ngữ');

INSERT INTO courses (teacher_id, category_id, title, description, status) VALUES
(2, 1, 'Lập trình PHP cơ bản',   'Làm quen cú pháp PHP, xử lý form, kết nối MySQL bằng PDO.', 'published'),
(2, 1, 'HTML & CSS từ con số 0', 'Xây dựng giao diện web responsive với HTML5, CSS3.',        'published');

INSERT INTO lessons (course_id, title, content, video_url, sort_order) VALUES
(1, 'Bài 1: Giới thiệu PHP',        'PHP là ngôn ngữ lập trình phía server...', NULL, 1),
(1, 'Bài 2: Biến và kiểu dữ liệu',  'Cách khai báo biến, các kiểu dữ liệu cơ bản...', NULL, 2),
(1, 'Bài 3: Kết nối MySQL với PDO', 'Tạo kết nối, prepared statements...', NULL, 3),
(2, 'Bài 1: Cấu trúc trang HTML',   'Các thẻ cơ bản, cấu trúc một trang web...', NULL, 1);

INSERT INTO enrollments (student_id, course_id) VALUES (3, 1);

INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES
(1, 'Bài tập 1: Form đăng ký', 'Viết form đăng ký bằng PHP và lưu vào MySQL.', DATE_ADD(NOW(), INTERVAL 7 DAY), 10);

-- =====================================================
-- DỮ LIỆU MẪU: KHÓA HỌC NGÀNH CÔNG NGHỆ THÔNG TIN
-- Tên học phần tham khảo theo khung chương trình CNTT thông thường.
-- Mô tả, nội dung bài học là nội dung mẫu, giảng viên là tên giả định.
-- Chạy trong database elearning (phpMyAdmin > SQL).
-- CẢNH BÁO: file này XÓA toàn bộ khóa học, bài học, ghi danh, bài tập,
-- bài nộp và danh mục hiện có rồi nạp lại từ đầu.
-- =====================================================

-- ---------- 1. Dọn dữ liệu cũ (CASCADE xóa kèm lessons, enrollments, assignments, submissions) ----------
DELETE FROM courses;
DELETE FROM categories;
ALTER TABLE courses     AUTO_INCREMENT = 1;
ALTER TABLE categories  AUTO_INCREMENT = 1;
ALTER TABLE lessons     AUTO_INCREMENT = 1;
ALTER TABLE enrollments AUTO_INCREMENT = 1;
ALTER TABLE assignments AUTO_INCREMENT = 1;
ALTER TABLE submissions AUTO_INCREMENT = 1;

-- ---------- 2. Thêm 2 giảng viên giả định (mật khẩu: 123456) ----------
INSERT INTO users (id, full_name, email, password, role) VALUES
(4, 'Trần Minh Khoa', 'teacher2@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'teacher'),
(5, 'Lê Thị Hạnh',    'teacher3@elearning.local', '$2b$12$QOg8GwfG9PPoH2K3DbdR..zUvFpkxWTOer.LRdm/AtFRbrxWe9/Oi', 'teacher')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ---------- 3. Danh mục ----------
INSERT INTO categories (id, name) VALUES
(1, 'Nền tảng lập trình'),
(2, 'Cơ sở dữ liệu & Hệ thống thông tin'),
(3, 'Mạng máy tính & Hệ thống'),
(4, 'Công nghệ phần mềm'),
(5, 'Trí tuệ nhân tạo & Dữ liệu'),
(6, 'Toán & Cơ sở ngành');

-- ---------- 4. Khóa học (teacher_id: 2, 4, 5) ----------
INSERT INTO courses (id, teacher_id, category_id, title, description, status) VALUES
(1,  2, 1, 'Nhập môn lập trình',
 'Làm quen tư duy lập trình: biến, kiểu dữ liệu, cấu trúc điều khiển, hàm, mảng và chuỗi. Thực hành giải các bài toán cơ bản bằng ngôn ngữ C/C++.', 'published'),
(2,  2, 1, 'Cấu trúc dữ liệu và giải thuật',
 'Các cấu trúc dữ liệu thông dụng (danh sách liên kết, ngăn xếp, hàng đợi, cây, đồ thị) và giải thuật sắp xếp, tìm kiếm, đánh giá độ phức tạp.', 'published'),
(3,  4, 1, 'Lập trình hướng đối tượng',
 'Tư duy lập trình hướng đối tượng: lớp, đối tượng, kế thừa, đa hình, đóng gói. Áp dụng vào xây dựng ứng dụng nhỏ.', 'published'),
(4,  4, 1, 'Lập trình Web',
 'Xây dựng website động với HTML, CSS, JavaScript và PHP kết nối MySQL bằng PDO. Quản lý phiên đăng nhập, phân quyền và upload file.', 'published'),
(5,  5, 6, 'Toán rời rạc',
 'Logic mệnh đề, tập hợp, quan hệ, đếm tổ hợp, lý thuyết đồ thị. Nền tảng toán cho các môn giải thuật và mạng.', 'published'),
(6,  5, 6, 'Xác suất thống kê',
 'Biến ngẫu nhiên, phân phối xác suất, ước lượng và kiểm định giả thuyết, ứng dụng trong phân tích dữ liệu.', 'published'),
(7,  2, 3, 'Hệ điều hành',
 'Quản lý tiến trình, bộ nhớ, tập tin và thiết bị vào ra. Đồng bộ hóa tiến trình và tránh tắc nghẽn.', 'published'),
(8,  2, 3, 'Mạng máy tính',
 'Mô hình OSI và TCP/IP, địa chỉ IP, định tuyến, các giao thức tầng ứng dụng như HTTP, DNS, DHCP.', 'published'),
(9,  5, 3, 'An toàn và bảo mật thông tin',
 'Các nguyên lý bảo mật, mã hóa đối xứng và bất đối xứng, xác thực, các dạng tấn công web phổ biến và cách phòng chống.', 'published'),
(10, 4, 2, 'Cơ sở dữ liệu',
 'Mô hình thực thể liên kết, mô hình quan hệ, chuẩn hóa, ngôn ngữ SQL (SELECT, JOIN, GROUP BY), giao dịch và chỉ mục.', 'published'),
(11, 4, 2, 'Phân tích và thiết kế hệ thống thông tin',
 'Khảo sát yêu cầu, mô hình hóa nghiệp vụ, vẽ sơ đồ luồng dữ liệu, sơ đồ UML và thiết kế cơ sở dữ liệu cho hệ thống thông tin.', 'published'),
(12, 2, 4, 'Công nghệ phần mềm',
 'Quy trình phát triển phần mềm (Waterfall, Agile/Scrum), quản lý yêu cầu, thiết kế, kiểm thử và quản lý mã nguồn bằng Git.', 'published'),
(13, 5, 5, 'Trí tuệ nhân tạo',
 'Giới thiệu trí tuệ nhân tạo: giải quyết vấn đề bằng tìm kiếm, biểu diễn tri thức, suy diễn và các ứng dụng thực tế.', 'published'),
(14, 5, 5, 'Học máy',
 'Học có giám sát và không giám sát: hồi quy, phân loại, phân cụm. Đánh giá mô hình và thực hành với Python.', 'published'),
(15, 5, 5, 'Khai phá dữ liệu',
 'Tiền xử lý dữ liệu, luật kết hợp, phân lớp và phân cụm trên tập dữ liệu lớn. (Bản nháp, chưa công khai)', 'draft');

-- ---------- 5. Bài học (3 bài mỗi khóa) ----------
INSERT INTO lessons (course_id, title, content, sort_order) VALUES
(1, 'Bài 1: Giới thiệu lập trình và thuật toán', 'Khái niệm chương trình, thuật toán, lưu đồ. Cài đặt môi trường và chạy chương trình đầu tiên.', 1),
(1, 'Bài 2: Biến, kiểu dữ liệu và toán tử', 'Các kiểu dữ liệu cơ bản, khai báo biến, nhập xuất, toán tử số học và logic.', 2),
(1, 'Bài 3: Cấu trúc rẽ nhánh và vòng lặp', 'Câu lệnh if-else, switch, vòng lặp for, while, do-while và các bài tập mẫu.', 3),

(2, 'Bài 1: Độ phức tạp thuật toán', 'Ký hiệu Big-O, cách đánh giá thời gian chạy của thuật toán.', 1),
(2, 'Bài 2: Danh sách liên kết, ngăn xếp, hàng đợi', 'Cài đặt và ứng dụng các cấu trúc dữ liệu tuyến tính.', 2),
(2, 'Bài 3: Sắp xếp và tìm kiếm', 'Các thuật toán sắp xếp (chọn, chèn, nhanh, trộn) và tìm kiếm nhị phân.', 3),

(3, 'Bài 1: Lớp và đối tượng', 'Khái niệm lớp, thuộc tính, phương thức, hàm khởi tạo.', 1),
(3, 'Bài 2: Kế thừa và đa hình', 'Lớp cha, lớp con, ghi đè phương thức, lớp trừu tượng và giao diện.', 2),
(3, 'Bài 3: Xử lý ngoại lệ và mẫu thiết kế cơ bản', 'Try-catch, tạo ngoại lệ riêng, giới thiệu mẫu Singleton và Factory.', 3),

(4, 'Bài 1: HTML và CSS nền tảng', 'Cấu trúc trang web, các thẻ cơ bản, bố cục với Flexbox và Bootstrap.', 1),
(4, 'Bài 2: PHP cơ bản và xử lý form', 'Cú pháp PHP, nhận dữ liệu từ form, kiểm tra dữ liệu đầu vào, chống XSS với htmlspecialchars.', 2),
(4, 'Bài 3: Kết nối MySQL bằng PDO', 'Tạo kết nối, prepared statements, thêm, sửa, xóa, truy vấn dữ liệu.', 3),
(4, 'Bài 4: Session, đăng nhập và phân quyền', 'Quản lý phiên, băm mật khẩu bằng password_hash, kiểm tra quyền truy cập.', 4),

(5, 'Bài 1: Logic mệnh đề và tập hợp', 'Mệnh đề, bảng chân trị, các phép toán tập hợp.', 1),
(5, 'Bài 2: Phép đếm và tổ hợp', 'Quy tắc cộng, quy tắc nhân, hoán vị, chỉnh hợp, tổ hợp.', 2),
(5, 'Bài 3: Cơ bản về đồ thị', 'Đồ thị vô hướng, có hướng, đường đi, chu trình, cây khung.', 3),

(6, 'Bài 1: Biến ngẫu nhiên và phân phối xác suất', 'Xác suất, biến ngẫu nhiên rời rạc và liên tục, kỳ vọng, phương sai.', 1),
(6, 'Bài 2: Phân phối chuẩn', 'Đặc điểm và ứng dụng của phân phối chuẩn.', 2),
(6, 'Bài 3: Ước lượng và kiểm định giả thuyết', 'Khoảng tin cậy, kiểm định t, giá trị p.', 3),

(7, 'Bài 1: Tổng quan hệ điều hành', 'Chức năng, cấu trúc, lời gọi hệ thống.', 1),
(7, 'Bài 2: Quản lý tiến trình', 'Tiến trình, luồng, các thuật toán điều độ CPU.', 2),
(7, 'Bài 3: Quản lý bộ nhớ', 'Phân trang, phân đoạn, bộ nhớ ảo.', 3),

(8, 'Bài 1: Mô hình OSI và TCP/IP', 'Các tầng của mô hình mạng và chức năng từng tầng.', 1),
(8, 'Bài 2: Địa chỉ IP và chia mạng con', 'IPv4, mặt nạ mạng, chia mạng con, giới thiệu IPv6.', 2),
(8, 'Bài 3: Giao thức tầng ứng dụng', 'HTTP, DNS, DHCP, SMTP và cách hoạt động.', 3),

(9, 'Bài 1: Nguyên lý an toàn thông tin', 'Ba yếu tố bảo mật: bí mật, toàn vẹn, sẵn sàng. Các loại mối đe dọa.', 1),
(9, 'Bài 2: Mật mã học cơ bản', 'Mã hóa đối xứng, bất đối xứng, hàm băm, chữ ký số.', 2),
(9, 'Bài 3: Bảo mật ứng dụng web', 'SQL Injection, XSS, CSRF và cách phòng chống.', 3),

(10, 'Bài 1: Mô hình thực thể liên kết', 'Thực thể, thuộc tính, mối liên kết, chuyển sang mô hình quan hệ.', 1),
(10, 'Bài 2: Ngôn ngữ SQL cơ bản', 'SELECT, WHERE, ORDER BY, GROUP BY, JOIN.', 2),
(10, 'Bài 3: Chuẩn hóa cơ sở dữ liệu', 'Phụ thuộc hàm, các dạng chuẩn 1NF, 2NF, 3NF.', 3),

(11, 'Bài 1: Khảo sát và thu thập yêu cầu', 'Phỏng vấn, bảng hỏi, mô tả yêu cầu chức năng và phi chức năng.', 1),
(11, 'Bài 2: Mô hình hóa với UML', 'Sơ đồ use case, sơ đồ lớp, sơ đồ tuần tự.', 2),
(11, 'Bài 3: Thiết kế cơ sở dữ liệu cho hệ thống', 'Từ sơ đồ thực thể đến bảng dữ liệu và ràng buộc.', 3),

(12, 'Bài 1: Quy trình phát triển phần mềm', 'Mô hình thác nước, mô hình xoắn ốc, Agile và Scrum.', 1),
(12, 'Bài 2: Quản lý mã nguồn với Git', 'Commit, branch, merge, pull request và quy trình làm việc nhóm.', 2),
(12, 'Bài 3: Kiểm thử phần mềm', 'Kiểm thử hộp đen, hộp trắng, kiểm thử đơn vị và kiểm thử tích hợp.', 3),

(13, 'Bài 1: Tổng quan trí tuệ nhân tạo', 'Lịch sử, các lĩnh vực và ứng dụng của trí tuệ nhân tạo.', 1),
(13, 'Bài 2: Giải quyết vấn đề bằng tìm kiếm', 'Tìm kiếm mù (BFS, DFS) và tìm kiếm có thông tin (A*).', 2),
(13, 'Bài 3: Biểu diễn tri thức và suy diễn', 'Logic vị từ, luật sinh, suy diễn tiến và lùi.', 3),

(14, 'Bài 1: Giới thiệu học máy', 'Học có giám sát, không giám sát, quy trình xây dựng mô hình.', 1),
(14, 'Bài 2: Hồi quy và phân loại', 'Hồi quy tuyến tính, hồi quy logistic, cây quyết định.', 2),
(14, 'Bài 3: Đánh giá mô hình', 'Tập huấn luyện và kiểm tra, quá khớp, độ chính xác, precision và recall.', 3),

(15, 'Bài 1: Tiền xử lý dữ liệu', 'Làm sạch, chuẩn hóa, xử lý giá trị thiếu.', 1),
(15, 'Bài 2: Luật kết hợp', 'Thuật toán Apriori, độ hỗ trợ và độ tin cậy.', 2),
(15, 'Bài 3: Phân cụm', 'Thuật toán K-means và đánh giá chất lượng phân cụm.', 3);

-- ---------- 6. Ghi danh mẫu cho học viên demo (id = 3) ----------
INSERT INTO enrollments (student_id, course_id) VALUES (3, 1), (3, 4), (3, 10);

-- ---------- 7. Bài tập mẫu ----------
INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES
(1, 'Bài tập 1: Tính toán cơ bản', 'Viết chương trình nhập hai số, in ra tổng, hiệu, tích, thương. Nộp file mã nguồn.', DATE_ADD(NOW(), INTERVAL 7 DAY), 10),
(4, 'Bài tập 1: Form đăng ký', 'Viết form đăng ký bằng PHP, kiểm tra dữ liệu và lưu vào MySQL bằng PDO.', DATE_ADD(NOW(), INTERVAL 7 DAY), 10),
(10, 'Bài tập 1: Thiết kế CSDL quản lý thư viện', 'Vẽ sơ đồ thực thể liên kết và viết script SQL tạo bảng cho hệ thống quản lý thư viện.', DATE_ADD(NOW(), INTERVAL 10 DAY), 10);
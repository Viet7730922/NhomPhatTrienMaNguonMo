<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses.php';
requireRole('student');

// Chỉ nhận POST (từ form ở course.php)
if (!isPost()) {
    redirect(url('index.php'));
}
if (!csrfVerify()) {
    flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
    redirect(url('index.php'));
}

$courseId = (int) ($_POST['course_id'] ?? 0);
$course   = $courseId > 0 ? getCourse($courseId) : null;

if (!$course || $course['status'] !== 'published') {
    flash('danger', 'Khóa học không tồn tại hoặc chưa được công khai.');
    redirect(url('index.php'));
}

$studentId = (int) currentUser()['id'];

if (isEnrolled($studentId, $courseId)) {
    flash('info', 'Bạn đã ghi danh khóa học này rồi.');
} else {
    // INSERT IGNORE: nếu bấm 2 lần cùng lúc thì ràng buộc UNIQUE sẽ bỏ qua bản trùng
    $stmt = db()->prepare('INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)');
    $stmt->execute([$studentId, $courseId]);
    flash('success', 'Ghi danh thành công khóa học "' . $course['title'] . '"!');
}

redirect(url('course.php?id=' . $courseId));

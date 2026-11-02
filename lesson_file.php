<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

if (!isLoggedIn()) {
    flash('warning', 'Vui lòng đăng nhập để tải tài liệu.');
    redirect(url('login.php'));
}

$lessonId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT l.id, l.title, l.file_path, l.course_id, c.teacher_id, c.status AS course_status
     FROM lessons l JOIN courses c ON c.id = l.course_id
     WHERE l.id = ?'
);
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson || !$lesson['file_path']
    || !canAccessCourseContent([
        'id' => $lesson['course_id'],
        'teacher_id' => $lesson['teacher_id'],
        'status' => $lesson['course_status'],
    ])) {
    flash('danger', 'Không tìm thấy tài liệu hoặc bạn không có quyền tải.');
    redirect(url('index.php'));
}

$ext  = strtolower(pathinfo($lesson['file_path'], PATHINFO_EXTENSION));
$name = safeDownloadName($lesson['title'], $ext);

if (!sendFileDownload(UPLOAD_PATH . 'lessons/' . basename($lesson['file_path']), $name)) {
    flash('danger', 'Không tìm thấy tài liệu trên máy chủ (có thể đã bị xóa).');
    redirect(url('lesson.php?id=' . $lessonId));
}

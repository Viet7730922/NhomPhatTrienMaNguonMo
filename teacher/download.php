<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('teacher');

$fileId = (int) ($_GET['id'] ?? 0);

// Chỉ tải được file thuộc bài nộp của khóa học do chính mình phụ trách
$stmt = db()->prepare(
    'SELECT sf.* FROM submission_files sf
     JOIN submissions s ON s.id = sf.submission_id
     JOIN assignments a ON a.id = s.assignment_id
     JOIN courses c ON c.id = a.course_id
     WHERE sf.id = ? AND c.teacher_id = ?'
);
$stmt->execute([$fileId, (int) currentUser()['id']]);
$file = $stmt->fetch();

if (!$file) {
    flash('danger', 'Không tìm thấy file hoặc bạn không có quyền tải.');
    redirect(url('teacher/index.php'));
}

sendSubmissionFile($file);   // thành công thì exit; chỉ chạy tiếp xuống dưới nếu file mất

redirect(url('teacher/grade.php?id=' . (int) $file['submission_id']));

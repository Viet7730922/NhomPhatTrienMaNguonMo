<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('student');

$fileId = (int) ($_GET['id'] ?? 0);

// Sinh viên chỉ tải được file trong bài nộp của chính mình
$stmt = db()->prepare(
    'SELECT sf.*, s.assignment_id FROM submission_files sf
     JOIN submissions s ON s.id = sf.submission_id
     WHERE sf.id = ? AND s.student_id = ?'
);
$stmt->execute([$fileId, (int) currentUser()['id']]);
$file = $stmt->fetch();

if (!$file) {
    flash('danger', 'Không tìm thấy file hoặc bạn không có quyền tải.');
    redirect(url('student/assignments.php'));
}

sendSubmissionFile($file);   // thành công thì exit; chạy tiếp xuống dưới nếu file không còn trên đĩa

redirect(url('student/assignment.php?id=' . (int) $file['assignment_id']));

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('teacher');

if (!isPost() || !csrfVerify()) {
    flash('danger', 'Yêu cầu không hợp lệ.');
    redirect(url('teacher/index.php'));
}

$assignmentId = (int) ($_POST['assignment_id'] ?? 0);
$assignment   = requireOwnAssignment($assignmentId);   // chỉ xóa được bài tập thuộc khóa của mình

// Lấy danh sách file trước khi xóa dòng trong CSDL
$storedNames = submissionFileNamesForAssignment($assignmentId);

// ON DELETE CASCADE sẽ xóa kèm submissions và submission_files
db()->prepare('DELETE FROM assignments WHERE id = ? AND course_id = ?')
    ->execute([$assignmentId, (int) $assignment['course_id']]);

deleteSubmissionFiles($storedNames);

flash('success', 'Đã xóa bài tập "' . $assignment['title'] . '".');
redirect(url('teacher/assignments.php?course_id=' . (int) $assignment['course_id']));

<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$courseId = (int) ($_GET['course_id'] ?? 0);
$destination = url('student/assignments.php') . ($courseId > 0 ? '?course_id=' . $courseId : '');
redirect($destination);

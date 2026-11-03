<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('student');

$studentId = (int) currentUser()['id'];
$courseId  = (int) ($_GET['course_id'] ?? 0);

$sql = "SELECT q.*, c.title AS course_title,
               (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
        FROM quizzes q
        JOIN courses c     ON c.id = q.course_id
        JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
        WHERE q.is_published = 1 AND c.status = 'published'";
$params = [$studentId];
if ($courseId > 0) {
    $sql .= ' AND c.id = ?';
    $params[] = $courseId;
}
$sql .= ' ORDER BY (q.close_at IS NULL), q.close_at, q.id';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$quizzes = $stmt->fetchAll();

// Gom lần làm bài của sinh viên theo bài kiểm tra
$stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE student_id = ? ORDER BY quiz_id, attempt_no');
$stmt->execute([$studentId]);
$attemptsByQuiz = [];
foreach ($stmt->fetchAll() as $a) {
    $attemptsByQuiz[(int) $a['quiz_id']][] = $a;
}

$availLabels = ['not_open' => ['Chưa mở', 'secondary'], 'open' => ['Đang mở', 'success'], 'closed' => ['Đã đóng', 'dark']];

$pageTitle = 'Bài kiểm tra của tôi';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('student/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item active">Bài kiểm tra</li>
    </ol>
</nav>

<h3 class="mb-3">Bài kiểm tra của tôi</h3>

<?php if (!$quizzes): ?>
    <div class="alert alert-info">Chưa có bài kiểm tra nào trong các khóa học bạn đã ghi danh.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bài kiểm tra</th>
                        <th>Khóa học</th>
                        <th>Đóng lúc</th>
                        <th class="text-center">Số lần làm</th>
                        <th class="text-center">Điểm</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $q): ?>
                    <?php
                    $atts  = $attemptsByQuiz[(int) $q['id']] ?? [];
                    $final = quizFinalGrade($q, $atts);
                    [$aLabel, $aColor] = $availLabels[quizAvailability($q)];
                    $waiting = count(array_filter($atts, fn($a) => $a['status'] === 'waiting'));
                    ?>
                    <tr>
                        <td class="fw-semibold">
                            <a href="<?= url('student/quiz.php?id=' . (int) $q['id']) ?>" class="text-decoration-none"><?= e($q['title']) ?></a>
                            <div class="small text-muted fw-normal"><?= (int) $q['question_count'] ?> câu hỏi<?= $q['time_limit_min'] ? ' · ' . (int) $q['time_limit_min'] . ' phút' : '' ?></div>
                        </td>
                        <td class="small"><?= e($q['course_title']) ?></td>
                        <td class="text-nowrap small"><?= e(fmtDateTime($q['close_at'], 'Không giới hạn')) ?></td>
                        <td class="text-center"><?= count($atts) ?> / <?= (int) $q['max_attempts'] > 0 ? (int) $q['max_attempts'] : '∞' ?></td>
                        <td class="text-center">
                            <?= $final !== null ? e(fmtMarks($final)) . ' / ' . e(formatScore($q['max_grade'])) : '—' ?>
                            <?php if ($waiting > 0): ?><div class="small text-muted">có bài chờ chấm</div><?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-<?= e($aColor) ?>"><?= e($aLabel) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

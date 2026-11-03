<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

$quizId = (int) ($_GET['quiz_id'] ?? 0);
$quiz   = requireOwnQuiz($quizId);

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'waiting', 'finished'], true)) {
    $filter = 'all';
}

$stmt = db()->prepare(
    "SELECT a.*, u.full_name, u.email
     FROM quiz_attempts a JOIN users u ON u.id = a.student_id
     WHERE a.quiz_id = ? AND a.status <> 'in_progress'
     ORDER BY u.full_name, a.attempt_no"
);
$stmt->execute([$quizId]);
$all = $stmt->fetchAll();

// Điểm cuối cùng theo từng sinh viên
$byStudent = [];
foreach ($all as $a) {
    $byStudent[(int) $a['student_id']]['name']      = $a['full_name'];
    $byStudent[(int) $a['student_id']]['email']     = $a['email'];
    $byStudent[(int) $a['student_id']]['attempts'][] = $a;
}
$finals = [];
foreach ($byStudent as $sid => $s) {
    $finals[$sid] = quizFinalGrade($quiz, $s['attempts']);
}
$gradedFinals = array_filter($finals, fn($g) => $g !== null);
$average      = $gradedFinals ? array_sum($gradedFinals) / count($gradedFinals) : null;

$count = ['all' => count($all), 'waiting' => 0, 'finished' => 0];
foreach ($all as $a) {
    $count[$a['status']]++;
}
$rows    = $filter === 'all' ? $all : array_values(array_filter($all, fn($a) => $a['status'] === $filter));
$tabs    = ['all' => 'Tất cả', 'waiting' => 'Chờ chấm', 'finished' => 'Đã hoàn thành'];
$selfUrl = url('teacher/quiz_attempts.php?quiz_id=' . $quizId);

$pageTitle = 'Kết quả: ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/quizzes.php?course_id=' . (int) $quiz['course_id']) ?>">Bài kiểm tra: <?= e($quiz['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($quiz['title']) ?></li>
    </ol>
</nav>

<h3 class="mb-1"><?= e($quiz['title']) ?></h3>
<p class="text-muted">
    <?= count($byStudent) ?> sinh viên đã nộp &middot; Tính điểm: <strong><?= e(QUIZ_GRADE_METHODS[$quiz['grade_method']]) ?></strong>
    <?php if ($average !== null): ?>&middot; Điểm cuối cùng trung bình: <strong><?= e(fmtMarks($average)) ?>/<?= e(fmtMarks($quiz['max_grade'])) ?></strong><?php endif; ?>
</p>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $key ? 'active' : '' ?>" href="<?= e($selfUrl . ($key === 'all' ? '' : '&filter=' . $key)) ?>">
                <?= e($label) ?> <span class="badge text-bg-light"><?= (int) $count[$key] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$rows): ?>
    <div class="alert alert-info">Không có lần làm bài nào trong mục này.</div>
<?php else: ?>
    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Sinh viên</th><th class="text-center">Lần</th><th>Trạng thái</th><th>Nộp lúc</th><th>Thời gian</th>
                    <th class="text-center">Điểm lần này</th><th class="text-center">Điểm cuối cùng</th><th class="text-end">Thao tác</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <?php $g = attemptGrade($quiz, $a); $fin = $finals[(int) $a['student_id']] ?? null; ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($a['full_name']) ?></div><div class="small text-muted"><?= e($a['email']) ?></div></td>
                    <td class="text-center"><?= (int) $a['attempt_no'] ?></td>
                    <td><?= attemptStatusBadge($a) ?></td>
                    <td class="text-nowrap small"><?= e(fmtDateTime($a['finished_at'])) ?></td>
                    <td class="small"><?= e(attemptDuration($a)) ?></td>
                    <td class="text-center"><?= $g !== null ? e(fmtMarks($g)) . ' / ' . e(fmtMarks($quiz['max_grade'])) : '—' ?></td>
                    <td class="text-center"><?= $fin !== null ? '<strong>' . e(fmtMarks($fin)) . '</strong>' : '—' ?></td>
                    <td class="text-end">
                        <a href="<?= url('teacher/quiz_grade.php?id=' . (int) $a['id']) ?>"
                           class="btn btn-sm <?= $a['status'] === 'waiting' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <i class="bi bi-check2-square"></i> <?= $a['status'] === 'waiting' ? 'Chấm tự luận' : 'Xem / sửa điểm' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

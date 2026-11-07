<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('student');

$studentId = (int) currentUser()['id'];
$courseId  = (int) ($_GET['course_id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'todo', 'submitted', 'graded'], true)) {
    $filter = 'all';
}

$all = getStudentAssignments($studentId, $courseId > 0 ? $courseId : null);

$quizSql = "SELECT q.*, c.title AS course_title,
                   (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
            FROM quizzes q
            JOIN courses c     ON c.id = q.course_id
            JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
            WHERE q.is_published = 1 AND c.status = 'published'";
$quizParams = [$studentId];
if ($courseId > 0) {
    $quizSql .= ' AND c.id = ?';
    $quizParams[] = $courseId;
}
$quizSql .= ' ORDER BY (q.close_at IS NULL), q.close_at, q.id';
$stmt = db()->prepare($quizSql);
$stmt->execute($quizParams);
$quizzes = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE student_id = ? ORDER BY quiz_id, attempt_no');
$stmt->execute([$studentId]);
$attemptsByQuiz = [];
foreach ($stmt->fetchAll() as $attempt) {
    $attemptsByQuiz[(int) $attempt['quiz_id']][] = $attempt;
}

$count = ['all' => count($all), 'todo' => 0, 'submitted' => 0, 'graded' => 0];
foreach ($all as $r) {
    $count[$r['status']]++;
}
$rows = $filter === 'all' ? $all : array_values(array_filter($all, fn($r) => $r['status'] === $filter));

$courseTitle = null;
if ($courseId > 0) {
    $courseTitle = $all[0]['course_title'] ?? $quizzes[0]['course_title'] ?? null;
}

$tabs  = ['all' => 'Tất cả', 'todo' => 'Chưa nộp', 'submitted' => 'Đã nộp', 'graded' => 'Đã chấm'];
$base  = url('student/assignments.php') . ($courseId > 0 ? '?course_id=' . $courseId : '');
$joint = $courseId > 0 ? '&' : '?';

$pageTitle = 'Bài tập & kiểm tra';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('student/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item active">Bài tập & kiểm tra<?= $courseTitle ? ': ' . e($courseTitle) : '' ?></li>
    </ol>
</nav>

<h3 class="mb-4">Bài tập & kiểm tra</h3>

<section class="mb-5">
<h4 class="mb-3"><i class="bi bi-clipboard-check"></i> Bài tập cần nộp</h4>
<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $key ? 'active' : '' ?>"
               href="<?= e($base . ($key === 'all' ? '' : $joint . 'filter=' . $key)) ?>">
                <?= e($label) ?> <span class="badge text-bg-light"><?= (int) $count[$key] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$rows): ?>
    <div class="alert alert-info">Không có bài tập nào trong mục này.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bài tập</th>
                        <th>Khóa học</th>
                        <th>Hạn nộp</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Điểm</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold">
                            <a href="<?= url('student/assignment.php?id=' . (int) $r['id']) ?>" class="text-decoration-none">
                                <?= e($r['title']) ?>
                            </a>
                            <?php
                            $modeLabels = ['file' => 'Nộp file', 'online_text' => 'Online text', 'both' => 'File + Online text'];
                            $mode = $r['submission_mode'] ?? 'both';
                            ?>
                            <div><span class="badge text-bg-light border"><?= e($modeLabels[$mode] ?? $modeLabels['both']) ?></span></div>
                        </td>
                        <td class="small"><?= e($r['course_title']) ?></td>
                        <td class="text-nowrap small"><?= e(fmtDateTime($r['due_date'], 'Không giới hạn')) ?></td>
                        <td><?= assignmentStatusBadge($r) ?></td>
                        <td class="text-center">
                            <?= $r['status'] === 'graded' ? e(formatScore($r['score'])) . ' / ' . e(formatScore($r['max_score'])) : '—' ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('student/assignment.php?id=' . (int) $r['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <?= $r['status'] === 'todo' ? 'Nộp bài' : 'Xem' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</section>

<section>
    <h4 class="mb-3"><i class="bi bi-ui-checks"></i> Bài kiểm tra trắc nghiệm</h4>
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
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($quizzes as $quiz): ?>
                        <?php
                        $attempts = $attemptsByQuiz[(int) $quiz['id']] ?? [];
                        $final = quizFinalGrade($quiz, $attempts);
                        $availability = quizAvailability($quiz);
                        $availabilityLabels = ['not_open' => ['Chưa mở', 'secondary'], 'open' => ['Đang mở', 'success'], 'closed' => ['Đã đóng', 'dark']];
                        [$availabilityLabel, $availabilityColor] = $availabilityLabels[$availability];
                        $inProgress = (bool) array_filter($attempts, fn($attempt) => $attempt['status'] === 'in_progress');
                        $waiting = count(array_filter($attempts, fn($attempt) => $attempt['status'] === 'waiting'));
                        $canStart = (int) $quiz['question_count'] > 0
                            && $availability === 'open'
                            && ((int) $quiz['max_attempts'] === 0 || count($attempts) < (int) $quiz['max_attempts']);
                        $actionLabel = $inProgress ? 'Tiếp tục làm' : ($canStart ? 'Làm trắc nghiệm' : 'Xem bài kiểm tra');
                        ?>
                        <tr>
                            <td class="fw-semibold">
                                <a href="<?= url('student/quiz.php?id=' . (int) $quiz['id']) ?>" class="text-decoration-none"><?= e($quiz['title']) ?></a>
                                <div class="small text-muted fw-normal">
                                    <?= (int) $quiz['question_count'] ?> câu hỏi<?= $quiz['time_limit_min'] ? ' · ' . (int) $quiz['time_limit_min'] . ' phút' : '' ?>
                                </div>
                            </td>
                            <td class="small"><?= e($quiz['course_title']) ?></td>
                            <td class="text-nowrap small"><?= e(fmtDateTime($quiz['close_at'], 'Không giới hạn')) ?></td>
                            <td class="text-center"><?= count($attempts) ?> / <?= (int) $quiz['max_attempts'] > 0 ? (int) $quiz['max_attempts'] : '∞' ?></td>
                            <td class="text-center">
                                <?= $final !== null ? e(fmtMarks($final)) . ' / ' . e(formatScore($quiz['max_grade'])) : '—' ?>
                                <?php if ($waiting > 0): ?><div class="small text-muted">có bài chờ chấm</div><?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= e($availabilityColor) ?>"><?= e($availabilityLabel) ?></span></td>
                            <td class="text-end">
                                <a href="<?= url('student/quiz.php?id=' . (int) $quiz['id']) ?>"
                                   class="btn btn-sm <?= $canStart || $inProgress ? 'btn-primary' : 'btn-outline-secondary' ?>">
                                    <?= e($actionLabel) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>

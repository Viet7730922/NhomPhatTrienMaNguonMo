<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('teacher');

$assignmentId = (int) ($_GET['assignment_id'] ?? 0);
$assignment   = requireOwnAssignment($assignmentId);
$courseId     = (int) $assignment['course_id'];

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'graded', 'missing'], true)) {
    $filter = 'all';
}

// Toàn bộ sinh viên đã ghi danh, kèm bài nộp (nếu có)
$sql = "SELECT u.id AS student_id, u.full_name, u.email,
               s.id AS submission_id, s.submitted_at, s.score, s.graded_at,
               (SELECT COUNT(*) FROM submission_files sf WHERE sf.submission_id = s.id) AS file_count
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN submissions s ON s.assignment_id = ? AND s.student_id = u.id
        WHERE e.course_id = ?
        ORDER BY u.full_name";
$stmt = db()->prepare($sql);
$stmt->execute([$assignmentId, $courseId]);
$all = $stmt->fetchAll();

$scores = [];
foreach ($all as &$r) {
    if ($r['submission_id'] === null) {
        $r['status'] = 'missing';
    } elseif ($r['score'] !== null) {
        $r['status'] = 'graded';
        $scores[] = (float) $r['score'];
    } else {
        $r['status'] = 'pending';
    }
}
unset($r);

$count = ['all' => count($all), 'pending' => 0, 'graded' => 0, 'missing' => 0];
foreach ($all as $r) {
    $count[$r['status']]++;
}
$average = $scores ? array_sum($scores) / count($scores) : null;

$rows = $filter === 'all' ? $all : array_values(array_filter($all, fn($r) => $r['status'] === $filter));

$tabs = ['all' => 'Tất cả', 'pending' => 'Chờ chấm', 'graded' => 'Đã chấm', 'missing' => 'Chưa nộp'];
$selfUrl = url('teacher/submissions.php?assignment_id=' . $assignmentId);

$pageTitle = 'Bài nộp: ' . $assignment['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/assignments.php?course_id=' . $courseId) ?>">Bài tập: <?= e($assignment['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($assignment['title']) ?></li>
    </ol>
</nav>

<h3 class="mb-1"><?= e($assignment['title']) ?></h3>
<p class="text-muted">
    Hạn nộp: <strong><?= e(fmtDateTime($assignment['due_date'], 'Không giới hạn')) ?></strong>
    &middot; Điểm tối đa: <strong><?= e(formatScore($assignment['max_score'])) ?></strong>
    <?php if ($average !== null): ?>
        &middot; Điểm trung bình: <strong><?= e(formatScore($average)) ?></strong>
    <?php endif; ?>
</p>

<?php if (!empty($assignment['description'])): ?>
    <div class="card mb-4">
        <div class="card-body small"><?= nl2br(e($assignment['description'])) ?></div>
    </div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $key ? 'active' : '' ?>"
               href="<?= e($selfUrl . ($key === 'all' ? '' : '&filter=' . $key)) ?>">
                <?= e($label) ?> <span class="badge text-bg-light"><?= (int) $count[$key] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (!$rows): ?>
    <div class="alert alert-info">Không có sinh viên nào trong mục này.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Sinh viên</th>
                        <th>Trạng thái</th>
                        <th>Thời gian nộp</th>
                        <th class="text-center">Số file</th>
                        <th class="text-center">Điểm</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                            <div class="text-muted small"><?= e($r['email']) ?></div>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'missing'): ?>
                                <span class="badge text-bg-secondary">Chưa nộp</span>
                            <?php elseif ($r['status'] === 'graded'): ?>
                                <span class="badge text-bg-success">Đã chấm</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Chờ chấm</span>
                            <?php endif; ?>
                            <?php if ($r['submission_id'] !== null && isLateSubmission($assignment, $r['submitted_at'])): ?>
                                <span class="badge text-bg-danger">Nộp trễ</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= e(fmtDateTime($r['submitted_at'])) ?></td>
                        <td class="text-center"><?= $r['submission_id'] !== null ? (int) $r['file_count'] : '—' ?></td>
                        <td class="text-center">
                            <?= $r['status'] === 'graded'
                                ? e(formatScore($r['score'])) . ' / ' . e(formatScore($assignment['max_score']))
                                : '—' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($r['submission_id'] !== null): ?>
                                <a href="<?= url('teacher/grade.php?id=' . (int) $r['submission_id']) ?>"
                                   class="btn btn-sm <?= $r['status'] === 'graded' ? 'btn-outline-secondary' : 'btn-primary' ?>">
                                    <i class="bi bi-check2-square"></i> <?= $r['status'] === 'graded' ? 'Xem / sửa điểm' : 'Chấm điểm' ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

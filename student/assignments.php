<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student.php';
requireRole('student');

$studentId = (int) currentUser()['id'];
$courseId  = (int) ($_GET['course_id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'todo', 'submitted', 'graded'], true)) {
    $filter = 'all';
}

$all = getStudentAssignments($studentId, $courseId > 0 ? $courseId : null);

$count = ['all' => count($all), 'todo' => 0, 'submitted' => 0, 'graded' => 0];
foreach ($all as $r) {
    $count[$r['status']]++;
}
$rows = $filter === 'all' ? $all : array_values(array_filter($all, fn($r) => $r['status'] === $filter));

$courseTitle = null;
if ($courseId > 0 && $all) {
    $courseTitle = $all[0]['course_title'];
}

$tabs  = ['all' => 'Tất cả', 'todo' => 'Chưa nộp', 'submitted' => 'Đã nộp', 'graded' => 'Đã chấm'];
$base  = url('student/assignments.php') . ($courseId > 0 ? '?course_id=' . $courseId : '');
$joint = $courseId > 0 ? '&' : '?';

$pageTitle = 'Bài tập của tôi';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('student/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item active">Bài tập<?= $courseTitle ? ': ' . e($courseTitle) : '' ?></li>
    </ol>
</nav>

<h3 class="mb-3">Bài tập của tôi</h3>

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

<?php require __DIR__ . '/../includes/footer.php'; ?>

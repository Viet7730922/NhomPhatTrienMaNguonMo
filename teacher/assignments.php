<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('teacher');

$courseId = (int) ($_GET['course_id'] ?? 0);
$course   = requireOwnCourse($courseId);

$sql = "SELECT a.*,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id)                     AS submitted_count,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.score IS NULL) AS pending_count
        FROM assignments a
        WHERE a.course_id = ?
        ORDER BY a.created_at DESC, a.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute([$courseId]);
$assignments = $stmt->fetchAll();
$totalAssignments = count($assignments);
$pg          = paginate($totalAssignments, 20);
$assignments = array_slice($assignments, $pg['offset'], $pg['perPage']);

$stmt = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
$stmt->execute([$courseId]);
$enrolled = (int) $stmt->fetchColumn();

$pageTitle = 'Bài tập: ' . $course['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/lessons.php?course_id=' . $courseId) ?>"><?= e($course['title']) ?></a></li>
        <li class="breadcrumb-item active">Bài tập</li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1">Bài tập: <?= e($course['title']) ?></h3>
        <span class="text-muted small"><?= count($assignments) ?> bài tập &middot; <?= $enrolled ?> sinh viên ghi danh</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('teacher/lessons.php?course_id=' . $courseId) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul"></i> Bài học
        </a>
        <a href="<?= url('teacher/quizzes.php?course_id=' . $courseId) ?>" class="btn btn-outline-primary">
            <i class="bi bi-ui-checks"></i> Bài kiểm tra
        </a>
        <a href="<?= url('teacher/assignment_form.php?course_id=' . $courseId) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Tạo bài tập
        </a>
    </div>
</div>

<?php if (!$assignments): ?>
    <div class="alert alert-info">Khóa học chưa có bài tập nào. Bấm <strong>Tạo bài tập</strong> để bắt đầu.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bài tập</th>
                        <th>Hạn nộp</th>
                        <th class="text-center">Điểm tối đa</th>
                        <th class="text-center">Đã nộp</th>
                        <th class="text-center">Chờ chấm</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <?php $expired = $a['due_date'] && strtotime($a['due_date']) < time(); ?>
                    <tr>
                        <td class="fw-semibold">
                            <a href="<?= url('teacher/submissions.php?assignment_id=' . (int) $a['id']) ?>" class="text-decoration-none">
                                <?= e($a['title']) ?>
                            </a>
                        </td>
                        <td class="text-nowrap">
                            <?= e(fmtDateTime($a['due_date'], 'Không giới hạn')) ?>
                            <?php if ($expired): ?><span class="badge text-bg-secondary ms-1">Hết hạn</span><?php endif; ?>
                        </td>
                        <td class="text-center"><?= e(formatScore($a['max_score'])) ?></td>
                        <td class="text-center"><?= (int) $a['submitted_count'] ?> / <?= $enrolled ?></td>
                        <td class="text-center">
                            <?php if ((int) $a['pending_count'] > 0): ?>
                                <span class="badge text-bg-warning"><?= (int) $a['pending_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= url('teacher/submissions.php?assignment_id=' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-inbox"></i> Bài nộp
                            </a>
                            <a href="<?= url('teacher/assignment_form.php?id=' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Sửa
                            </a>
                            <form method="post" action="<?= url('teacher/assignment_delete.php') ?>" class="d-inline"
                                  onsubmit="return confirm('Xóa bài tập này? Toàn bộ bài nộp và điểm của sinh viên sẽ bị xóa vĩnh viễn.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="assignment_id" value="<?= (int) $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

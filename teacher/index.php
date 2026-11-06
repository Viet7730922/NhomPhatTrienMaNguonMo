<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses.php';
requireRole('teacher');

$teacherId = (int) currentUser()['id'];

$sql = "SELECT c.*, cat.name AS category_name,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id)     AS lesson_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count,
               (SELECT COUNT(*) FROM submissions s
                  JOIN assignments a ON a.id = s.assignment_id
                 WHERE a.course_id = c.id AND s.score IS NULL)               AS pending_count
        FROM courses c
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC, c.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute([$teacherId]);
$courses = $stmt->fetchAll();

$totalCourses   = count($courses);
$totalPublished = count(array_filter($courses, fn($c) => $c['status'] === 'published'));
$totalStudents  = array_sum(array_column($courses, 'student_count'));
$totalLessons   = array_sum(array_column($courses, 'lesson_count'));
$totalPending   = array_sum(array_column($courses, 'pending_count'));

$pageTitle = 'Quản lý khóa học';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Khóa học của tôi</h3>
    <a href="<?= url('teacher/course_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tạo khóa học</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $stats = [
        ['Khóa học', $totalCourses, 'bi-journal-text', 'primary'],
        ['Đang công khai', $totalPublished, 'bi-globe', 'success'],
        ['Bài học', $totalLessons, 'bi-play-circle', 'info'],
        ['Lượt ghi danh', $totalStudents, 'bi-people', 'secondary'],
        ['Bài chờ chấm', $totalPending, 'bi-hourglass-split', 'warning'],
    ];
    foreach ($stats as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-lg">
            <div class="card">
                <div class="card-body d-flex align-items-center">
                    <i class="bi <?= $icon ?> fs-2 text-<?= $color ?> me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold lh-1"><?= (int) $value ?></div>
                        <div class="text-muted small"><?= e($label) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$courses): ?>
    <div class="alert alert-info">Bạn chưa có khóa học nào. Bấm <strong>Tạo khóa học</strong> để bắt đầu.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Khóa học</th>
                        <th>Danh mục</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Bài học</th>
                        <th class="text-center">Sinh viên</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td>
                            <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= e($c['title']) ?>
                            </a>
                            <div class="text-muted small"><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></div>
                        </td>
                        <td><?= e($c['category_name'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if ($c['status'] === 'published'): ?>
                                <span class="badge text-bg-success">Công khai</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nháp</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int) $c['lesson_count'] ?></td>
                        <td class="text-center"><?= (int) $c['student_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex flex-column align-items-end gap-2">
                                <!-- Quản lý nội dung của khóa học -->
                                <div class="btn-group btn-group-sm" role="group" aria-label="Quản lý nội dung khóa học">
                                    <a href="<?= url('teacher/lessons.php?course_id=' . (int) $c['id']) ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-list-ul"></i> Bài học
                                    </a>
                                    <a href="<?= url('teacher/assignments.php?course_id=' . (int) $c['id']) ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-clipboard-check"></i> Bài tập
                                        <?php if ((int) $c['pending_count'] > 0): ?>
                                            <span class="badge text-bg-warning" title="Bài chờ chấm"><?= (int) $c['pending_count'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="<?= url('teacher/quizzes.php?course_id=' . (int) $c['id']) ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-ui-checks"></i> Bài kiểm tra
                                    </a>
                                </div>

                                <!-- Thao tác khác -->
                                <div class="d-flex align-items-center gap-3 small">
                                    <a href="<?= url('teacher/quiz_form.php?course_id=' . (int) $c['id']) ?>" class="link-primary text-decoration-none">
                                        <i class="bi bi-plus-circle"></i> Tạo bài kiểm tra
                                    </a>
                                    <a href="<?= url('teacher/course_form.php?id=' . (int) $c['id']) ?>" class="link-secondary text-decoration-none">
                                        <i class="bi bi-pencil"></i> Sửa
                                    </a>
                                    <form method="post" action="<?= url('teacher/course_delete.php') ?>" class="m-0"
                                          onsubmit="return confirm('Xóa khóa học này? Toàn bộ bài học, ghi danh, bài tập và bài nộp liên quan sẽ bị xóa vĩnh viễn.');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0 border-0 text-danger text-decoration-none align-baseline">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
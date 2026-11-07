<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student.php';
requireRole('student');

$studentId = (int) currentUser()['id'];

$sql = "SELECT c.*, u.full_name AS teacher_name, cat.name AS category_name, e.enrolled_at,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count,
               (SELECT l.id FROM lessons l WHERE l.course_id = c.id ORDER BY l.sort_order, l.id LIMIT 1) AS first_lesson_id,
               (SELECT COUNT(*) FROM assignments a
                 WHERE a.course_id = c.id
                   AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)) AS todo_count
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE e.student_id = ?
        ORDER BY e.enrolled_at DESC, c.id DESC";
$stmt = db()->prepare($sql);
$stmt->execute([$studentId, $studentId]);
$courses = $stmt->fetchAll();

$todo = array_slice(array_values(array_filter(getStudentAssignments($studentId), fn($a) => $a['status'] === 'todo')), 0, 5);

$pageTitle = 'Khóa học của tôi';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Khóa học của tôi</h3>
    <a href="<?= url('student/assignments.php') ?>" class="btn btn-outline-primary">
        <i class="bi bi-clipboard-check"></i> Bài tập & kiểm tra
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <?php if (!$courses): ?>
            <div class="alert alert-info">
                Bạn chưa ghi danh khóa học nào. <a href="<?= url('index.php') ?>">Khám phá khóa học</a>
            </div>
        <?php else: ?>
            <div class="row g-3 course-grid">
                <?php foreach ($courses as $c): ?>
                    <div class="col-md-6">
                        <div class="card h-100 overflow-hidden">
                            <?= courseThumbHtml($c, 120) ?>
                            <div class="card-body d-flex flex-column">
                                <?php if ($c['category_name']): ?>
                                    <div class="mb-1"><span class="badge text-bg-light border"><?= e($c['category_name']) ?></span></div>
                                <?php endif; ?>
                                <h6 class="card-title">
                                    <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="text-decoration-none text-dark">
                                        <?= e($c['title']) ?>
                                    </a>
                                </h6>
                                <div class="small text-muted mb-3 flex-grow-1">
                                    <div><i class="bi bi-person"></i> <?= e($c['teacher_name']) ?></div>
                                    <div><i class="bi bi-play-circle"></i> <?= (int) $c['lesson_count'] ?> bài học</div>
                                    <?php if ((int) $c['todo_count'] > 0): ?>
                                        <div class="text-danger"><i class="bi bi-exclamation-circle"></i> <?= (int) $c['todo_count'] ?> bài tập chưa nộp</div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($c['first_lesson_id']): ?>
                                        <a href="<?= url('lesson.php?id=' . (int) $c['first_lesson_id']) ?>" class="btn btn-sm btn-primary flex-grow-1">
                                            <i class="bi bi-play-fill"></i> Vào học
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">Xem khóa học</a>
                                    <?php endif; ?>
                                    <a href="<?= url('student/assignments.php?course_id=' . (int) $c['id']) ?>" class="btn btn-sm btn-outline-primary" title="Bài tập & kiểm tra">
                                        <i class="bi bi-clipboard-check"></i><span class="visually-hidden"> Bài tập & kiểm tra</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-bold">Bài tập cần làm</div>
            <?php if (!$todo): ?>
                <div class="card-body text-muted small">Không có bài tập nào đang chờ bạn nộp.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($todo as $a): ?>
                        <li class="list-group-item">
                            <a href="<?= url('student/assignment.php?id=' . (int) $a['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= e($a['title']) ?>
                            </a>
                            <div class="small text-muted"><?= e($a['course_title']) ?></div>
                            <div class="small <?= $a['overdue'] ? 'text-danger' : 'text-muted' ?>">
                                Hạn: <?= e(fmtDateTime($a['due_date'], 'Không giới hạn')) ?>
                                <?php if ($a['overdue']): ?>(quá hạn)<?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

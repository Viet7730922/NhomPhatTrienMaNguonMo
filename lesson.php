<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student.php';

if (!isLoggedIn()) {
    flash('warning', 'Vui lòng đăng nhập để xem bài học.');
    redirect(url('login.php'));
}

$lessonId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT l.*, c.title AS course_title, c.teacher_id, c.status AS course_status
     FROM lessons l JOIN courses c ON c.id = l.course_id
     WHERE l.id = ?'
);
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy bài học';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Bài học không tồn tại. <a href="' . url('index.php') . '">Quay lại danh sách khóa học</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$courseId = (int) $lesson['course_id'];
if (!canAccessCourseContent([
    'id' => $courseId,
    'teacher_id' => $lesson['teacher_id'],
    'status' => $lesson['course_status'],
])) {
    if (hasRole('student')) {
        flash('warning', 'Hãy ghi danh khóa học để xem bài học.');
        redirect(url('course.php?id=' . $courseId));
    }
    flash('danger', 'Bạn không có quyền xem bài học này.');
    redirect(roleHome(currentUser()['role']));
}

$stmt = db()->prepare('SELECT id, title, video_url, file_path FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$stmt->execute([$courseId]);
$lessons = $stmt->fetchAll();

$index = 0;
foreach ($lessons as $i => $l) {
    if ((int) $l['id'] === $lessonId) {
        $index = $i;
        break;
    }
}
$prev = $lessons[$index - 1] ?? null;
$next = $lessons[$index + 1] ?? null;

$embed = youtubeEmbedUrl($lesson['video_url']);

$pageTitle = $lesson['title'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('course.php?id=' . $courseId) ?>"><?= e($lesson['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($lesson['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="text-muted small mb-1">Bài <?= $index + 1 ?> / <?= count($lessons) ?></div>
                <h3 class="mb-3"><?= e($lesson['title']) ?></h3>

                <?php if ($embed): ?>
                    <div class="ratio ratio-16x9 mb-4">
                        <iframe src="<?= e($embed) ?>" title="<?= e($lesson['title']) ?>" loading="lazy" allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; encrypted-media; picture-in-picture"></iframe>
                    </div>
                <?php endif; ?>

                <?php if (!empty($lesson['content'])): ?>
                    <div class="lh-lg"><?= nl2br(e($lesson['content'])) ?></div>
                <?php elseif (!$embed): ?>
                    <p class="text-muted mb-0">Bài học chưa có nội dung.</p>
                <?php endif; ?>

                <?php if ($lesson['file_path']): ?>
                    <hr>
                    <a href="<?= url('lesson_file.php?id=' . $lessonId) ?>" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Tải tài liệu bài học
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <?php if ($prev): ?>
                <a href="<?= url('lesson.php?id=' . (int) $prev['id']) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> Bài trước
                </a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($next): ?>
                <a href="<?= url('lesson.php?id=' . (int) $next['id']) ?>" class="btn btn-primary">
                    Bài tiếp <i class="bi bi-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 1rem">
            <div class="card-header bg-white fw-bold">Nội dung khóa học</div>
            <div class="list-group list-group-flush">
                <?php foreach ($lessons as $i => $l): ?>
                    <a href="<?= url('lesson.php?id=' . (int) $l['id']) ?>"
                       class="list-group-item list-group-item-action <?= (int) $l['id'] === $lessonId ? 'active' : '' ?>">
                        <?= $i + 1 ?>. <?= e($l['title']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (hasRole('student')): ?>
                <div class="card-body border-top">
                    <a href="<?= url('student/assignments.php?course_id=' . $courseId) ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-clipboard-check"></i> Bài tập của khóa học
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

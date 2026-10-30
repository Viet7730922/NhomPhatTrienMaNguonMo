<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses.php';

$id     = (int) ($_GET['id'] ?? 0);
$course = $id > 0 ? getCourse($id) : null;

// Không tồn tại hoặc không có quyền xem (bản nháp) thì báo 404
if (!$course || !canViewCourse($course)) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy khóa học';
    require __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Khóa học không tồn tại hoặc đã bị gỡ. '
       . '<a href="' . url('index.php') . '">Quay lại danh sách</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT id, title FROM lessons WHERE course_id = ? ORDER BY sort_order, id');
$stmt->execute([$id]);
$lessons = $stmt->fetchAll();

$user       = currentUser();
$isStudent  = hasRole('student');
$enrolled   = $isStudent && isEnrolled((int) $user['id'], $id);
// Học viên đã ghi danh, giảng viên chủ khóa và admin được mở bài học
$canOpenLessons = $enrolled
    || hasRole('admin')
    || (hasRole('teacher') && (int) $user['id'] === (int) $course['teacher_id']);

$pageTitle = $course['title'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Khóa học</a></li>
        <li class="breadcrumb-item active"><?= e($course['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Cột trái: thông tin & danh sách bài học -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <?php if ($course['status'] === 'draft'): ?>
                    <span class="badge text-bg-warning mb-2">Bản nháp - chỉ bạn nhìn thấy</span>
                <?php endif; ?>
                <?php if ($course['category_name']): ?>
                    <span class="badge text-bg-light border mb-2"><?= e($course['category_name']) ?></span>
                <?php endif; ?>

                <h2 class="h3"><?= e($course['title']) ?></h2>
                <p class="text-muted mb-3">
                    <i class="bi bi-person"></i> Giảng viên: <strong><?= e($course['teacher_name']) ?></strong>
                    &middot; <i class="bi bi-people"></i> <?= (int) $course['student_count'] ?> học viên
                </p>

                <h5>Giới thiệu</h5>
                <p><?= nl2br(e($course['description'] ?: 'Chưa có mô tả.')) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">
                Nội dung khóa học (<?= count($lessons) ?> bài)
            </div>
            <?php if (!$lessons): ?>
                <div class="card-body text-muted">Khóa học chưa có bài học nào.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($lessons as $i => $l): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php if ($canOpenLessons): ?>
                                <a href="<?= url('lesson.php?id=' . (int) $l['id']) ?>" class="text-decoration-none">
                                    <?= $i + 1 ?>. <?= e($l['title']) ?>
                                </a>
                                <i class="bi bi-play-circle text-primary"></i>
                            <?php else: ?>
                                <span><?= $i + 1 ?>. <?= e($l['title']) ?></span>
                                <i class="bi bi-lock text-muted"></i>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cột phải: ảnh bìa & nút ghi danh -->
    <div class="col-lg-4">
        <div class="card overflow-hidden sticky-top" style="top: 1rem">
            <?= courseThumbHtml($course, 200) ?>
            <div class="card-body">
                <?php if (!$user): ?>
                    <p class="text-muted small">Đăng nhập bằng tài khoản học viên để ghi danh khóa học này.</p>
                    <a href="<?= url('login.php') ?>" class="btn btn-primary w-100">Đăng nhập để ghi danh</a>

                <?php elseif ($isStudent && $enrolled): ?>
                    <div class="alert alert-success py-2 small"><i class="bi bi-check-circle"></i> Bạn đã ghi danh khóa học này.</div>
                    <?php if ($lessons): ?>
                        <a href="<?= url('lesson.php?id=' . (int) $lessons[0]['id']) ?>" class="btn btn-success w-100">
                            <i class="bi bi-play-fill"></i> Vào học
                        </a>
                    <?php endif; ?>

                <?php elseif ($isStudent): ?>
                    <?php if ($course['status'] === 'published'): ?>
                        <form method="post" action="<?= url('student/enroll.php') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Ghi danh miễn phí</button>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="text-muted small mb-0">
                        Bạn đang xem với vai trò <strong><?= e($user['role']) ?></strong>.
                        Chỉ tài khoản học viên mới có thể ghi danh.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

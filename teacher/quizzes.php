<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

$courseId = (int) ($_GET['course_id'] ?? 0);
$course   = requireOwnCourse($courseId);
$selfUrl  = url('teacher/quizzes.php?course_id=' . $courseId);

// ---------- Xóa bài kiểm tra (POST + CSRF + kiểm tra quyền sở hữu) ----------
if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($selfUrl);
    }
    $quiz = requireOwnQuiz((int) ($_POST['quiz_id'] ?? 0));
    if ((int) $quiz['course_id'] !== $courseId) {
        flash('danger', 'Bài kiểm tra không thuộc khóa học này.');
        redirect($selfUrl);
    }
    db()->prepare('DELETE FROM quizzes WHERE id = ? AND course_id = ?')->execute([(int) $quiz['id'], $courseId]);   // CASCADE
    flash('success', 'Đã xóa bài kiểm tra "' . $quiz['title'] . '".');
    redirect($selfUrl);
}

$stmt = db()->prepare(
    "SELECT q.*,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
            (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.status <> 'in_progress') AS attempt_count,
            (SELECT COUNT(*) FROM quiz_attempts a WHERE a.quiz_id = q.id AND a.status = 'waiting')      AS waiting_count
     FROM quizzes q WHERE q.course_id = ? ORDER BY q.created_at DESC, q.id DESC"
);
$stmt->execute([$courseId]);
$quizzes = $stmt->fetchAll();

$pageTitle = 'Bài kiểm tra: ' . $course['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/lessons.php?course_id=' . $courseId) ?>"><?= e($course['title']) ?></a></li>
        <li class="breadcrumb-item active">Bài kiểm tra</li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Bài kiểm tra: <?= e($course['title']) ?></h3>
    <a href="<?= url('teacher/quiz_form.php?course_id=' . $courseId) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tạo bài kiểm tra</a>
</div>

<?php if (!$quizzes): ?>
    <div class="alert alert-info">Khóa học chưa có bài kiểm tra nào. Bấm <strong>Tạo bài kiểm tra</strong> để bắt đầu.</div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bài kiểm tra</th><th>Đóng lúc</th><th class="text-center">Câu hỏi</th>
                        <th class="text-center">Lượt nộp</th><th class="text-center">Chờ chấm</th><th>Trạng thái</th><th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $q): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($q['title']) ?></td>
                        <td class="text-nowrap small"><?= e(fmtDateTime($q['close_at'], 'Không giới hạn')) ?></td>
                        <td class="text-center"><?= (int) $q['question_count'] ?></td>
                        <td class="text-center"><?= (int) $q['attempt_count'] ?></td>
                        <td class="text-center">
                            <?= (int) $q['waiting_count'] > 0 ? '<span class="badge text-bg-warning">' . (int) $q['waiting_count'] . '</span>' : '<span class="text-muted">0</span>' ?>
                        </td>
                        <td><?= $q['is_published'] ? '<span class="badge text-bg-success">Công khai</span>' : '<span class="badge text-bg-secondary">Nháp</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= url('teacher/quiz_questions.php?quiz_id=' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-check"></i> Câu hỏi</a>
                            <a href="<?= url('teacher/quiz_attempts.php?quiz_id=' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-inbox"></i> Kết quả</a>
                            <a href="<?= url('teacher/quiz_form.php?id=' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Sửa</a>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Xóa bài kiểm tra này? Toàn bộ câu hỏi, bài làm và điểm sẽ bị xóa vĩnh viễn.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="quiz_id" value="<?= (int) $q['id'] ?>">
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

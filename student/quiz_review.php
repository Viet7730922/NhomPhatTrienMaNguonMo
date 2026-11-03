<?php
/** Xem lại bài đã nộp. Chỉ vào được khi review_mode của bài kiểm tra cho phép. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('student');

$studentId = (int) currentUser()['id'];
$attempt   = getOwnAttempt((int) ($_GET['id'] ?? 0), $studentId);
$quiz      = $attempt ? getQuizForStudent((int) $attempt['quiz_id'], $studentId) : null;

if (!$attempt || !$quiz) {
    flash('danger', 'Không tìm thấy lần làm bài hoặc bạn không có quyền truy cập.');
    redirect(url('student/quizzes.php'));
}
$overviewUrl = url('student/quiz.php?id=' . (int) $quiz['id']);

if (!canReviewAttempt($quiz, $attempt)) {
    flash('warning', 'Bạn không được phép xem lại lần làm bài này.');
    redirect($overviewUrl);
}

$questions = attemptQuestions($attempt, $quiz);
$answers   = getAttemptAnswers((int) $attempt['id']);
$grade     = attemptGrade($quiz, $attempt);

$pageTitle = 'Xem lại: ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e($overviewUrl) ?>"><?= e($quiz['title']) ?></a></li>
        <li class="breadcrumb-item active">Xem lại lần <?= (int) $attempt['attempt_no'] ?></li>
    </ol>
</nav>

<div class="alert alert-light border">
    Điểm thô: <strong><?= $attempt['score'] !== null ? e(fmtMarks($attempt['score'])) . '/' . e(fmtMarks($attempt['max_marks'])) : 'chờ chấm tự luận' ?></strong>
    <?php if ($grade !== null): ?> &middot; Điểm: <strong><?= e(fmtMarks($grade)) ?>/<?= e(fmtMarks($quiz['max_grade'])) ?></strong><?php endif; ?>
</div>

<?php foreach ($questions as $i => $q): ?>
    <?php
    $ans    = $answers[(int) $q['id']] ?? null;
    $chosen = $ans && $ans['option_ids'] !== null && $ans['option_ids'] !== '' ? array_map('intval', explode(',', $ans['option_ids'])) : [];
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <span class="fw-semibold">Câu <?= $i + 1 ?></span>
                <span class="badge text-bg-light border">
                    <?= $ans && $ans['points_awarded'] !== null ? e(fmtMarks($ans['points_awarded'])) : 'chờ chấm' ?> / <?= e(fmtMarks($q['points'])) ?>
                </span>
            </div>
            <div class="my-2"><?= nl2br(e($q['text'])) ?></div>

            <?php if ($q['type'] === 'essay'): ?>
                <div class="border rounded p-2 bg-light small"><?= $ans && $ans['answer_text'] !== null ? nl2br(e($ans['answer_text'])) : '<span class="text-muted">Bạn không trả lời câu này.</span>' ?></div>
            <?php else: ?>
                <?php foreach ($q['options'] as $o): ?>
                    <?php $picked = in_array((int) $o['id'], $chosen, true); ?>
                    <div class="<?= $o['is_correct'] ? 'text-success fw-semibold' : ($picked ? 'text-danger' : '') ?>">
                        <i class="bi <?= $picked ? 'bi-check-square-fill' : 'bi-square' ?>"></i>
                        <?= e($o['text']) ?>
                        <?php if ($o['is_correct']): ?><small>(đáp án đúng)</small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($ans && !empty($ans['feedback'])): ?>
                <div class="mt-2 small"><span class="fw-semibold">Nhận xét của giảng viên:</span> <?= nl2br(e($ans['feedback'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<a href="<?= e($overviewUrl) ?>" class="btn btn-outline-secondary mb-4">Quay lại</a>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Trang tổng quan bài kiểm tra (giống màn hình Moodle): thông tin, điểm cuối cùng, các lần làm bài, nút bắt đầu.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('student');

$studentId = (int) currentUser()['id'];
$quizId    = (int) ($_GET['id'] ?? 0);
$quiz      = getQuizForStudent($quizId, $studentId);

if (!$quiz) {
    flash('danger', 'Không tìm thấy bài kiểm tra hoặc bạn chưa ghi danh khóa học này.');
    redirect(url('student/assignments.php'));
}
$selfUrl = url('student/quiz.php?id=' . $quizId);

closeExpiredAttempts($quizId, $studentId);   // tự nộp những lần đã quá hạn

if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($selfUrl);
    }
    $res = startAttempt($quiz, $studentId);
    if ($res['ok']) {
        redirect(url('student/quiz_attempt.php?id=' . (int) $res['attempt_id']));
    }
    flash('danger', $res['error']);
    redirect($selfUrl);
}

$attempts   = getAttempts($quizId, $studentId);
$final      = quizFinalGrade($quiz, $attempts);
$avail      = quizAvailability($quiz);
$inProgress = null;
foreach ($attempts as $a) {
    if ($a['status'] === 'in_progress') {
        $inProgress = $a;
    }
}
$limit      = (int) $quiz['max_attempts'];
$canStart   = $avail === 'open' && ($limit === 0 || count($attempts) < $limit);

$pageTitle = $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('student/assignments.php') ?>">Bài tập & kiểm tra</a></li>
        <li class="breadcrumb-item"><a href="<?= url('course.php?id=' . (int) $quiz['course_id']) ?>"><?= e($quiz['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($quiz['title']) ?></li>
    </ol>
</nav>

<h3 class="mb-3"><?= e($quiz['title']) ?></h3>

<?php if (!empty($quiz['description'])): ?>
    <p><?= nl2br(e($quiz['description'])) ?></p>
<?php endif; ?>

<ul class="small text-muted list-unstyled mb-4">
    <li>Mở lúc: <?= e(fmtDateTime($quiz['open_at'], 'ngay lập tức')) ?> &middot; Đóng lúc: <?= e(fmtDateTime($quiz['close_at'], 'không giới hạn')) ?></li>
    <li>Thời gian làm bài: <?= $quiz['time_limit_min'] ? (int) $quiz['time_limit_min'] . ' phút' : 'không giới hạn' ?></li>
    <li>Số lần làm tối đa: <?= $limit > 0 ? $limit : 'không giới hạn' ?></li>
    <li>Phương pháp tính điểm: <?= e(QUIZ_GRADE_METHODS[$quiz['grade_method']]) ?></li>
</ul>

<?php if ($final !== null): ?>
    <h5 class="text-primary">
        Điểm cuối cùng của bạn cho bài kiểm tra này là <?= e(fmtMarks($final)) ?>/<?= e(fmtMarks($quiz['max_grade'])) ?>.
    </h5>
<?php elseif ($attempts): ?>
    <h5 class="text-muted">Chưa có điểm: bài của bạn đang chờ giảng viên chấm.</h5>
<?php endif; ?>

<?php if ($attempts): ?>
    <h5 class="text-primary mt-4">Các lần làm bài của bạn</h5>
    <div class="row">
        <div class="col-lg-8">
            <?php foreach ($attempts as $a): ?>
                <?php $g = attemptGrade($quiz, $a); ?>
                <div class="card mb-3">
                    <div class="card-header bg-white fw-bold">Lần làm <?= (int) $a['attempt_no'] ?></div>
                    <table class="table table-sm mb-0">
                        <tbody>
                        <tr><th class="w-25 text-end">Trạng thái</th><td><?= attemptStatusBadge($a) ?></td></tr>
                        <tr><th class="text-end">Bắt đầu</th><td><?= e(fmtDateTime($a['started_at'])) ?></td></tr>
                        <tr><th class="text-end">Hoàn thành</th><td><?= e(fmtDateTime($a['finished_at'])) ?></td></tr>
                        <tr><th class="text-end">Thời gian</th><td><?= e(attemptDuration($a)) ?></td></tr>
                        <tr>
                            <th class="text-end">Điểm thô</th>
                            <td><?= $a['score'] !== null ? e(fmtMarks($a['score'])) . '/' . e(fmtMarks($a['max_marks'])) : '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-end">Điểm</th>
                            <td>
                                <?php if ($g !== null): ?>
                                    <strong><?= e(fmtMarks($g)) ?></strong> trên <?= e(fmtMarks($quiz['max_grade'])) ?>
                                    (<strong><?= gradePercent($quiz, $g) ?>%</strong>)
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="card-footer bg-white small">
                        <?php if ($a['status'] === 'in_progress'): ?>
                            <span class="text-muted">Đang làm dở.</span>
                        <?php elseif (canReviewAttempt($quiz, $a)): ?>
                            <a href="<?= url('student/quiz_review.php?id=' . (int) $a['id']) ?>">Xem lại bài làm</a>
                        <?php else: ?>
                            <span class="text-muted">Không được phép xem lại</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="text-center my-4 d-flex justify-content-center gap-2">
    <?php if ($inProgress): ?>
        <form method="post" action="<?= e($selfUrl) ?>"><?= csrfField() ?>
            <button type="submit" class="btn btn-warning">Tiếp tục làm bài</button>
        </form>
    <?php elseif ($canStart): ?>
        <form method="post" action="<?= e($selfUrl) ?>"
              onsubmit="return confirm('<?= $quiz['time_limit_min'] ? 'Đồng hồ sẽ chạy ngay khi bạn bắt đầu (' . (int) $quiz['time_limit_min'] . ' phút). Bắt đầu làm bài?' : 'Bắt đầu làm bài?' ?>');">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-primary"><?= $attempts ? 'Làm lại bài kiểm tra' : 'Bắt đầu làm bài' ?></button>
        </form>
    <?php elseif ($avail === 'not_open'): ?>
        <span class="text-muted">Bài kiểm tra chưa đến giờ mở.</span>
    <?php elseif ($avail === 'closed'): ?>
        <span class="text-muted">Bài kiểm tra đã đóng.</span>
    <?php else: ?>
        <span class="text-muted">Bạn đã dùng hết số lần làm bài.</span>
    <?php endif; ?>
    <a href="<?= url('course.php?id=' . (int) $quiz['course_id']) ?>" class="btn btn-danger">Quay lại khóa học</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

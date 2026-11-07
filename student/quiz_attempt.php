<?php
/**
 * Trang làm bài. Server quyết định hạn chót; JS chỉ hiển thị đồng hồ và tự bấm nộp khi hết giờ.
 * Trang này KHÔNG chứa đáp án đúng.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('student');

header('Cache-Control: no-store');   // nút Back không hiện lại bài đã nộp

$studentId = (int) currentUser()['id'];
$attemptId = (int) ($_GET['id'] ?? 0);
$attempt   = getOwnAttempt($attemptId, $studentId);
$quiz      = $attempt ? getQuizForStudent((int) $attempt['quiz_id'], $studentId) : null;

if (!$attempt || !$quiz) {
    flash('danger', 'Không tìm thấy lần làm bài hoặc bạn không có quyền truy cập.');
    redirect(url('student/assignments.php'));
}
$overviewUrl = url('student/quiz.php?id=' . (int) $quiz['id']);

if ($attempt['status'] !== 'in_progress') {
    flash('info', 'Lần làm bài này đã được nộp.');
    redirect($overviewUrl);
}

if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ hoặc dữ liệu quá lớn. Bài của bạn CHƯA được nộp, hãy thử lại.');
        redirect(url('student/quiz_attempt.php?id=' . $attemptId));
    }
    $answers = $_POST['answers'] ?? [];
    submitAttempt($attemptId, is_array($answers) ? $answers : []);
    flash('success', 'Đã nộp bài kiểm tra.');
    redirect($overviewUrl);
}

if (attemptExpired($attempt, QUIZ_GRACE_SECONDS)) {   // hết giờ mà chưa nộp
    submitAttempt($attemptId, []);
    flash('warning', 'Đã hết thời gian làm bài.');
    redirect($overviewUrl);
}

$questions = attemptQuestions($attempt, $quiz);
$secsLeft  = attemptSecondsLeft($attempt);

$pageTitle = $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 sticky-top bg-body py-2" style="top:0;z-index:5">
    <h4 class="mb-0"><?= e($quiz['title']) ?> <small class="text-muted">· Lần <?= (int) $attempt['attempt_no'] ?></small></h4>
    <?php if ($secsLeft !== null): ?>
        <span class="badge text-bg-danger fs-6" id="timer" aria-live="polite">--:--</span>
    <?php endif; ?>
</div>

<form method="post" id="quizForm" novalidate>
    <?= csrfField() ?>
    <?php foreach ($questions as $i => $q): ?>
        <?php $qid = (int) $q['id']; ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Câu <?= $i + 1 ?></span>
                    <span class="badge text-bg-light border"><?= e(formatScore($q['points'])) ?> điểm</span>
                </div>
                <div class="my-2"><?= nl2br(e($q['text'])) ?></div>

                <?php if ($q['type'] === 'essay'): ?>
                    <textarea class="form-control" name="answers[<?= $qid ?>]" rows="7" maxlength="20000"
                              placeholder="Nhập câu trả lời..."></textarea>
                <?php else: ?>
                    <div class="small text-muted mb-1"><?= $q['type'] === 'single' ? 'Chọn một đáp án' : 'Chọn một hoặc nhiều đáp án' ?></div>
                    <?php foreach ($q['options'] as $o): ?>
                        <div class="form-check">
                            <?php if ($q['type'] === 'single'): ?>
                                <input class="form-check-input" type="radio" name="answers[<?= $qid ?>]"
                                       id="o<?= (int) $o['id'] ?>" value="<?= (int) $o['id'] ?>">
                            <?php else: ?>
                                <input class="form-check-input" type="checkbox" name="answers[<?= $qid ?>][]"
                                       id="o<?= (int) $o['id'] ?>" value="<?= (int) $o['id'] ?>">
                            <?php endif; ?>
                            <label class="form-check-label" for="o<?= (int) $o['id'] ?>"><?= e($o['text']) ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary" id="submitBtn">Nộp bài</button>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('quizForm');
    var submitted = false;
    var left = <?= $secsLeft === null ? 'null' : (int) $secsLeft ?>;

    form.addEventListener('submit', function (e) {
        if (submitted) { e.preventDefault(); return; }       // chống bấm nộp 2 lần
        if (left !== 0 && !confirm('Nộp bài ngay? Bạn không thể sửa sau khi nộp.')) { e.preventDefault(); return; }
        submitted = true;
        document.getElementById('submitBtn').disabled = true;
    });
    window.addEventListener('beforeunload', function (e) {
        if (!submitted) { e.preventDefault(); e.returnValue = ''; }
    });

    if (left === null) { return; }
    var timer = document.getElementById('timer');
    function tick() {
        var m = Math.floor(left / 60), s = left % 60;
        timer.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        if (left <= 0) { submitted = false; form.requestSubmit ? form.requestSubmit() : form.submit(); return; }
        left--;
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

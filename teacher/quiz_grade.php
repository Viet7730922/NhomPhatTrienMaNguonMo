<?php
/** Giảng viên chấm các câu tự luận của một lần làm bài (trắc nghiệm đã được chấm tự động). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

$attemptId = (int) ($_GET['id'] ?? 0);

// Lấy lần làm bài, đồng thời kiểm tra bài kiểm tra có thuộc khóa học của mình không
$stmt = db()->prepare(
    "SELECT a.*, u.full_name, u.email FROM quiz_attempts a
     JOIN users u    ON u.id = a.student_id
     JOIN quizzes q  ON q.id = a.quiz_id
     JOIN courses c  ON c.id = q.course_id
     WHERE a.id = ? AND c.teacher_id = ? AND a.status <> 'in_progress'"
);
$stmt->execute([$attemptId, (int) currentUser()['id']]);
$attempt = $stmt->fetch();
if (!$attempt) {
    flash('danger', 'Không tìm thấy lần làm bài hoặc bạn không có quyền truy cập.');
    redirect(url('teacher/index.php'));
}
$quiz    = requireOwnQuiz((int) $attempt['quiz_id']);
$listUrl = url('teacher/quiz_attempts.php?quiz_id=' . (int) $quiz['id']);

$questions = attemptQuestions($attempt, $quiz);
$answers   = getAttemptAnswers($attemptId);
$errors    = [];

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $pts = is_array($_POST['points'] ?? null) ? $_POST['points'] : [];
        $fbk = is_array($_POST['feedback'] ?? null) ? $_POST['feedback'] : [];
        $upd = [];

        foreach ($questions as $i => $q) {
            $qid = (int) $q['id'];
            if ($q['type'] !== 'essay' || empty($answers[$qid]) || $answers[$qid]['answer_text'] === null) {
                continue;                                        // chỉ chấm tự luận có nội dung
            }
            $raw = is_string($pts[$qid] ?? null) ? str_replace(',', '.', trim($pts[$qid])) : '';
            if ($raw === '' || !is_numeric($raw) || (float) $raw < 0 || (float) $raw > (float) $q['points']) {
                $errors[] = 'Câu ' . ($i + 1) . ': điểm phải từ 0 đến ' . formatScore($q['points']) . '.';
                continue;
            }
            $fb = is_string($fbk[$qid] ?? null) ? trim($fbk[$qid]) : '';
            if (mb_strlen($fb) > 5000) {
                $errors[] = 'Câu ' . ($i + 1) . ': nhận xét tối đa 5000 ký tự.';
                continue;
            }
            $upd[$qid] = [round((float) $raw, 2), $fb !== '' ? $fb : null];
        }

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('UPDATE quiz_answers SET points_awarded = ?, feedback = ? WHERE attempt_id = ? AND question_id = ?');
                foreach ($upd as $qid => [$p, $fb]) {
                    $st->execute([$p, $fb, $attemptId, $qid]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $ex;
            }
            recalcAttempt($attemptId);
            flash('success', 'Đã lưu điểm cho ' . $attempt['full_name'] . '.');
            redirect($listUrl);
        }
    }
}

$pageTitle = 'Chấm bài: ' . $attempt['full_name'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= e($listUrl) ?>"><?= e($quiz['title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($attempt['full_name']) ?> &middot; lần <?= (int) $attempt['attempt_no'] ?></li>
    </ol>
</nav>

<div class="card mb-3"><div class="card-body">
    <h4 class="mb-1"><?= e($attempt['full_name']) ?></h4>
    <div class="text-muted small mb-2"><?= e($attempt['email']) ?></div>
    Nộp lúc <strong><?= e(fmtDateTime($attempt['finished_at'])) ?></strong> (<?= e(attemptDuration($attempt)) ?>) &middot; <?= attemptStatusBadge($attempt) ?>
    <?php if ($attempt['score'] !== null): ?> &middot; Điểm thô <strong><?= e(fmtMarks($attempt['score'])) ?>/<?= e(fmtMarks($attempt['max_marks'])) ?></strong><?php endif; ?>
</div></div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" novalidate>
    <?= csrfField() ?>
    <?php foreach ($questions as $i => $q): ?>
        <?php
        $qid    = (int) $q['id'];
        $ans    = $answers[$qid] ?? null;
        $chosen = $ans && $ans['option_ids'] ? array_map('intval', explode(',', $ans['option_ids'])) : [];
        $gradable = $q['type'] === 'essay' && $ans && $ans['answer_text'] !== null;
        ?>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between">
                <span class="fw-semibold">Câu <?= $i + 1 ?> <small class="text-muted">· <?= e(QUIZ_QUESTION_TYPES[$q['type']]) ?></small></span>
                <span class="badge text-bg-light border">tối đa <?= e(fmtMarks($q['points'])) ?></span>
            </div>
            <div class="my-2"><?= nl2br(e($q['text'])) ?></div>

            <?php if ($q['type'] === 'essay'): ?>
                <div class="border rounded p-2 bg-light small mb-3">
                    <?= $ans && $ans['answer_text'] !== null ? nl2br(e($ans['answer_text'])) : '<span class="text-muted">Sinh viên không trả lời.</span>' ?>
                </div>
                <?php if ($gradable): ?>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small" for="p<?= $qid ?>">Điểm (0 - <?= e(formatScore($q['points'])) ?>)</label>
                            <input type="number" class="form-control" id="p<?= $qid ?>" name="points[<?= $qid ?>]" min="0" max="<?= e((string) $q['points']) ?>" step="0.25"
                                   value="<?= $ans['points_awarded'] !== null ? e(formatScore($ans['points_awarded'])) : '' ?>" required>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small" for="f<?= $qid ?>">Nhận xét</label>
                            <textarea class="form-control" id="f<?= $qid ?>" name="feedback[<?= $qid ?>]" rows="2"><?= e($ans['feedback'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($q['options'] as $o): ?>
                    <?php $picked = in_array((int) $o['id'], $chosen, true); ?>
                    <div class="small <?= $o['is_correct'] ? 'text-success fw-semibold' : ($picked ? 'text-danger' : '') ?>">
                        <i class="bi <?= $picked ? 'bi-check-square-fill' : 'bi-square' ?>"></i> <?= e($o['text']) ?><?= $o['is_correct'] ? ' (đúng)' : '' ?>
                    </div>
                <?php endforeach; ?>
                <div class="small text-muted mt-1">Tự động chấm: <?= $ans && $ans['points_awarded'] !== null ? e(fmtMarks($ans['points_awarded'])) : '0.00' ?> điểm</div>
            <?php endif; ?>
        </div></div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">Lưu điểm</button>
        <a href="<?= e($listUrl) ?>" class="btn btn-outline-secondary">Quay lại danh sách</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>

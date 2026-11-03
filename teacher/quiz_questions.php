<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

$quizId  = (int) ($_GET['quiz_id'] ?? 0);
$quiz    = requireOwnQuiz($quizId);
$selfUrl = url('teacher/quiz_questions.php?quiz_id=' . $quizId);
$locked  = quizHasAttempts($quizId);   // đã có người làm thì khóa cấu trúc đề để điểm không bị sai lệch

if (isPost()) {
    if (!csrfVerify()) {
        flash('danger', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
    } elseif ($locked) {
        flash('danger', 'Bài kiểm tra đã có sinh viên làm nên không thể thêm/xóa/đổi thứ tự câu hỏi.');
    } else {
        $action = $_POST['action'] ?? '';
        $qid    = (int) ($_POST['question_id'] ?? 0);
        if ($action === 'delete') {
            db()->prepare('DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?')->execute([$qid, $quizId]);
            $stmt = db()->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
            $stmt->execute([$quizId]);
            $upd = db()->prepare('UPDATE quiz_questions SET sort_order = ? WHERE id = ?');
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $i => $id) {
                $upd->execute([$i + 1, (int) $id]);
            }
            flash('success', 'Đã xóa câu hỏi.');
        } elseif ($action === 'move') {
            $dir  = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
            $stmt = db()->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
            $stmt->execute([$quizId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $i   = array_search($qid, $ids, true);
            $j   = $i === false ? false : $i + $dir;
            if ($i !== false && $j >= 0 && $j < count($ids)) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                $upd = db()->prepare('UPDATE quiz_questions SET sort_order = ? WHERE id = ? AND quiz_id = ?');
                foreach ($ids as $k => $id) {
                    $upd->execute([$k + 1, $id, $quizId]);
                }
            }
        }
    }
    redirect($selfUrl);
}

$questions = array_values(quizQuestions($quizId));
$total     = array_sum(array_map(fn($q) => (float) $q['points'], $questions));
$last      = count($questions) - 1;

$pageTitle = 'Câu hỏi: ' . $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/quizzes.php?course_id=' . (int) $quiz['course_id']) ?>">Bài kiểm tra: <?= e($quiz['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($quiz['title']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="mb-1"><?= e($quiz['title']) ?></h3>
        <span class="text-muted small"><?= count($questions) ?> câu hỏi &middot; tổng <?= e(formatScore($total)) ?> điểm thô</span>
    </div>
    <?php if (!$locked): ?>
        <a href="<?= url('teacher/question_form.php?quiz_id=' . $quizId) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm câu hỏi</a>
    <?php endif; ?>
</div>

<?php if ($locked): ?>
    <div class="alert alert-warning small">Đã có sinh viên làm bài nên đề được khóa (không thêm, sửa, xóa, đổi thứ tự câu hỏi) để điểm các lần làm không bị sai lệch.</div>
<?php endif; ?>

<?php if (!$questions): ?>
    <div class="alert alert-info">Chưa có câu hỏi nào.</div>
<?php else: ?>
    <div class="card"><ul class="list-group list-group-flush">
    <?php foreach ($questions as $i => $q): ?>
        <li class="list-group-item">
            <div class="d-flex flex-wrap align-items-start gap-2">
                <span class="badge text-bg-light border"><?= $i + 1 ?></span>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= nl2br(e($q['text'])) ?></div>
                    <div class="small text-muted mb-1"><?= e(QUIZ_QUESTION_TYPES[$q['type']]) ?> &middot; <?= e(formatScore($q['points'])) ?> điểm</div>
                    <?php foreach ($q['options'] as $o): ?>
                        <div class="small <?= $o['is_correct'] ? 'text-success fw-semibold' : '' ?>">
                            <i class="bi <?= $o['is_correct'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i> <?= e($o['text']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$locked): ?>
                <div class="d-flex gap-1">
                    <?php foreach ([['up', 'bi-arrow-up', $i === 0], ['down', 'bi-arrow-down', $i === $last]] as [$dir, $icon, $disabled]): ?>
                        <form method="post" class="d-inline"><?= csrfField() ?>
                            <input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="<?= $dir ?>">
                            <input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" <?= $disabled ? 'disabled' : '' ?>><i class="bi <?= $icon ?>"></i></button>
                        </form>
                    <?php endforeach; ?>
                    <a href="<?= url('teacher/question_form.php?id=' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Sửa</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa câu hỏi này?');"><?= csrfField() ?>
                        <input type="hidden" name="action" value="delete"><input type="hidden" name="question_id" value="<?= (int) $q['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

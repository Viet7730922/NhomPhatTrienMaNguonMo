<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

const QUESTION_OPTION_SLOTS = 6;

$questionId = (int) ($_GET['id'] ?? 0);
$question   = null;
if ($questionId > 0) {
    $stmt = db()->prepare(
        'SELECT qq.* FROM quiz_questions qq
         JOIN quizzes q ON q.id = qq.quiz_id JOIN courses c ON c.id = q.course_id
         WHERE qq.id = ? AND c.teacher_id = ?'
    );
    $stmt->execute([$questionId, (int) currentUser()['id']]);
    $question = $stmt->fetch();
    if (!$question) {
        flash('danger', 'Không tìm thấy câu hỏi hoặc bạn không có quyền truy cập.');
        redirect(url('teacher/index.php'));
    }
}
$quizId  = $question ? (int) $question['quiz_id'] : (int) ($_GET['quiz_id'] ?? 0);
$quiz    = requireOwnQuiz($quizId);
$isEdit  = $question !== null;
$backUrl = url('teacher/quiz_questions.php?quiz_id=' . $quizId);

if (quizHasAttempts($quizId)) {
    flash('danger', 'Bài kiểm tra đã có sinh viên làm nên không thể sửa câu hỏi.');
    redirect($backUrl);
}

$errors = [];
$type   = $question['type'] ?? 'single';
$text   = $question['text'] ?? '';
$points = isset($question['points']) ? formatScore($question['points']) : '1';
$opts   = [];                                   // [['text' => ..., 'correct' => bool], ...]
if ($isEdit) {
    foreach (quizQuestions($quizId)[$questionId]['options'] as $o) {
        $opts[] = ['text' => $o['text'], 'correct' => (bool) $o['is_correct']];
    }
}

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $type   = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        $text   = is_string($_POST['text'] ?? null) ? trim($_POST['text']) : '';
        $points = is_string($_POST['points'] ?? null) ? str_replace(',', '.', trim($_POST['points'])) : '';
        $texts  = is_array($_POST['opt_text'] ?? null) ? $_POST['opt_text'] : [];
        $marks  = is_array($_POST['opt_correct'] ?? null) ? array_map('intval', array_filter($_POST['opt_correct'], 'is_scalar')) : [];

        $opts = [];
        for ($i = 0; $i < QUESTION_OPTION_SLOTS; $i++) {
            $t = is_string($texts[$i] ?? null) ? trim($texts[$i]) : '';
            if ($t !== '') {
                if (mb_strlen($t) > 500) {
                    $errors[] = 'Mỗi đáp án tối đa 500 ký tự.';
                }
                $opts[] = ['text' => $t, 'correct' => in_array($i, $marks, true)];
            }
        }
        $correctCount = count(array_filter($opts, fn($o) => $o['correct']));

        if (!isset(QUIZ_QUESTION_TYPES[$type])) {
            $errors[] = 'Loại câu hỏi không hợp lệ.';
        }
        if ($text === '' || mb_strlen($text) > 5000) {
            $errors[] = 'Nội dung câu hỏi không được để trống và tối đa 5000 ký tự.';
        }
        if (!is_numeric($points) || (float) $points <= 0 || (float) $points > 100) {
            $errors[] = 'Điểm của câu hỏi phải lớn hơn 0 và không quá 100.';
        }
        if ($type === 'single' || $type === 'multiple') {
            if (count($opts) < 2) {
                $errors[] = 'Câu trắc nghiệm cần ít nhất 2 đáp án.';
            }
            if ($type === 'single' && $correctCount !== 1) {
                $errors[] = 'Câu một đáp án phải có đúng 1 đáp án đúng.';
            }
            if ($type === 'multiple' && $correctCount < 1) {
                $errors[] = 'Câu nhiều đáp án phải có ít nhất 1 đáp án đúng.';
            }
        } else {
            $opts = [];
        }

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                if ($isEdit) {
                    $pdo->prepare('UPDATE quiz_questions SET type = ?, text = ?, points = ? WHERE id = ? AND quiz_id = ?')
                        ->execute([$type, $text, round((float) $points, 2), $questionId, $quizId]);
                    $pdo->prepare('DELETE FROM quiz_options WHERE question_id = ?')->execute([$questionId]);
                } else {
                    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM quiz_questions WHERE quiz_id = ?');
                    $stmt->execute([$quizId]);
                    $pdo->prepare('INSERT INTO quiz_questions (quiz_id, type, text, points, sort_order) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$quizId, $type, $text, round((float) $points, 2), (int) $stmt->fetchColumn()]);
                    $questionId = (int) $pdo->lastInsertId();
                }
                $ins = $pdo->prepare('INSERT INTO quiz_options (question_id, text, is_correct) VALUES (?, ?, ?)');
                foreach ($opts as $o) {
                    $ins->execute([$questionId, $o['text'], $o['correct'] ? 1 : 0]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $ex;
            }
            flash('success', $isEdit ? 'Đã cập nhật câu hỏi.' : 'Đã thêm câu hỏi.');
            redirect(isset($_POST['add_more']) ? url('teacher/question_form.php?quiz_id=' . $quizId) : $backUrl);
        }
    }
}
while (count($opts) < QUESTION_OPTION_SLOTS) {
    $opts[] = ['text' => '', 'correct' => false];
}

$pageTitle = $isEdit ? 'Sửa câu hỏi' : 'Thêm câu hỏi';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= e($backUrl) ?>"><?= e($quiz['title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>

<div class="row justify-content-center"><div class="col-lg-8"><div class="card"><div class="card-body p-4">
    <h4 class="mb-4"><?= e($pageTitle) ?></h4>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" novalidate>
        <?= csrfField() ?>
        <div class="row">
            <div class="col-md-8 mb-3"><label class="form-label" for="type">Loại câu hỏi</label>
                <select class="form-select" id="type" name="type">
                    <?php foreach (QUIZ_QUESTION_TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="points">Điểm</label>
                <input type="number" class="form-control" id="points" name="points" min="0.25" max="100" step="0.25" value="<?= e($points) ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label" for="text">Nội dung câu hỏi <span class="text-danger">*</span></label>
            <textarea class="form-control" id="text" name="text" rows="4" required><?= e($text) ?></textarea></div>

        <div id="optionsBox" class="mb-4">
            <div class="form-label">Đáp án (tick ô bên trái cho đáp án đúng; để trống ô không dùng)</div>
            <?php foreach ($opts as $i => $o): ?>
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="checkbox" name="opt_correct[]" value="<?= $i ?>" <?= $o['correct'] ? 'checked' : '' ?> aria-label="Đáp án đúng">
                    </div>
                    <input type="text" class="form-control" name="opt_text[<?= $i ?>]" maxlength="500" value="<?= e($o['text']) ?>" placeholder="Đáp án <?= $i + 1 ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Lưu</button>
            <?php if (!$isEdit): ?><button type="submit" name="add_more" value="1" class="btn btn-outline-primary">Lưu và thêm câu tiếp</button><?php endif; ?>
            <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Hủy</a>
        </div>
    </form>
</div></div></div></div>

<script>
(function () {
    var type = document.getElementById('type'), box = document.getElementById('optionsBox');
    function sync() { box.style.display = type.value === 'essay' ? 'none' : ''; }
    type.addEventListener('change', sync); sync();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

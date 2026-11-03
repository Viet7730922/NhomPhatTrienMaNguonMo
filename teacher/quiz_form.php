<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/quiz.php';
requireRole('teacher');

$quizId   = (int) ($_GET['id'] ?? 0);
$quiz     = $quizId > 0 ? requireOwnQuiz($quizId) : null;
$isEdit   = $quiz !== null;
$courseId = $isEdit ? (int) $quiz['course_id'] : (int) ($_GET['course_id'] ?? 0);
$course   = requireOwnCourse($courseId);
$backUrl  = url('teacher/quizzes.php?course_id=' . $courseId);
$errors   = [];

$questionCount = 0;
if ($isEdit) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?');
    $stmt->execute([$quizId]);
    $questionCount = (int) $stmt->fetchColumn();
}

$toInput = fn(?string $dt) => $dt ? date('Y-m-d\TH:i', strtotime($dt)) : '';
$f = [
    'title'          => $quiz['title'] ?? '',
    'description'    => $quiz['description'] ?? '',
    'time_limit_min' => $quiz['time_limit_min'] ?? '',
    'open_at'        => $toInput($quiz['open_at'] ?? null),
    'close_at'       => $toInput($quiz['close_at'] ?? null),
    'max_attempts'   => $quiz['max_attempts'] ?? 1,
    'grade_method'   => $quiz['grade_method'] ?? 'highest',
    'max_grade'      => isset($quiz['max_grade']) ? formatScore($quiz['max_grade']) : '10',
    'shuffle'        => (int) ($quiz['shuffle'] ?? 0),
    'review_mode'    => $quiz['review_mode'] ?? 'after_close',
    'is_published'   => (int) ($quiz['is_published'] ?? 0),
];

/** 'Y-m-d\TH:i' -> 'Y-m-d H:i:00'; '' -> null; sai định dạng -> false */
function parseLocalDateTime(string $s)
{
    if ($s === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $s);
    return ($dt && $dt->format('Y-m-d\TH:i') === $s) ? $dt->format('Y-m-d H:i:00') : false;
}

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        foreach (['title', 'description', 'time_limit_min', 'open_at', 'close_at', 'max_attempts', 'grade_method', 'max_grade', 'review_mode'] as $k) {
            $f[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        }
        $f['max_grade']    = str_replace(',', '.', $f['max_grade']);
        $f['shuffle']      = isset($_POST['shuffle']) ? 1 : 0;
        $f['is_published'] = isset($_POST['is_published']) ? 1 : 0;

        if ($f['title'] === '' || mb_strlen($f['title']) > 200) {
            $errors[] = 'Tiêu đề không được để trống và tối đa 200 ký tự.';
        }
        $timeLimit = null;
        if ($f['time_limit_min'] !== '') {
            if (!ctype_digit($f['time_limit_min']) || (int) $f['time_limit_min'] < 1 || (int) $f['time_limit_min'] > 600) {
                $errors[] = 'Thời gian làm bài phải từ 1 đến 600 phút (để trống nếu không giới hạn).';
            } else {
                $timeLimit = (int) $f['time_limit_min'];
            }
        }
        $open  = parseLocalDateTime($f['open_at']);
        $close = parseLocalDateTime($f['close_at']);
        if ($open === false || $close === false) {
            $errors[] = 'Thời gian mở/đóng không hợp lệ.';
        } elseif ($open && $close && $close <= $open) {
            $errors[] = 'Thời gian đóng phải sau thời gian mở.';
        }
        if (!ctype_digit($f['max_attempts']) || (int) $f['max_attempts'] > 20) {
            $errors[] = 'Số lần làm tối đa từ 0 đến 20 (0 = không giới hạn).';
        }
        if (!isset(QUIZ_GRADE_METHODS[$f['grade_method']])) {
            $errors[] = 'Phương pháp tính điểm không hợp lệ.';
        }
        if (!isset(QUIZ_REVIEW_MODES[$f['review_mode']])) {
            $errors[] = 'Chế độ xem lại không hợp lệ.';
        }
        if (!is_numeric($f['max_grade']) || (float) $f['max_grade'] <= 0 || (float) $f['max_grade'] > 100) {
            $errors[] = 'Thang điểm phải lớn hơn 0 và không quá 100.';
        }
        if ($f['is_published'] && $questionCount === 0) {
            $errors[] = 'Cần thêm ít nhất một câu hỏi trước khi công khai bài kiểm tra.';
        }

        if (!$errors) {
            $vals = [$f['title'], $f['description'], $timeLimit, $open, $close, (int) $f['max_attempts'],
                     $f['grade_method'], round((float) $f['max_grade'], 2), $f['shuffle'], $f['review_mode'], $f['is_published']];
            if ($isEdit) {
                db()->prepare(
                    'UPDATE quizzes SET title=?, description=?, time_limit_min=?, open_at=?, close_at=?, max_attempts=?,
                            grade_method=?, max_grade=?, shuffle=?, review_mode=?, is_published=? WHERE id=? AND course_id=?'
                )->execute(array_merge($vals, [$quizId, $courseId]));
                flash('success', 'Đã cập nhật bài kiểm tra.');
                redirect($backUrl);
            }
            db()->prepare(
                'INSERT INTO quizzes (title, description, time_limit_min, open_at, close_at, max_attempts,
                                      grade_method, max_grade, shuffle, review_mode, is_published, course_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute(array_merge($vals, [$courseId]));
            flash('success', 'Đã tạo bài kiểm tra. Hãy thêm câu hỏi.');
            redirect(url('teacher/quiz_questions.php?quiz_id=' . (int) db()->lastInsertId()));
        }
    }
}

$pageTitle = $isEdit ? 'Sửa bài kiểm tra' : 'Tạo bài kiểm tra';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= e($backUrl) ?>">Bài kiểm tra: <?= e($course['title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>

<div class="row justify-content-center"><div class="col-lg-8"><div class="card"><div class="card-body p-4">
    <h4 class="mb-4"><?= e($pageTitle) ?></h4>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" novalidate>
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label" for="title">Tiêu đề <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="title" name="title" maxlength="200" value="<?= e($f['title']) ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="description">Mô tả / hướng dẫn</label>
            <textarea class="form-control" id="description" name="description" rows="4"><?= e($f['description']) ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label" for="open_at">Mở lúc (để trống = mở ngay)</label>
                <input type="datetime-local" class="form-control" id="open_at" name="open_at" value="<?= e($f['open_at']) ?>"></div>
            <div class="col-md-6 mb-3"><label class="form-label" for="close_at">Đóng lúc (để trống = không đóng)</label>
                <input type="datetime-local" class="form-control" id="close_at" name="close_at" value="<?= e($f['close_at']) ?>"></div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label" for="time_limit_min">Thời gian làm (phút)</label>
                <input type="number" class="form-control" id="time_limit_min" name="time_limit_min" min="1" max="600" value="<?= e((string) $f['time_limit_min']) ?>" placeholder="Không giới hạn"></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="max_attempts">Số lần làm tối đa (0 = vô hạn)</label>
                <input type="number" class="form-control" id="max_attempts" name="max_attempts" min="0" max="20" value="<?= e((string) $f['max_attempts']) ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label" for="max_grade">Thang điểm</label>
                <input type="number" class="form-control" id="max_grade" name="max_grade" min="1" max="100" step="0.25" value="<?= e((string) $f['max_grade']) ?>"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label" for="grade_method">Phương pháp tính điểm</label>
                <select class="form-select" id="grade_method" name="grade_method">
                    <?php foreach (QUIZ_GRADE_METHODS as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['grade_method'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-6 mb-3"><label class="form-label" for="review_mode">Xem lại bài làm</label>
                <select class="form-select" id="review_mode" name="review_mode">
                    <?php foreach (QUIZ_REVIEW_MODES as $k => $l): ?><option value="<?= e($k) ?>" <?= $f['review_mode'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="shuffle" name="shuffle" <?= $f['shuffle'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="shuffle">Xáo trộn thứ tự câu hỏi và đáp án (mỗi lần làm một khác)</label>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="is_published" name="is_published"
                   <?= $f['is_published'] ? 'checked' : '' ?> <?= $questionCount === 0 ? 'disabled' : '' ?>>
            <label class="form-check-label" for="is_published">Công khai cho sinh viên
                <?= $questionCount === 0 ? '<span class="text-muted small">(thêm câu hỏi trước)</span>' : '' ?></label>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo bài kiểm tra' ?></button>
            <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Hủy</a>
        </div>
    </form>
</div></div></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

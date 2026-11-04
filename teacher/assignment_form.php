<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/teacher.php';
require_once __DIR__ . '/../includes/assignments.php';
require_once __DIR__ . '/../includes/notify.php';
requireRole('teacher');

$assignmentId = (int) ($_GET['id'] ?? 0);
$assignment   = $assignmentId > 0 ? requireOwnAssignment($assignmentId) : null;
$isEdit       = $assignment !== null;
$courseId     = $isEdit ? (int) $assignment['course_id'] : (int) ($_GET['course_id'] ?? 0);
$course       = requireOwnCourse($courseId);
$backUrl      = url('teacher/assignments.php?course_id=' . $courseId);
$errors       = [];

// Điểm cao nhất đã chấm (không cho hạ điểm tối đa xuống thấp hơn)
$highestGiven = 0.0;
if ($isEdit) {
    $stmt = db()->prepare('SELECT MAX(score) FROM submissions WHERE assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $highestGiven = (float) $stmt->fetchColumn();
}

$title       = $assignment['title'] ?? '';
$description = $assignment['description'] ?? '';
$dueInput    = !empty($assignment['due_date']) ? date('Y-m-d\TH:i', strtotime($assignment['due_date'])) : '';
$maxScore    = isset($assignment['max_score']) ? formatScore($assignment['max_score']) : '10';

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueInput    = trim($_POST['due_date'] ?? '');
        $maxScore    = str_replace(',', '.', trim($_POST['max_score'] ?? ''));

        if ($title === '' || mb_strlen($title) > 200) {
            $errors[] = 'Tiêu đề không được để trống và tối đa 200 ký tự.';
        }

        $dueValue = null;
        if ($dueInput !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $dueInput);
            if (!$dt || $dt->format('Y-m-d\TH:i') !== $dueInput) {
                $errors[] = 'Hạn nộp không hợp lệ.';
            } else {
                $dueValue = $dt->format('Y-m-d H:i:00');
            }
        }

        if (!is_numeric($maxScore) || (float) $maxScore <= 0 || (float) $maxScore > 100) {
            $errors[] = 'Điểm tối đa phải là số lớn hơn 0 và không quá 100.';
        } elseif ((float) $maxScore < $highestGiven) {
            $errors[] = 'Đã có bài được chấm ' . formatScore($highestGiven)
                      . ' điểm, nên điểm tối đa không thể thấp hơn mức này.';
        }

        if (!$errors) {
            $max = round((float) $maxScore, 2);
            if ($isEdit) {
                $stmt = db()->prepare(
                    'UPDATE assignments SET title = ?, description = ?, due_date = ?, max_score = ?
                     WHERE id = ? AND course_id = ?'
                );
                $stmt->execute([$title, $description, $dueValue, $max, $assignmentId, $courseId]);
                flash('success', 'Đã cập nhật bài tập.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$courseId, $title, $description, $dueValue, $max]);
                $newId = (int) db()->lastInsertId();
                flash('success', 'Đã tạo bài tập.');
                notifyNewAssignment($newId);   // gửi email cho sinh viên đã ghi danh
            }
            redirect($backUrl);
        }
    }
}

$pageTitle = $isEdit ? 'Sửa bài tập' : 'Tạo bài tập';
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= e($backUrl) ?>">Bài tập: <?= e($course['title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-4"><?= e($pageTitle) ?></h4>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label" for="title">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="200"
                               value="<?= e($title) ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Yêu cầu / mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="8"><?= e($description) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label" for="due_date">Hạn nộp (để trống nếu không giới hạn)</label>
                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" value="<?= e($dueInput) ?>">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label" for="max_score">Điểm tối đa</label>
                            <input type="number" class="form-control" id="max_score" name="max_score"
                                   min="0.25" max="100" step="0.25" value="<?= e($maxScore) ?>" required>
                        </div>
                    </div>

                    <div class="alert alert-light border small mb-4">
                        <strong>Quy định nộp bài của sinh viên:</strong>
                        tối đa <?= SUBMISSION_MAX_FILES ?> file mỗi lần nộp, mỗi file không quá
                        <?= (int) (SUBMISSION_MAX_SIZE / 1048576) ?> MB.<br>
                        Định dạng cho phép: <?= e(implode(', ', SUBMISSION_ALLOWED_EXT)) ?>.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Lưu thay đổi' : 'Tạo bài tập' ?></button>
                        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

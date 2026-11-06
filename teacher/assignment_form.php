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
$submissionMode = $assignment['submission_mode'] ?? 'file';
$hasSubmissions = false;

// Điểm cao nhất đã chấm (không cho hạ điểm tối đa xuống thấp hơn)
$highestGiven = 0.0;
if ($isEdit) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?');
    $stmt->execute([$assignmentId]);
    $hasSubmissions = (int) $stmt->fetchColumn() > 0;

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
        $requestedMode = trim($_POST['submission_mode'] ?? $submissionMode);

        if (!in_array($requestedMode, ['file', 'online_text', 'both'], true)) {
            $errors[] = 'Hình thức nộp bài không hợp lệ.';
        } elseif ($hasSubmissions && $requestedMode !== ($assignment['submission_mode'] ?? 'both')) {
            $errors[] = 'Không thể đổi hình thức nộp bài sau khi đã có sinh viên nộp bài.';
        } else {
            $submissionMode = $requestedMode;
        }

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
                    'UPDATE assignments SET title = ?, description = ?, due_date = ?, max_score = ?, submission_mode = ?
                     WHERE id = ? AND course_id = ?'
                );
                $stmt->execute([$title, $description, $dueValue, $max, $submissionMode, $assignmentId, $courseId]);
                flash('success', 'Đã cập nhật bài tập.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO assignments (course_id, title, description, due_date, max_score, submission_mode)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$courseId, $title, $description, $dueValue, $max, $submissionMode]);
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

                    <div class="mb-3">
                        <label class="form-label" for="submission_mode">Hình thức nộp bài</label>
                        <select class="form-select" id="submission_mode" name="submission_mode" <?= $hasSubmissions ? 'disabled' : '' ?>>
                            <option value="file" <?= $submissionMode === 'file' ? 'selected' : '' ?>>Nộp file</option>
                            <option value="online_text" <?= $submissionMode === 'online_text' ? 'selected' : '' ?>>Online text (soạn thảo trực tuyến)</option>
                            <option value="both" <?= $submissionMode === 'both' ? 'selected' : '' ?>>Cả file và Online text</option>
                        </select>
                        <?php if ($hasSubmissions): ?>
                            <input type="hidden" name="submission_mode" value="<?= e($submissionMode) ?>">
                            <div class="form-text">Không thể đổi hình thức sau khi sinh viên đã nộp bài.</div>
                        <?php else: ?>
                            <div class="form-text">Online text cho phép sinh viên soạn nội dung trực tiếp bằng trình soạn thảo định dạng.</div>
                        <?php endif; ?>
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

                    <?php if ($submissionMode !== 'online_text'): ?>
                        <div class="alert alert-light border small mb-4" id="submission-file-policy" data-file-policy>
                            <strong>Quy định nộp file:</strong>
                            tối đa <?= SUBMISSION_MAX_FILES ?> file mỗi lần nộp, mỗi file không quá
                            <?= (int) (SUBMISSION_MAX_SIZE / 1048576) ?> MB.<br>
                            Định dạng cho phép: <?= e(implode(', ', SUBMISSION_ALLOWED_EXT)) ?>.
                        </div>
                    <?php endif; ?>

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

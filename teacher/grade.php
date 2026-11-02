<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assignments.php';
requireRole('teacher');

$submissionId = (int) ($_GET['id'] ?? 0);

// Lấy bài nộp, đồng thời kiểm tra khóa học chứa nó có phải của mình không
$sql = "SELECT s.*, u.full_name, u.email,
               a.title AS assignment_title, a.description AS assignment_description,
               a.due_date, a.max_score, a.course_id, c.title AS course_title
        FROM submissions s
        JOIN users u       ON u.id = s.student_id
        JOIN assignments a ON a.id = s.assignment_id
        JOIN courses c     ON c.id = a.course_id
        WHERE s.id = ? AND c.teacher_id = ?";
$stmt = db()->prepare($sql);
$stmt->execute([$submissionId, (int) currentUser()['id']]);
$sub = $stmt->fetch();

if (!$sub) {
    flash('danger', 'Không tìm thấy bài nộp hoặc bạn không có quyền truy cập.');
    redirect(url('teacher/index.php'));
}

$assignmentId = (int) $sub['assignment_id'];
$listUrl      = url('teacher/submissions.php?assignment_id=' . $assignmentId);
$maxScore     = (float) $sub['max_score'];
$errors       = [];

$scoreInput = $sub['score'] !== null ? formatScore($sub['score']) : '';
$feedback   = $sub['feedback'] ?? '';

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
    } else {
        $scoreInput = str_replace(',', '.', trim($_POST['score'] ?? ''));
        $feedback   = trim($_POST['feedback'] ?? '');

        if ($scoreInput === '' || !is_numeric($scoreInput)) {
            $errors[] = 'Vui lòng nhập điểm hợp lệ.';
        } elseif ((float) $scoreInput < 0 || (float) $scoreInput > $maxScore) {
            $errors[] = 'Điểm phải nằm trong khoảng từ 0 đến ' . formatScore($maxScore) . '.';
        }
        if (mb_strlen($feedback) > 5000) {
            $errors[] = 'Nhận xét tối đa 5000 ký tự.';
        }

        if (!$errors) {
            $stmt = db()->prepare('UPDATE submissions SET score = ?, feedback = ?, graded_at = NOW() WHERE id = ?');
            $stmt->execute([round((float) $scoreInput, 2), $feedback !== '' ? $feedback : null, $submissionId]);
            flash('success', 'Đã lưu điểm cho ' . $sub['full_name'] . '.');

            // "Lưu và chấm bài tiếp": chuyển sang bài chờ chấm kế tiếp
            if (isset($_POST['next'])) {
                $stmt = db()->prepare(
                    'SELECT id FROM submissions WHERE assignment_id = ? AND score IS NULL AND id <> ?
                     ORDER BY submitted_at, id LIMIT 1'
                );
                $stmt->execute([$assignmentId, $submissionId]);
                $nextId = $stmt->fetchColumn();
                if ($nextId) {
                    redirect(url('teacher/grade.php?id=' . (int) $nextId));
                }
                flash('info', 'Đã chấm hết các bài chờ chấm.');
            }
            redirect($listUrl);
        }
    }
}

$files = getSubmissionFiles($submissionId);
$late  = isLateSubmission($sub, $sub['submitted_at']);

$pageTitle = 'Chấm bài: ' . $sub['full_name'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('teacher/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('teacher/assignments.php?course_id=' . (int) $sub['course_id']) ?>">Bài tập: <?= e($sub['course_title']) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= e($listUrl) ?>"><?= e($sub['assignment_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($sub['full_name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Cột trái: nội dung bài nộp -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="mb-1"><?= e($sub['full_name']) ?></h4>
                <div class="text-muted small mb-3"><?= e($sub['email']) ?></div>
                <p class="mb-0">
                    Nộp lúc: <strong><?= e(fmtDateTime($sub['submitted_at'])) ?></strong>
                    <?php if ($late): ?><span class="badge text-bg-danger ms-1">Nộp trễ</span><?php endif; ?>
                    <br>
                    <span class="text-muted small">Hạn nộp: <?= e(fmtDateTime($sub['due_date'], 'Không giới hạn')) ?></span>
                </p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">File đã nộp (<?= count($files) ?>)</div>
            <?php if (!$files): ?>
                <div class="card-body text-muted">Học viên không đính kèm file.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($files as $f): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-file-earmark-code"></i>
                                <?= e($f['original_name']) ?>
                                <span class="text-muted small">(<?= e(formatFileSize((int) $f['file_size'])) ?>)</span>
                            </span>
                            <a href="<?= url('teacher/download.php?id=' . (int) $f['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i> Tải về
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-bold">Ghi chú của học viên</div>
            <div class="card-body">
                <?php if (!empty($sub['answer_text'])): ?>
                    <?= nl2br(e($sub['answer_text'])) ?>
                <?php else: ?>
                    <span class="text-muted">Không có ghi chú.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cột phải: chấm điểm -->
    <div class="col-lg-5">
        <div class="card sticky-top" style="top: 1rem">
            <div class="card-body">
                <h5 class="mb-3">Chấm điểm</h5>

                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <?php if ($sub['graded_at']): ?>
                    <div class="alert alert-success py-2 small">
                        Đã chấm lúc <?= e(fmtDateTime($sub['graded_at'])) ?>. Bạn có thể sửa lại bên dưới.
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="score">Điểm (0 - <?= e(formatScore($maxScore)) ?>)</label>
                        <input type="number" class="form-control" id="score" name="score" min="0"
                               max="<?= e((string) $maxScore) ?>" step="0.25" value="<?= e($scoreInput) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="feedback">Nhận xét</label>
                        <textarea class="form-control" id="feedback" name="feedback" rows="6"><?= e($feedback) ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Lưu điểm</button>
                        <button type="submit" name="next" value="1" class="btn btn-outline-primary">Lưu và chấm bài tiếp</button>
                        <a href="<?= e($listUrl) ?>" class="btn btn-outline-secondary">Quay lại danh sách</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

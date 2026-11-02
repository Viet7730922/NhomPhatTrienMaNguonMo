<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student.php';
requireRole('student');

$studentId    = (int) currentUser()['id'];
$assignmentId = (int) ($_GET['id'] ?? 0);
$selfUrl      = url('student/assignment.php?id=' . $assignmentId);

// Chỉ xem được bài tập của khóa học mình đã ghi danh
$stmt = db()->prepare(
    'SELECT a.*, c.title AS course_title
     FROM assignments a
     JOIN courses c ON c.id = a.course_id
     JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
     WHERE a.id = ?'
);
$stmt->execute([$studentId, $assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    flash('danger', 'Không tìm thấy bài tập hoặc bạn chưa ghi danh khóa học này.');
    redirect(url('student/assignments.php'));
}

$stmt = db()->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?');
$stmt->execute([$assignmentId, $studentId]);
$sub   = $stmt->fetch() ?: null;
$files = $sub ? getSubmissionFiles((int) $sub['id']) : [];

$locked  = $sub !== null && $sub['score'] !== null;      // đã chấm thì khóa, không sửa được
$due     = $assignment['due_date'] ? strtotime($assignment['due_date']) : null;
$pastDue = $due !== null && time() > $due;

$errors    = [];
$noteInput = $sub['answer_text'] ?? '';

if (isPost()) {
    if (!csrfVerify()) {
        $errors[] = 'Phiên làm việc không hợp lệ (hoặc tổng dung lượng tệp quá lớn). Vui lòng thử lại.';
    } elseif ($locked) {
        $errors[] = 'Bài nộp đã được chấm điểm nên không thể chỉnh sửa.';
    } else {
        $noteInput = trim($_POST['answer_text'] ?? '');
        if (mb_strlen($noteInput) > 5000) {
            $errors[] = 'Ghi chú tối đa 5000 ký tự.';
        }

        $existingIds = array_map('intval', array_column($files, 'id'));
        $removeIds   = array_values(array_intersect($existingIds, array_map('intval', (array) ($_POST['remove_files'] ?? []))));
        $newFiles    = normalizeUploadedFiles($_FILES['files'] ?? null);
        $keepCount   = count($existingIds) - count($removeIds);

        if ($keepCount + count($newFiles) > SUBMISSION_MAX_FILES) {
            $errors[] = 'Mỗi bài nộp tối đa ' . SUBMISSION_MAX_FILES . ' file.';
        }
        foreach ($newFiles as $f) {
            $err = validateSubmissionFile($f);
            if ($err !== null) {
                $errors[] = $err;
            }
        }
        if ($keepCount + count($newFiles) === 0 && $noteInput === '') {
            $errors[] = 'Vui lòng đính kèm ít nhất một file hoặc nhập ghi chú.';
        }

        if (!$errors) {
            $stored   = [];     // file mới đã ghi lên đĩa
            $toUnlink = [];     // file cũ cần xóa khỏi đĩa sau khi lưu CSDL thành công
            $pdo      = db();
            try {
                foreach ($newFiles as $f) {
                    $stored[] = storeSubmissionFile($f);
                }

                $pdo->beginTransaction();

                // Khóa dòng để kiểm tra lại trạng thái chấm điểm ngay trong giao dịch
                $stmt = $pdo->prepare('SELECT id, score FROM submissions WHERE assignment_id = ? AND student_id = ? FOR UPDATE');
                $stmt->execute([$assignmentId, $studentId]);
                $row = $stmt->fetch();

                if ($row && $row['score'] !== null) {
                    throw new RuntimeException('LOCKED');
                }

                if ($row) {
                    $subId = (int) $row['id'];
                    $pdo->prepare('UPDATE submissions SET answer_text = ?, submitted_at = NOW() WHERE id = ?')
                        ->execute([$noteInput !== '' ? $noteInput : null, $subId]);
                } else {
                    $pdo->prepare('INSERT INTO submissions (assignment_id, student_id, answer_text) VALUES (?, ?, ?)')
                        ->execute([$assignmentId, $studentId, $noteInput !== '' ? $noteInput : null]);
                    $subId = (int) $pdo->lastInsertId();
                }

                foreach ($removeIds as $rid) {
                    $q = $pdo->prepare('SELECT stored_name FROM submission_files WHERE id = ? AND submission_id = ?');
                    $q->execute([$rid, $subId]);
                    $name = $q->fetchColumn();
                    if ($name !== false) {
                        $toUnlink[] = $name;
                        $pdo->prepare('DELETE FROM submission_files WHERE id = ? AND submission_id = ?')->execute([$rid, $subId]);
                    }
                }

                $ins = $pdo->prepare(
                    'INSERT INTO submission_files (submission_id, original_name, stored_name, file_size) VALUES (?, ?, ?, ?)'
                );
                foreach ($stored as $s) {
                    $ins->execute([$subId, $s['original_name'], $s['stored_name'], $s['file_size']]);
                }

                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                deleteSubmissionFiles(array_column($stored, 'stored_name'));   // dọn file mới đã lỡ ghi
                $toUnlink = [];
                if ($ex->getMessage() === 'LOCKED') {
                    $errors[] = 'Bài nộp đã được chấm điểm nên không thể chỉnh sửa.';
                } else {
                    $errors[] = DEBUG ? 'Lỗi: ' . $ex->getMessage() : 'Không thể lưu bài nộp, vui lòng thử lại.';
                }
            }

            if (!$errors) {
                deleteSubmissionFiles($toUnlink);
                flash('success', $sub ? 'Đã cập nhật bài nộp.' : 'Nộp bài thành công.');
                redirect($selfUrl);
            }
        }
    }
}

$maxNew = max(0, SUBMISSION_MAX_FILES - count($files));
$accept = implode(',', array_map(fn($x) => '.' . $x, SUBMISSION_ALLOWED_EXT));

$pageTitle = $assignment['title'];
require __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= url('student/index.php') ?>">Khóa học của tôi</a></li>
        <li class="breadcrumb-item"><a href="<?= url('student/assignments.php?course_id=' . (int) $assignment['course_id']) ?>">Bài tập: <?= e($assignment['course_title']) ?></a></li>
        <li class="breadcrumb-item active"><?= e($assignment['title']) ?></li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <h3 class="mb-3"><?= e($assignment['title']) ?></h3>

        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-2">
                    <strong>Hạn nộp:</strong> <?= e(fmtDateTime($assignment['due_date'], 'Không giới hạn')) ?>
                    &middot; <strong>Điểm tối đa:</strong> <?= e(formatScore($assignment['max_score'])) ?>
                </div>
                <?php if (!empty($assignment['description'])): ?>
                    <hr class="my-2">
                    <div><?= nl2br(e($assignment['description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tình trạng bài nộp -->
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold">Tình trạng bài nộp</div>
            <table class="table mb-0">
                <tbody>
                    <tr>
                        <th class="w-25">Trạng thái nộp</th>
                        <td>
                            <?php if (!$sub): ?>
                                <span class="badge text-bg-secondary">Chưa nộp</span>
                            <?php else: ?>
                                <span class="badge text-bg-info">Đã nộp để chấm điểm</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Trạng thái chấm</th>
                        <td>
                            <?= $locked ? '<span class="badge text-bg-success">Đã chấm</span>' : '<span class="badge text-bg-secondary">Chưa chấm</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Thời gian</th>
                        <td>
                            <?php if ($sub): ?>
                                <?php if ($due === null): ?>
                                    Đã nộp
                                <?php else: ?>
                                    <?php $diff = $due - strtotime($sub['submitted_at']); ?>
                                    <?php if ($diff >= 0): ?>
                                        <span class="text-success">Nộp sớm hơn hạn <?= e(humanDuration($diff)) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">Nộp trễ <?= e(humanDuration($diff)) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php elseif ($due === null): ?>
                                Không giới hạn thời gian
                            <?php elseif ($pastDue): ?>
                                <span class="text-danger">Đã quá hạn <?= e(humanDuration(time() - $due)) ?></span>
                            <?php else: ?>
                                Còn <?= e(humanDuration($due - time())) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($sub): ?>
                        <tr>
                            <th>Lần nộp gần nhất</th>
                            <td><?= e(fmtDateTime($sub['submitted_at'])) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Điểm và nhận xét -->
        <?php if ($locked): ?>
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white fw-bold">Kết quả</div>
                <div class="card-body">
                    <div class="fs-4 fw-bold mb-2">
                        <?= e(formatScore($sub['score'])) ?> / <?= e(formatScore($assignment['max_score'])) ?>
                    </div>
                    <div class="text-muted small mb-2">Chấm lúc <?= e(fmtDateTime($sub['graded_at'])) ?></div>
                    <?php if (!empty($sub['feedback'])): ?>
                        <div class="fw-semibold">Nhận xét của giảng viên:</div>
                        <div><?= nl2br(e($sub['feedback'])) ?></div>
                    <?php else: ?>
                        <div class="text-muted">Giảng viên chưa có nhận xét.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- File đã nộp (khi đã khóa) -->
        <?php if ($locked): ?>
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold">File đã nộp (<?= count($files) ?>)</div>
                <?php if (!$files): ?>
                    <div class="card-body text-muted">Không có file đính kèm.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($files as $f): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-code"></i> <?= e($f['original_name']) ?>
                                    <span class="text-muted small">(<?= e(formatFileSize((int) $f['file_size'])) ?>)</span></span>
                                <a href="<?= url('student/download.php?id=' . (int) $f['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($sub['answer_text'])): ?>
                    <div class="card-body border-top">
                        <div class="fw-semibold small">Ghi chú của bạn:</div>
                        <?= nl2br(e($sub['answer_text'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Form nộp / sửa bài -->
            <div class="card">
                <div class="card-header bg-white fw-bold"><?= $sub ? 'Chỉnh sửa bài nộp' : 'Nộp bài' ?></div>
                <div class="card-body">
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <?php if ($pastDue): ?>
                        <div class="alert alert-warning py-2 small">
                            Hạn nộp đã qua. Bài nộp bây giờ vẫn được nhận nhưng sẽ bị đánh dấu <strong>nộp trễ</strong>.
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" novalidate>
                        <?= csrfField() ?>

                        <?php if ($files): ?>
                            <div class="mb-3">
                                <div class="fw-semibold mb-2">File đã nộp (đánh dấu để xóa)</div>
                                <?php foreach ($files as $f): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_files[]"
                                               value="<?= (int) $f['id'] ?>" id="rm<?= (int) $f['id'] ?>">
                                        <label class="form-check-label" for="rm<?= (int) $f['id'] ?>">
                                            <?= e($f['original_name']) ?>
                                            <span class="text-muted small">(<?= e(formatFileSize((int) $f['file_size'])) ?>)</span>
                                            &middot; <a href="<?= url('student/download.php?id=' . (int) $f['id']) ?>">Tải về</a>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="files">Thêm file</label>
                            <input type="file" class="form-control" id="files" name="files[]" multiple accept="<?= e($accept) ?>">
                            <div class="form-text">
                                Tối đa <?= SUBMISSION_MAX_FILES ?> file mỗi bài nộp, mỗi file không quá
                                <?= (int) (SUBMISSION_MAX_SIZE / 1048576) ?> MB.
                                Cho phép: tài liệu, file nén, ảnh và mã nguồn
                                (<?= e(implode(', ', SUBMISSION_ALLOWED_EXT)) ?>).
                            </div>
                            <ul class="small mt-2 mb-0" id="file-preview"></ul>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="answer_text">Ghi chú gửi giảng viên (không bắt buộc)</label>
                            <textarea class="form-control" id="answer_text" name="answer_text" rows="4"><?= e($noteInput) ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><?= $sub ? 'Lưu thay đổi' : 'Nộp bài' ?></button>
                            <a href="<?= url('student/assignments.php?course_id=' . (int) $assignment['course_id']) ?>" class="btn btn-outline-secondary">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            (function () {
                var input = document.getElementById('files');
                var box = document.getElementById('file-preview');
                var maxFiles = <?= (int) $maxNew ?>;
                var maxSize = <?= (int) SUBMISSION_MAX_SIZE ?>;
                input.addEventListener('change', function () {
                    box.innerHTML = '';
                    Array.prototype.forEach.call(input.files, function (f) {
                        var li = document.createElement('li');
                        li.textContent = f.name + ' (' + (f.size / 1048576).toFixed(2) + ' MB)';
                        if (f.size > maxSize) {
                            li.className = 'text-danger';
                            li.textContent += ' - vượt quá dung lượng cho phép';
                        }
                        box.appendChild(li);
                    });
                    if (input.files.length > maxFiles) {
                        var w = document.createElement('li');
                        w.className = 'text-danger';
                        w.textContent = 'Bạn chọn quá số file cho phép (còn tối đa ' + maxFiles + ' file).';
                        box.appendChild(w);
                    }
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

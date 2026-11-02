<?php
/**
 * Hàm dùng chung cho bài tập và bài nộp.
 * Cần require auth.php trước.
 */

// ---- Quy định nộp bài (dùng cho form giảng viên hiện tại và trang nộp bài của sinh viên ở Bước 4) ----
const SUBMISSION_MAX_FILES = 10;                    // số file tối đa mỗi lần nộp
const SUBMISSION_MAX_SIZE  = 20 * 1024 * 1024;      // 20 MB mỗi file
const SUBMISSION_ALLOWED_EXT = [
    // tài liệu
    'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'md',
    // nén
    'zip', 'rar', '7z',
    // ảnh
    'jpg', 'jpeg', 'png',
    // mã nguồn
    'c', 'h', 'cpp', 'hpp', 'cc', 'cs', 'java', 'py', 'js', 'ts', 'html', 'css', 'php', 'sql', 'json', 'xml', 'ipynb', 'sh',
];

/**
 * Lấy bài tập thuộc khóa học của giảng viên đang đăng nhập.
 * Không phải của mình hoặc không tồn tại thì báo lỗi và chuyển về dashboard.
 */
function requireOwnAssignment(int $id): array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.title AS course_title
         FROM assignments a JOIN courses c ON c.id = a.course_id
         WHERE a.id = ? AND c.teacher_id = ?'
    );
    $stmt->execute([$id, (int) currentUser()['id']]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        flash('danger', 'Không tìm thấy bài tập hoặc bạn không có quyền truy cập.');
        redirect(url('teacher/index.php'));
    }
    return $assignment;
}

function fmtDateTime(?string $dt, string $empty = '—'): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : $empty;
}

/** 8.50 -> "8.5", 10.00 -> "10" */
function formatScore($score): string
{
    if ($score === null || $score === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
}

function isLateSubmission(array $assignment, string $submittedAt): bool
{
    return !empty($assignment['due_date']) && strtotime($submittedAt) > strtotime($assignment['due_date']);
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

function getSubmissionFiles(int $submissionId): array
{
    $stmt = db()->prepare('SELECT * FROM submission_files WHERE submission_id = ? ORDER BY id');
    $stmt->execute([$submissionId]);
    return $stmt->fetchAll();
}

/** Tên file lưu trên đĩa của mọi bài nộp thuộc 1 bài tập (để xóa file khi xóa bài tập). */
function submissionFileNamesForAssignment(int $assignmentId): array
{
    $stmt = db()->prepare(
        'SELECT sf.stored_name FROM submission_files sf
         JOIN submissions s ON s.id = sf.submission_id
         WHERE s.assignment_id = ?'
    );
    $stmt->execute([$assignmentId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** Tên file lưu trên đĩa của mọi bài nộp thuộc 1 khóa học (để xóa file khi xóa khóa học). */
function submissionFileNamesForCourse(int $courseId): array
{
    $stmt = db()->prepare(
        'SELECT sf.stored_name FROM submission_files sf
         JOIN submissions s ON s.id = sf.submission_id
         JOIN assignments a ON a.id = s.assignment_id
         WHERE a.course_id = ?'
    );
    $stmt->execute([$courseId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function deleteSubmissionFiles(array $storedNames): void
{
    foreach ($storedNames as $name) {
        $path = UPLOAD_PATH . 'submissions/' . basename($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Gửi file bài nộp về trình duyệt dưới dạng TẢI VỀ (attachment).
 * File lưu trên đĩa không có đuôi và luôn được gửi với Content-Type octet-stream,
 * nên file .php / .html do sinh viên nộp không bao giờ bị thực thi hay hiển thị trong trình duyệt.
 * Chỉ gọi hàm này SAU KHI đã kiểm tra quyền. Nếu file không còn trên đĩa, hàm trả về (không exit).
 */
function sendSubmissionFile(array $file): void
{
    $path = UPLOAD_PATH . 'submissions/' . basename($file['stored_name']);
    if (!is_file($path)) {
        flash('danger', 'Không tìm thấy file trên máy chủ (có thể đã bị xóa).');
        return;
    }

    $name  = (string) $file['original_name'];
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

    session_write_close();                 // không giữ khóa session khi tải file lớn
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($name));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

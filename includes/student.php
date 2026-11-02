<?php
/**
 * Hàm dùng chung cho khu vực sinh viên (học bài, nộp bài).
 * Cần require auth.php trước.
 */
require_once __DIR__ . '/courses.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/assignments.php';

// Đuôi file mã nguồn / văn bản: kiểm tra bằng cách đảm bảo nội dung là văn bản (không có byte NUL)
const SUBMISSION_TEXT_EXT = ['c', 'h', 'cpp', 'hpp', 'cc', 'cs', 'java', 'py', 'js', 'ts', 'html', 'css',
                             'php', 'sql', 'json', 'xml', 'ipynb', 'sh', 'txt', 'md'];

/** Ai được xem nội dung khóa học (bài học, tài liệu): sinh viên đã ghi danh, giảng viên chủ khóa, admin. */
function canAccessCourseContent(array $course): bool
{
    if (!isLoggedIn()) {
        return false;
    }
    if (hasRole('admin')) {
        return true;
    }
    if (hasRole('teacher')) {
        return (int) $course['teacher_id'] === (int) currentUser()['id'];
    }
    if (hasRole('student')) {
        return ($course['status'] ?? '') === 'published'
            && isEnrolled((int) currentUser()['id'], (int) $course['id']);
    }
    return false;
}

/** Tên file tải về lấy từ tiêu đề bài học, bỏ ký tự không hợp lệ. */
function safeDownloadName(string $title, string $ext): string
{
    $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $title);
    $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    $name = mb_substr($name, 0, 100);
    if ($name === '') {
        $name = 'tai-lieu';
    }
    return $ext !== '' ? $name . '.' . $ext : $name;
}

/**
 * Gửi file về dạng tải xuống. Thành công thì exit; trả về false nếu file không còn trên đĩa.
 * Luôn dùng octet-stream + attachment + nosniff nên file không bao giờ bị trình duyệt thực thi.
 */
function sendFileDownload(string $path, string $downloadName): bool
{
    if (!is_file($path)) {
        return false;
    }
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);

    session_write_close();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/** "2 ngày 3 giờ", "5 giờ 20 phút", "10 phút" */
function humanDuration(int $seconds): string
{
    $seconds = abs($seconds);
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($d > 0) {
        return $d . ' ngày' . ($h > 0 ? ' ' . $h . ' giờ' : '');
    }
    if ($h > 0) {
        return $h . ' giờ' . ($m > 0 ? ' ' . $m . ' phút' : '');
    }
    return max(1, $m) . ' phút';
}

/**
 * Bài tập của sinh viên trong các khóa đã ghi danh (hoặc 1 khóa), kèm trạng thái:
 * status = todo | submitted | graded; late = nộp trễ; overdue = chưa nộp và đã quá hạn.
 */
function getStudentAssignments(int $studentId, ?int $courseId = null): array
{
    $sql = "SELECT a.*, c.title AS course_title,
                   s.id AS submission_id, s.submitted_at, s.score
            FROM enrollments e
            JOIN courses c     ON c.id = e.course_id
            JOIN assignments a ON a.course_id = c.id
            LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = e.student_id
            WHERE e.student_id = ? AND c.status = 'published'";
    $params = [$studentId];
    if ($courseId) {
        $sql .= ' AND c.id = ?';
        $params[] = $courseId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $now = time();
    foreach ($rows as &$r) {
        $due = $r['due_date'] ? strtotime($r['due_date']) : null;
        if ($r['submission_id'] === null) {
            $r['status'] = 'todo';
        } elseif ($r['score'] !== null) {
            $r['status'] = 'graded';
        } else {
            $r['status'] = 'submitted';
        }
        $r['late']    = $r['submission_id'] !== null && $due !== null && strtotime($r['submitted_at']) > $due;
        $r['overdue'] = $r['status'] === 'todo' && $due !== null && $due < $now;
    }
    unset($r);

    $rank = ['todo' => 0, 'submitted' => 1, 'graded' => 2];
    usort($rows, function ($x, $y) use ($rank) {
        if ($x['status'] !== $y['status']) {
            return $rank[$x['status']] <=> $rank[$y['status']];
        }
        $dx = $x['due_date'] ? strtotime($x['due_date']) : PHP_INT_MAX;
        $dy = $y['due_date'] ? strtotime($y['due_date']) : PHP_INT_MAX;
        return $dx <=> $dy;
    });
    return $rows;
}

/** Huy hiệu trạng thái bài tập (HTML đã escape sẵn). */
function assignmentStatusBadge(array $r): string
{
    if ($r['status'] === 'graded') {
        $html = '<span class="badge text-bg-success">Đã chấm</span>';
    } elseif ($r['status'] === 'submitted') {
        $html = '<span class="badge text-bg-info">Đã nộp</span>';
    } elseif ($r['overdue']) {
        $html = '<span class="badge text-bg-danger">Quá hạn</span>';
    } else {
        $html = '<span class="badge text-bg-secondary">Chưa nộp</span>';
    }
    if ($r['late']) {
        $html .= ' <span class="badge text-bg-danger">Nộp trễ</span>';
    }
    return $html;
}

// =====================================================
// Nộp bài nhiều file
// =====================================================

/** Chuẩn hóa $_FILES['files'] (nhiều file) thành danh sách phẳng, bỏ qua ô không chọn file. */
function normalizeUploadedFiles(?array $f): array
{
    $out = [];
    if (!$f || !isset($f['name'])) {
        return $out;
    }
    if (!is_array($f['name'])) {
        $f = array_map(fn($v) => [$v], $f);
    }
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => (string) $name,
            'tmp_name' => $f['tmp_name'][$i] ?? '',
            'size'     => (int) ($f['size'][$i] ?? 0),
            'error'    => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
    return $out;
}

/** MIME hợp lệ cho các định dạng nhị phân (nội dung thật, không tin tên file). */
function submissionBinaryMimeMap(): array
{
    $zipLike = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
    $legacy  = ['application/octet-stream', 'application/msword', 'application/vnd.ms-powerpoint',
                'application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-cfb'];
    return [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'zip'  => $zipLike,
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $zipLike),
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $zipLike),
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $zipLike),
        'doc'  => $legacy,
        'ppt'  => $legacy,
        'xls'  => $legacy,
        'rar'  => ['application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        '7z'   => ['application/x-7z-compressed', 'application/octet-stream'],
    ];
}

/** Kiểm tra 1 file nộp. Trả về chuỗi lỗi, hoặc null nếu hợp lệ. */
function validateSubmissionFile(array $file): ?string
{
    $label = '"' . $file['name'] . '"';

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Tệp ' . $label . ': ' . uploadErrorMessage($file['error']);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Tệp ' . $label . ' không hợp lệ.';
    }
    if ($file['size'] > SUBMISSION_MAX_SIZE) {
        return 'Tệp ' . $label . ' vượt quá ' . (int) (SUBMISSION_MAX_SIZE / 1048576) . ' MB.';
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, SUBMISSION_ALLOWED_EXT, true)) {
        return 'Tệp ' . $label . ': định dạng không được phép.';
    }

    if (in_array($ext, SUBMISSION_TEXT_EXT, true)) {
        // Mã nguồn / văn bản: nội dung phải là văn bản thuần (không có byte NUL)
        $chunk = (string) file_get_contents($file['tmp_name'], false, null, 0, 8192);
        if (strpos($chunk, "\0") !== false) {
            return 'Tệp ' . $label . ' không phải tệp văn bản/mã nguồn hợp lệ.';
        }
        return null;
    }

    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, submissionBinaryMimeMap()[$ext] ?? [], true)) {
            return 'Tệp ' . $label . ': nội dung không khớp với định dạng .' . $ext . '.';
        }
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && @getimagesize($file['tmp_name']) === false) {
        return 'Tệp ' . $label . ' không phải ảnh hợp lệ.';
    }
    return null;
}

/**
 * Lưu 1 file đã hợp lệ vào uploads/submissions/ với tên ngẫu nhiên, KHÔNG có đuôi.
 * @return array ['original_name', 'stored_name', 'file_size']
 */
function storeSubmissionFile(array $file): array
{
    $dir = UPLOAD_PATH . 'submissions/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Không tạo được thư mục lưu bài nộp.');
    }
    // Đảm bảo thư mục luôn bị chặn truy cập trực tiếp
    if (!is_file($dir . '.htaccess')) {
        @file_put_contents($dir . '.htaccess',
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }

    $stored = bin2hex(random_bytes(16));
    if (!move_uploaded_file($file['tmp_name'], $dir . $stored)) {
        throw new RuntimeException('Không lưu được tệp ' . $file['name'] . '.');
    }

    $original = basename(str_replace('\\', '/', $file['name']));
    $original = preg_replace('/[\x00-\x1F]/', '', $original);
    return [
        'original_name' => mb_substr($original, 0, 200),
        'stored_name'   => $stored,
        'file_size'     => (int) $file['size'],
    ];
}

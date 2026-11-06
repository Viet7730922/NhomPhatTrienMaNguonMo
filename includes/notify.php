<?php
/**
 * Thông báo qua email cho 2 sự kiện:
 *   1. Giảng viên TẠO bài tập mới  -> gửi cho mọi sinh viên đã ghi danh khóa học đó
 *   2. Sinh viên NỘP bài           -> gửi cho giảng viên của khóa học đó
 *
 * Các hàm ở đây KHÔNG ném lỗi và KHÔNG chặn chức năng chính: gửi thất bại chỉ hiện cảnh báo / ghi log.
 * Gọi SAU KHI đã lưu CSDL thành công (xem teacher/assignment_form.php và student/assignment.php).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/assignments.php';
require_once __DIR__ . '/mail.php';

/** Cắt chuỗi cho gọn (đếm theo ký tự, không cắt giữa chữ tiếng Việt). */
function mailExcerpt(?string $text, int $max = 300): string
{
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_strimwidth($text, 0, $max, '…', 'UTF-8');
}

/** Một dòng "Nhãn: giá trị" trong thư. Cả hai phần đều được escape ở đây. */
function mailRow(string $label, string $value): string
{
    return '<tr><td style="padding:4px 12px 4px 0;color:#6c757d;white-space:nowrap;vertical-align:top">' . e($label) . '</td>'
         . '<td style="padding:4px 0">' . e($value) . '</td></tr>';
}

// ---------------------------------------------------------------
// 1. Bài tập mới -> sinh viên đã ghi danh
// ---------------------------------------------------------------

/**
 * Gửi thư báo bài tập mới cho sinh viên đã ghi danh. Tự hiện thông báo kết quả bằng flash().
 * (Chỉ gọi khi TẠO MỚI bài tập, không gọi khi sửa.)
 */
function notifyNewAssignment(int $assignmentId): void
{
    try {
        $stmt = db()->prepare(
            'SELECT a.*, c.title AS course_title, t.full_name AS teacher_name
             FROM assignments a
             JOIN courses c ON c.id = a.course_id
             JOIN users t   ON t.id = c.teacher_id
             WHERE a.id = ?'
        );
        $stmt->execute([$assignmentId]);
        $a = $stmt->fetch();
        if (!$a) {
            return;
        }

        // Chỉ sinh viên đang hoạt động (bị khóa thì không gửi)
        $stmt = db()->prepare(
            "SELECT u.full_name AS name, u.email
             FROM enrollments en
             JOIN users u ON u.id = en.student_id
             WHERE en.course_id = ? AND u.role = 'student' AND u.is_active = 1
             ORDER BY u.id"
        );
        $stmt->execute([(int) $a['course_id']]);
        $students = $stmt->fetchAll();

        if (!$students) {
            flash('info', 'Khóa học chưa có sinh viên ghi danh nên chưa gửi email thông báo.');
            return;
        }
        if (!mailEnabled()) {
            flash('info', 'Chưa cấu hình email (config/mail.local.php) nên chưa gửi thông báo cho sinh viên.');
            return;
        }

        $link    = url('student/assignment.php?id=' . $assignmentId);
        $subject = '[' . $a['course_title'] . '] Bài tập mới: ' . $a['title'];

        $res = sendMailEach($students, $subject, function (array $s) use ($a, $link): string {
            $body = '<p style="margin:0 0 12px">Chào ' . e($s['name']) . ',</p>'
                  . '<p style="margin:0 0 12px">Giảng viên <strong>' . e($a['teacher_name']) . '</strong> vừa giao bài tập mới '
                  . 'trong khóa học <strong>' . e($a['course_title']) . '</strong>:</p>'
                  . '<table style="border-collapse:collapse;font-size:14px;margin:0 0 12px">'
                  . mailRow('Bài tập', (string) $a['title'])
                  . mailRow('Hạn nộp', fmtDateTime($a['due_date'], 'Không giới hạn'))
                  . mailRow('Điểm tối đa', formatScore($a['max_score']))
                  . mailRow('Hình thức nộp', ['file' => 'Nộp file', 'online_text' => 'Online text', 'both' => 'File và Online text'][$a['submission_mode'] ?? 'both'])
                  . '</table>';
            if (!empty($a['description'])) {
                $body .= '<div style="border-left:3px solid #dee2e6;padding-left:12px;color:#495057;font-size:14px">'
                       . e(mailExcerpt($a['description'])) . '</div>';
            }
            return mailLayout('Bạn có bài tập mới', $body, ['text' => 'Xem và nộp bài', 'url' => $link]);
        });

        $total = count($students);
        if (!$res['configured']) {
            flash('info', 'Chưa cấu hình email nên chưa gửi thông báo cho sinh viên.');
        } elseif ($res['failed'] === 0) {
            flash('info', 'Đã gửi email thông báo cho ' . $res['sent'] . ' sinh viên.');
        } else {
            $msg = 'Gửi email thông báo: thành công ' . $res['sent'] . '/' . $total . ' sinh viên, lỗi ' . $res['failed'] . '.';
            if (DEBUG && $res['error'] !== '') {
                $msg .= ' (' . $res['error'] . ')';
            }
            flash('warning', $msg);
        }
    } catch (Throwable $ex) {
        error_log('[notify] bài tập mới: ' . $ex->getMessage());
        flash('warning', 'Không gửi được email thông báo' . (DEBUG ? ': ' . $ex->getMessage() : '.'));
    }
}

// ---------------------------------------------------------------
// 2. Sinh viên nộp bài -> giảng viên của khóa học
// ---------------------------------------------------------------

/**
 * Gửi thư báo có bài nộp cho giảng viên của khóa học.
 * $isUpdate = true khi sinh viên SỬA bài đã nộp (tiêu đề thư sẽ khác).
 * Sinh viên không cần biết chuyện gửi mail: lỗi chỉ ghi log (chế độ DEBUG thì có cảnh báo để tiện sửa cấu hình).
 */
function notifyTeacherSubmission(int $assignmentId, int $studentId, bool $isUpdate): void
{
    try {
        if (!mailEnabled()) {
            return;
        }

        $stmt = db()->prepare(
            'SELECT a.*, c.title AS course_title,
                    t.full_name AS teacher_name, t.email AS teacher_email, t.is_active AS teacher_active
             FROM assignments a
             JOIN courses c ON c.id = a.course_id
             JOIN users t   ON t.id = c.teacher_id
             WHERE a.id = ?'
        );
        $stmt->execute([$assignmentId]);
        $a = $stmt->fetch();

        $stmt = db()->prepare('SELECT full_name, email FROM users WHERE id = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        $stmt = db()->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?');
        $stmt->execute([$assignmentId, $studentId]);
        $sub = $stmt->fetch();

        if (!$a || !$student || !$sub || !$a['teacher_active']) {
            return;
        }
        $files = getSubmissionFiles((int) $sub['id']);
        $late  = isLateSubmission($a, (string) $sub['submitted_at']);

        $verb    = $isUpdate ? 'đã cập nhật bài nộp' : 'đã nộp bài';
        $subject = '[' . $a['course_title'] . '] ' . $student['full_name'] . ' ' . $verb . ': ' . $a['title'];

        $res = sendMailEach(
            [['email' => $a['teacher_email'], 'name' => $a['teacher_name']]],
            $subject,
            function (array $t) use ($a, $student, $sub, $files, $late, $verb, $assignmentId): string {
                $fileList = '';
                foreach ($files as $f) {
                    $fileList .= '<li>' . e($f['original_name']) . ' (' . e(formatFileSize((int) $f['file_size'])) . ')</li>';
                }

                $body = '<p style="margin:0 0 12px">Chào ' . e($t['name']) . ',</p>'
                      . '<p style="margin:0 0 12px">Sinh viên <strong>' . e($student['full_name']) . '</strong> ' . e($verb) . '.</p>'
                      . '<table style="border-collapse:collapse;font-size:14px;margin:0 0 12px">'
                      . mailRow('Khóa học', (string) $a['course_title'])
                      . mailRow('Bài tập', (string) $a['title'])
                      . mailRow('Sinh viên', $student['full_name'] . ' <' . $student['email'] . '>')
                      . mailRow('Nộp lúc', fmtDateTime($sub['submitted_at']) . ($late ? ' (NỘP TRỄ)' : ''))
                      . mailRow('Hạn nộp', fmtDateTime($a['due_date'], 'Không giới hạn'))
                      . '</table>';
                if ($fileList !== '') {
                    $body .= '<div style="font-size:14px;margin:0 0 8px"><strong>File đính kèm (' . count($files) . '):</strong></div>'
                           . '<ul style="margin:0 0 12px;padding-left:20px;font-size:14px">' . $fileList . '</ul>';
                }
                if (!empty($sub['answer_text'])) {
                    $answerLabel = ($sub['answer_format'] ?? 'plain') === 'html' ? 'Online text của sinh viên:' : 'Ghi chú của sinh viên:';
                    $body .= '<div style="font-size:14px;margin:0 0 4px"><strong>' . e($answerLabel) . '</strong></div>'
                           . '<div style="border-left:3px solid #dee2e6;padding-left:12px;color:#495057;font-size:14px">'
                           . e(mailExcerpt($sub['answer_text'])) . '</div>';
                }
                return mailLayout(
                    'Có bài nộp mới',
                    $body,
                    ['text' => 'Xem các bài nộp', 'url' => url('teacher/submissions.php?assignment_id=' . $assignmentId)]
                );
            },
            ['email' => $student['email'], 'name' => $student['full_name']]    // giảng viên bấm "Trả lời" là gửi thẳng cho sinh viên
        );

        if ($res['failed'] > 0 && DEBUG) {
            flash('warning', 'Chưa gửi được email báo giảng viên' . ($res['error'] !== '' ? ': ' . $res['error'] : '.'));
        }
    } catch (Throwable $ex) {
        error_log('[notify] nộp bài: ' . $ex->getMessage());
    }
}

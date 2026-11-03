<?php
/**
 * Hàm dùng chung cho bài kiểm tra trực tuyến (Bước 6).
 * Cần require auth.php trước.
 *
 * Nguyên tắc: mọi quyết định (còn giờ không, được làm tiếp không, chấm đúng/sai) đều do SERVER làm.
 * Trình duyệt chỉ hiển thị đồng hồ; đáp án đúng KHÔNG bao giờ được gửi xuống trang làm bài.
 */
require_once __DIR__ . '/student.php';      // humanDuration(), formatScore(), fmtDateTime()...

const QUIZ_GRACE_SECONDS = 30;              // du di mạng khi nộp sát giờ

const QUIZ_GRADE_METHODS = [
    'highest' => 'Điểm cao nhất',
    'average' => 'Điểm trung bình',
    'first'   => 'Lần làm đầu tiên',
    'last'    => 'Lần làm cuối cùng',
];
const QUIZ_REVIEW_MODES = [
    'never'       => 'Không cho xem lại',
    'after_close' => 'Cho xem lại sau khi bài kiểm tra đóng',
    'immediate'   => 'Cho xem lại ngay sau khi nộp',
];
const QUIZ_QUESTION_TYPES = [
    'single'   => 'Trắc nghiệm một đáp án',
    'multiple' => 'Trắc nghiệm nhiều đáp án',
    'essay'    => 'Tự luận (giảng viên chấm)',
];
const QUIZ_ATTEMPT_LABELS = [
    'in_progress' => ['Đang làm', 'warning'],
    'waiting'     => ['Chờ chấm tự luận', 'info'],
    'finished'    => ['Đã hoàn thành', 'success'],
];

function quizNow(): string
{
    return date('Y-m-d H:i:s');
}

/** "8" -> "8.00" (hiển thị điểm kiểu Moodle). */
function fmtMarks($v): string
{
    return number_format((float) $v, 2, '.', '');
}

// ---------- Truy vấn bài kiểm tra ----------

/** Bài kiểm tra thuộc khóa học của giảng viên đang đăng nhập; không phải thì báo lỗi và chuyển về dashboard. */
function requireOwnQuiz(int $id): array
{
    $stmt = db()->prepare(
        'SELECT q.*, c.title AS course_title
         FROM quizzes q JOIN courses c ON c.id = q.course_id
         WHERE q.id = ? AND c.teacher_id = ?'
    );
    $stmt->execute([$id, (int) currentUser()['id']]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        flash('danger', 'Không tìm thấy bài kiểm tra hoặc bạn không có quyền truy cập.');
        redirect(url('teacher/index.php'));
    }
    return $quiz;
}

/** Bài kiểm tra đã công khai của khóa học mà sinh viên đã ghi danh; không thì null. */
function getQuizForStudent(int $quizId, int $studentId): ?array
{
    $stmt = db()->prepare(
        "SELECT q.*, c.title AS course_title
         FROM quizzes q
         JOIN courses c     ON c.id = q.course_id
         JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
         WHERE q.id = ? AND q.is_published = 1 AND c.status = 'published'"
    );
    $stmt->execute([$studentId, $quizId]);
    return $stmt->fetch() ?: null;
}

/** Câu hỏi + đáp án, khóa mảng theo id câu hỏi. CHỈ dùng phía server (có is_correct). */
function quizQuestions(int $quizId): array
{
    $stmt = db()->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
    $stmt->execute([$quizId]);
    $questions = [];
    foreach ($stmt->fetchAll() as $r) {
        $r['options'] = [];
        $questions[(int) $r['id']] = $r;
    }
    if ($questions) {
        $in   = implode(',', array_fill(0, count($questions), '?'));
        $stmt = db()->prepare("SELECT id, question_id, text, is_correct FROM quiz_options WHERE question_id IN ($in) ORDER BY id");
        $stmt->execute(array_keys($questions));
        foreach ($stmt->fetchAll() as $o) {
            $questions[(int) $o['question_id']]['options'][] = $o;
        }
    }
    return $questions;
}

function quizHasAttempts(int $quizId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM quiz_attempts WHERE quiz_id = ? LIMIT 1');
    $stmt->execute([$quizId]);
    return (bool) $stmt->fetchColumn();
}

/** not_open | open | closed */
function quizAvailability(array $quiz): string
{
    $now = time();
    if (!empty($quiz['open_at']) && strtotime($quiz['open_at']) > $now) {
        return 'not_open';
    }
    if (!empty($quiz['close_at']) && strtotime($quiz['close_at']) < $now) {
        return 'closed';
    }
    return 'open';
}

// ---------- Lần làm bài ----------

function getAttempts(int $quizId, int $studentId): array
{
    $stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY attempt_no');
    $stmt->execute([$quizId, $studentId]);
    return $stmt->fetchAll();
}

/** Lấy 1 lần làm bài của chính sinh viên này (chống xem bài người khác bằng cách đổi id). */
function getOwnAttempt(int $attemptId, int $studentId): ?array
{
    $stmt = db()->prepare('SELECT * FROM quiz_attempts WHERE id = ? AND student_id = ?');
    $stmt->execute([$attemptId, $studentId]);
    return $stmt->fetch() ?: null;
}

function attemptExpired(array $attempt, int $grace = 0): bool
{
    return !empty($attempt['deadline_at']) && time() > strtotime($attempt['deadline_at']) + $grace;
}

/** Số giây còn lại (null = không giới hạn). */
function attemptSecondsLeft(array $attempt): ?int
{
    return empty($attempt['deadline_at']) ? null : max(0, strtotime($attempt['deadline_at']) - time());
}

/** Tự nộp các lần làm đã quá hạn (sinh viên đóng trình duyệt giữa chừng). */
function closeExpiredAttempts(int $quizId, int $studentId): void
{
    foreach (getAttempts($quizId, $studentId) as $a) {
        if ($a['status'] === 'in_progress' && attemptExpired($a, QUIZ_GRACE_SECONDS)) {
            submitAttempt((int) $a['id'], []);
        }
    }
}

/**
 * Bắt đầu (hoặc tiếp tục) một lần làm bài.
 * @return array ['ok' => bool, 'attempt_id' => ?int, 'error' => ?string]
 */
function startAttempt(array $quiz, int $studentId): array
{
    $fail = fn(string $m) => ['ok' => false, 'attempt_id' => null, 'error' => $m];

    $avail = quizAvailability($quiz);
    if ($avail === 'not_open') {
        return $fail('Bài kiểm tra chưa đến giờ mở.');
    }
    if ($avail === 'closed') {
        return $fail('Bài kiểm tra đã đóng.');
    }
    $questions = quizQuestions((int) $quiz['id']);
    if (!$questions) {
        return $fail('Bài kiểm tra chưa có câu hỏi.');
    }
    $maxMarks = array_sum(array_map(fn($q) => (float) $q['points'], $questions));

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY attempt_no FOR UPDATE');
        $stmt->execute([(int) $quiz['id'], $studentId]);
        $attempts = $stmt->fetchAll();

        foreach ($attempts as $a) {                       // đang làm dở thì cho làm tiếp, không mở thêm lần mới
            if ($a['status'] === 'in_progress') {
                $pdo->commit();
                return ['ok' => true, 'attempt_id' => (int) $a['id'], 'error' => null];
            }
        }
        $limit = (int) $quiz['max_attempts'];
        if ($limit > 0 && count($attempts) >= $limit) {
            $pdo->rollBack();
            return $fail('Bạn đã dùng hết số lần làm bài cho phép.');
        }

        $last = $attempts ? end($attempts) : null;
        $no   = $last ? (int) $last['attempt_no'] + 1 : 1;

        $now      = time();
        $deadline = null;
        if (!empty($quiz['time_limit_min'])) {
            $deadline = $now + (int) $quiz['time_limit_min'] * 60;
        }
        if (!empty($quiz['close_at'])) {                  // không được làm lố giờ đóng bài
            $close    = strtotime($quiz['close_at']);
            $deadline = $deadline === null ? $close : min($deadline, $close);
        }

        $ids = array_keys($questions);
        if ($quiz['shuffle']) {
            shuffle($ids);
        }

        $pdo->prepare(
            'INSERT INTO quiz_attempts (quiz_id, student_id, attempt_no, status, started_at, deadline_at, question_order, max_marks)
             VALUES (?, ?, ?, "in_progress", ?, ?, ?, ?)'
        )->execute([
            (int) $quiz['id'], $studentId, $no, date('Y-m-d H:i:s', $now),
            $deadline !== null ? date('Y-m-d H:i:s', $deadline) : null,
            json_encode($ids), round($maxMarks, 2),
        ]);
        $attemptId = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['ok' => true, 'attempt_id' => $attemptId, 'error' => null];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 23000 = trùng (bấm 2 lần cùng lúc), 40001 = deadlock: cả hai đều chỉ cần thử lại
        if (in_array((string) $ex->getCode(), ['23000', '40001'], true)) {
            return $fail('Hệ thống đang bận, vui lòng thử lại.');
        }
        throw $ex;
    }
}

/** Câu hỏi của một lần làm bài theo đúng thứ tự đã chốt (đáp án xáo ổn định theo từng lần làm). */
function attemptQuestions(array $attempt, array $quiz): array
{
    $questions = quizQuestions((int) $attempt['quiz_id']);
    $order     = json_decode((string) $attempt['question_order'], true) ?: [];
    $out       = [];
    foreach ($order as $qid) {
        if (isset($questions[$qid])) {
            $out[] = $questions[$qid];
            unset($questions[$qid]);
        }
    }
    foreach ($questions as $q) {
        $out[] = $q;
    }
    if ($quiz['shuffle']) {
        $seed = (int) $attempt['id'];
        foreach ($out as &$q) {
            usort($q['options'], fn($a, $b) => crc32($seed . '-' . $a['id']) <=> crc32($seed . '-' . $b['id']));
        }
        unset($q);
    }
    return $out;
}

/** Điểm câu trắc nghiệm: chọn đúng cộng, chọn sai trừ, không âm. Một đáp án = đúng thì trọn điểm. */
function scoreChoice(array $q, array $chosen): float
{
    $correct = [];
    foreach ($q['options'] as $o) {
        if ($o['is_correct']) {
            $correct[] = (int) $o['id'];
        }
    }
    if (!$correct || !$chosen) {
        return 0.0;
    }
    $hit      = count(array_intersect($chosen, $correct));
    $wrong    = count($chosen) - $hit;
    $fraction = max(0.0, ($hit - $wrong) / count($correct));
    return round((float) $q['points'] * $fraction, 2);
}

/**
 * Nộp bài + chấm tự động phần trắc nghiệm, trong một giao dịch.
 * Gọi lại nhiều lần vẫn an toàn (lần 2 trở đi trả false, không chấm lại).
 * Nộp quá giờ (kể cả du di) thì bỏ toàn bộ đáp án gửi lên.
 */
function submitAttempt(int $attemptId, array $rawAnswers): bool
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM quiz_attempts WHERE id = ? FOR UPDATE');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt || $attempt['status'] !== 'in_progress') {
            $pdo->rollBack();
            return false;
        }
        $late = attemptExpired($attempt, QUIZ_GRACE_SECONDS);
        if ($late) {
            $rawAnswers = [];
        }

        $ins = $pdo->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, option_ids, answer_text, points_awarded)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE option_ids = VALUES(option_ids), answer_text = VALUES(answer_text),
                                     points_awarded = VALUES(points_awarded)'
        );

        $total   = 0.0;
        $pending = false;
        foreach (quizQuestions((int) $attempt['quiz_id']) as $qid => $q) {
            $raw       = $rawAnswers[$qid] ?? null;
            $optionIds = null;
            $text      = null;

            if ($q['type'] === 'essay') {
                $text = is_string($raw) ? trim(mb_substr($raw, 0, 20000)) : '';
                if ($text === '') {
                    $text   = null;
                    $points = 0.0;
                } else {
                    $points  = null;                       // chờ giảng viên chấm
                    $pending = true;
                }
            } else {
                $validIds = array_map(fn($o) => (int) $o['id'], $q['options']);
                $chosen   = array_map('intval', array_filter((array) $raw, 'is_scalar'));
                $chosen   = array_values(array_intersect(array_unique($chosen), $validIds));   // chỉ nhận đáp án của đúng câu này
                if ($q['type'] === 'single') {
                    $chosen = array_slice($chosen, 0, 1);
                }
                $optionIds = $chosen ? implode(',', $chosen) : null;
                $points    = scoreChoice($q, $chosen);
            }

            if ($points !== null) {
                $total += $points;
            }
            $ins->execute([$attemptId, $qid, $optionIds, $text, $points]);
        }

        $finished = $late && !empty($attempt['deadline_at']) ? $attempt['deadline_at'] : quizNow();
        $pdo->prepare('UPDATE quiz_attempts SET status = ?, finished_at = ?, score = ? WHERE id = ?')
            ->execute([$pending ? 'waiting' : 'finished', $finished, $pending ? null : round($total, 2), $attemptId]);

        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Tính lại tổng điểm sau khi giảng viên chấm tự luận. Hết câu chờ chấm thì chuyển sang "Đã hoàn thành". */
function recalcAttempt(int $attemptId): void
{
    $stmt = db()->prepare(
        'SELECT SUM(points_awarded IS NULL) AS pending, COALESCE(SUM(points_awarded), 0) AS total
         FROM quiz_answers WHERE attempt_id = ?'
    );
    $stmt->execute([$attemptId]);
    $r       = $stmt->fetch();
    $pending = (int) $r['pending'] > 0;

    db()->prepare("UPDATE quiz_attempts SET status = ?, score = ? WHERE id = ? AND status <> 'in_progress'")
        ->execute([$pending ? 'waiting' : 'finished', $pending ? null : round((float) $r['total'], 2), $attemptId]);
}

// ---------- Điểm số ----------

/** Điểm của 1 lần làm theo thang max_grade (null nếu chưa có điểm). */
function attemptGrade(array $quiz, array $attempt): ?float
{
    if ($attempt['score'] === null || (float) $attempt['max_marks'] <= 0) {
        return null;
    }
    return round((float) $attempt['score'] / (float) $attempt['max_marks'] * (float) $quiz['max_grade'], 2);
}

/** Điểm cuối cùng theo phương pháp tính điểm; chỉ xét các lần đã có điểm. */
function quizFinalGrade(array $quiz, array $attempts): ?float
{
    $grades = [];
    foreach ($attempts as $a) {
        $g = attemptGrade($quiz, $a);
        if ($g !== null) {
            $grades[] = $g;
        }
    }
    if (!$grades) {
        return null;
    }
    switch ($quiz['grade_method']) {
        case 'average': return round(array_sum($grades) / count($grades), 2);
        case 'first':   return $grades[0];
        case 'last':    return $grades[count($grades) - 1];
        default:        return max($grades);
    }
}

function gradePercent(array $quiz, float $grade): int
{
    return (float) $quiz['max_grade'] > 0 ? (int) round($grade / (float) $quiz['max_grade'] * 100) : 0;
}

/** Sinh viên được xem lại (kèm đáp án đúng) lần làm này không? */
function canReviewAttempt(array $quiz, array $attempt): bool
{
    if (!in_array($attempt['status'], ['waiting', 'finished'], true)) {
        return false;
    }
    switch ($quiz['review_mode']) {
        case 'immediate':   return true;
        case 'after_close': return !empty($quiz['close_at']) && strtotime($quiz['close_at']) < time();
        default:            return false;
    }
}

function attemptDuration(array $attempt): string
{
    if (empty($attempt['finished_at'])) {
        return '—';
    }
    return humanDuration(strtotime($attempt['finished_at']) - strtotime($attempt['started_at']));
}

function attemptStatusBadge(array $attempt): string
{
    [$label, $color] = QUIZ_ATTEMPT_LABELS[$attempt['status']] ?? [$attempt['status'], 'secondary'];
    return '<span class="badge text-bg-' . e($color) . '">' . e($label) . '</span>';
}

/** Câu trả lời đã lưu của một lần làm: [question_id => hàng quiz_answers]. */
function getAttemptAnswers(int $attemptId): array
{
    $stmt = db()->prepare('SELECT * FROM quiz_answers WHERE attempt_id = ?');
    $stmt->execute([$attemptId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['question_id']] = $r;
    }
    return $out;
}

<?php
/**
 * Hàm dùng chung cho khóa học.
 * Dùng: require_once __DIR__ . '/includes/courses.php';  (sau khi đã require auth.php)
 */

/** Lấy 1 khóa học kèm tên giảng viên, danh mục, số bài học, số học viên. Trả về null nếu không có. */
function getCourse(int $id): ?array
{
    $sql = "SELECT c.*, u.full_name AS teacher_name, cat.name AS category_name,
                   (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id)     AS lesson_count,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
            FROM courses c
            JOIN users u ON u.id = c.teacher_id
            LEFT JOIN categories cat ON cat.id = c.category_id
            WHERE c.id = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Học viên đã ghi danh khóa học này chưa? */
function isEnrolled(int $studentId, int $courseId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$studentId, $courseId]);
    return (bool) $stmt->fetchColumn();
}

/** Ai được xem khóa học: ai cũng xem được bản published; bản nháp chỉ admin và giảng viên chủ khóa. */
function canViewCourse(array $course): bool
{
    if ($course['status'] === 'published') {
        return true;
    }
    if (hasRole('admin')) {
        return true;
    }
    return hasRole('teacher') && (int) currentUser()['id'] === (int) $course['teacher_id'];
}

/** HTML ảnh bìa khóa học. Nếu chưa có ảnh thì hiện nền gradient. Ảnh lưu ở uploads/thumbnails/ */
function courseThumbHtml(array $course, int $height = 160): string
{
    $thumb = $course['thumbnail'] ?? null;
    if ($thumb && is_file(UPLOAD_PATH . 'thumbnails/' . basename($thumb))) {
        return '<img src="' . e(UPLOAD_URL . 'thumbnails/' . rawurlencode(basename($thumb))) . '"'
             . ' class="w-100" style="height:' . $height . 'px;object-fit:cover" alt="' . e($course['title']) . '">';
    }

    $palette = [['#4e73df', '#224abe'], ['#1cc88a', '#0f8a5f'], ['#f6c23e', '#c99a14'],
                ['#e74a3b', '#a8281c'], ['#36b9cc', '#1f8a99'], ['#6f42c1', '#4a2a85']];
    [$from, $to] = $palette[((int) $course['id']) % count($palette)];

    return '<div class="d-flex align-items-center justify-content-center text-white"'
         . ' style="height:' . $height . 'px;background:linear-gradient(135deg,' . $from . ',' . $to . ')">'
         . '<i class="bi bi-journal-code" style="font-size:3rem"></i></div>';
}

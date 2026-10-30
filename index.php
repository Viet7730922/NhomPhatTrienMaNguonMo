<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses.php';

$perPage    = 6;
$q          = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$page       = max(1, (int) ($_GET['page'] ?? 1));

// ---- Xây điều kiện lọc ----
$where  = ["c.status = 'published'"];
$params = [];

if ($q !== '') {
    $like     = '%' . addcslashes($q, '%_\\') . '%';   // escape ký tự đặc biệt của LIKE
    $where[]  = '(c.title LIKE ? OR c.description LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId > 0) {
    $where[]  = 'c.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = implode(' AND ', $where);

// ---- Đếm tổng để phân trang ----
$stmt = db()->prepare("SELECT COUNT(*) FROM courses c WHERE $whereSql");
$stmt->execute($params);
$total      = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ---- Lấy danh sách khóa học (LIMIT/OFFSET đã ép kiểu int nên an toàn) ----
$sql = "SELECT c.*, u.full_name AS teacher_name, cat.name AS category_name,
               (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id)     AS lesson_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        LEFT JOIN categories cat ON cat.id = c.category_id
        WHERE $whereSql
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// Tạo link phân trang, giữ nguyên từ khóa và danh mục đang lọc
$pageUrl = function (int $p) use ($q, $categoryId): string {
    $qs = http_build_query(array_filter([
        'q'        => $q,
        'category' => $categoryId,
        'page'     => $p > 1 ? $p : null,
    ]));
    return url('index.php') . ($qs ? '?' . $qs : '');
};

$pageTitle = 'Khóa học';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h3 class="mb-3">Khám phá khóa học</h3>

    <form method="get" class="row g-2">
        <div class="col-md-6">
            <input type="search" name="q" class="form-control" placeholder="Tìm khóa học..."
                   value="<?= e($q) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Tìm kiếm</button>
            <?php if ($q !== '' || $categoryId > 0): ?>
                <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary" title="Xóa bộ lọc"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!$courses): ?>
    <div class="alert alert-info">Không tìm thấy khóa học nào phù hợp.</div>
<?php else: ?>
    <p class="text-muted small">Tìm thấy <strong><?= $total ?></strong> khóa học</p>

    <div class="row g-4">
        <?php foreach ($courses as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 overflow-hidden">
                    <?= courseThumbHtml($c) ?>
                    <div class="card-body d-flex flex-column">
                        <?php if ($c['category_name']): ?>
                            <div class="mb-2"><span class="badge text-bg-light border"><?= e($c['category_name']) ?></span></div>
                        <?php endif; ?>
                        <h5 class="card-title">
                            <a href="<?= url('course.php?id=' . (int) $c['id']) ?>" class="text-decoration-none text-dark stretched-link">
                                <?= e($c['title']) ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small flex-grow-1">
                            <?= e(mb_strimwidth($c['description'] ?? '', 0, 110, '...')) ?>
                        </p>
                        <div class="small text-muted">
                            <div><i class="bi bi-person"></i> <?= e($c['teacher_name']) ?></div>
                            <div>
                                <i class="bi bi-play-circle"></i> <?= (int) $c['lesson_count'] ?> bài học
                                &middot;
                                <i class="bi bi-people"></i> <?= (int) $c['student_count'] ?> học viên
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="Phân trang">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

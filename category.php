<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();

// دریافت دسته
$stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>دسته یافت نشد</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
        <style>body { font-family: Tahoma, sans-serif; background: #f8f9fa; }</style>
    </head>
    <body>
        <div style="text-align:center; padding: 100px 20px;">
            <div style="font-size: 100px;">📁</div>
            <h1 style="color:#e74c3c;">۴۰۴</h1>
            <p style="color:#7f8c8d;">دسته‌بندی یافت نشد.</p>
            <a href="index.php" class="btn btn-primary mt-3">← بازگشت به خانه</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// صفحه‌بندی
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// تعداد کل
$stmt = $pdo->prepare("SELECT COUNT(*) FROM content_items WHERE category_id = ? AND status = 'published'");
$stmt->execute([$category['id']]);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// مقالات
$stmt = $pdo->prepare("
    SELECT ci.*, ct.icon as type_icon, u.username as author,
           c.name as category_name, c.color as category_color, c.icon as category_icon
    FROM content_items ci
    LEFT JOIN content_types ct ON ct.id = ci.type_id
    LEFT JOIN users u ON u.id = ci.author_id
    LEFT JOIN categories c ON c.id = ci.category_id
    WHERE ci.category_id = ? AND ci.status = 'published'
    ORDER BY ci.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute([$category['id']]);
$posts = $stmt->fetchAll();

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دسته <?= htmlspecialchars($category['name']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; margin: 0; }
        .top-bar { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar .logo { color: #fff; text-decoration: none; font-size: 16px; font-weight: bold; }
        .top-bar a { color: #ecf0f1; text-decoration: none; margin-right: 15px; font-size: 13px; }
        .top-bar a:hover { color: #3498db; }
        .category-header {
            background: linear-gradient(135deg, <?= htmlspecialchars($category['color']) ?>, <?= htmlspecialchars($category['color']) ?>dd);
            color: #fff; padding: 60px 20px; text-align: center;
        }
        .category-header .icon { font-size: 60px; margin-bottom: 15px; }
        .category-header h1 { margin: 0 0 10px; font-size: 36px; }
        .category-header p { margin: 0; opacity: 0.9; font-size: 16px; }
        .post-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; height: 100%; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .post-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .post-card .thumb { width: 100%; height: 180px; object-fit: cover; }
        .post-card .no-thumb { height: 180px; display: flex; align-items: center; justify-content: center; font-size: 60px; background: linear-gradient(135deg, #667eea, #764ba2); }
        .post-card .body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .post-card h4 { color: #2c3e50; font-size: 17px; margin: 0 0 10px; line-height: 1.4; }
        .post-card p { color: #7f8c8d; font-size: 13px; line-height: 1.7; flex: 1; }
        .post-card .btn { margin-top: auto; align-self: flex-start; }
        .pagination .page-link { color: #3498db; }
        .pagination .active .page-link { background: #3498db; border-color: #3498db; color: #fff; }
        @media (max-width: 768px) {
            .top-bar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
            .category-header { padding: 40px 15px; }
            .category-header h1 { font-size: 26px; }
        }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="logo">🏠 <?= htmlspecialchars($siteName) ?></a>
    <div>
        <a href="index.php">خانه</a>
        <?php if (isLoggedIn()): ?>
            <a href="admin/index.php">داشبورد</a>
            <a href="admin/logout.php">خروج</a>
        <?php endif; ?>
    </div>
</div>

<!-- هدر دسته -->
<div class="category-header">
    <div class="icon"><?= htmlspecialchars($category['icon'] ?: '📁') ?></div>
    <h1><?= htmlspecialchars($category['name']) ?></h1>
    <?php if ($category['description']): ?>
        <p><?= htmlspecialchars($category['description']) ?></p>
    <?php endif; ?>
    <div style="margin-top: 15px; opacity: 0.9; font-size: 14px;">
        📝 <?= $total ?> مطلب در این دسته
    </div>
</div>

<div class="container my-5">

    <?php if (empty($posts)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <h5>هنوز مطلبی در این دسته منتشر نشده است</h5>
            <a href="index.php" class="btn btn-primary mt-3">مشاهده همه مطالب</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($posts as $p): ?>
                <div class="col-md-4">
                    <div class="post-card">
                        <?php if (!empty($p['featured_image'])): ?>
                            <img src="<?= htmlspecialchars($p['featured_image']) ?>" class="thumb" alt="<?= htmlspecialchars($p['title']) ?>">
                        <?php else: ?>
                            <div class="no-thumb"><?= $p['type_icon'] ?: '📄' ?></div>
                        <?php endif; ?>
                        <div class="body">
                            <h4><?= htmlspecialchars($p['title']) ?></h4>
                            <p><?= htmlspecialchars(mb_substr($p['excerpt'] ?: strip_tags($p['content']), 0, 120)) ?>...</p>
                            <div style="font-size: 11px; color: #95a5a6; margin-bottom: 12px;">
                                👤 <?= htmlspecialchars($p['author'] ?? 'ناشناس') ?> 
                                | 📅 <?= date('Y/m/d', strtotime($p['created_at'])) ?>
                            </div>
                            <a href="post.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">بیشتر بخوانید →</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?= urlencode($slug) ?>&page=<?= $page - 1 ?>">← قبلی</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?slug=<?= urlencode($slug) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?slug=<?= urlencode($slug) ?>&page=<?= $page + 1 ?>">بعدی →</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>

<footer style="background: #2c3e50; color: #bdc3c7; padding: 25px; text-align: center; font-size: 13px;">
    © ۲۰۲۶ - <?= htmlspecialchars($siteName) ?>
</footer>

</body>
</html>

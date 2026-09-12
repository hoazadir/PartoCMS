<?php
require_once __DIR__ . '/config.php';

$q = trim($_GET['q'] ?? '');
$pdo = getDB();

$posts = [];
$total = 0;

if (!empty($q)) {
    // صفحه‌بندی
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 9;
    $offset = ($page - 1) * $perPage;

    // جستجو در عنوان، محتوا، خلاصه
    $searchTerm = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM content_items 
        WHERE status = 'published' 
        AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $total = $stmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

    $stmt = $pdo->prepare("
        SELECT ci.*, ct.icon as type_icon, u.username as author,
               c.name as category_name, c.color as category_color, c.icon as category_icon
        FROM content_items ci
        LEFT JOIN content_types ct ON ct.id = ci.type_id
        LEFT JOIN users u ON u.id = ci.author_id
        LEFT JOIN categories c ON c.id = ci.category_id
        WHERE ci.status = 'published' 
        AND (ci.title LIKE ? OR ci.content LIKE ? OR ci.excerpt LIKE ?)
        ORDER BY 
            CASE 
                WHEN ci.title LIKE ? THEN 1
                WHEN ci.excerpt LIKE ? THEN 2
                ELSE 3
            END,
            ci.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $posts = $stmt->fetchAll();
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جستجو: <?= htmlspecialchars($q) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; margin: 0; }
        .top-bar { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar .logo { color: #fff; text-decoration: none; font-size: 16px; font-weight: bold; }
        .top-bar a { color: #ecf0f1; text-decoration: none; margin-right: 15px; font-size: 13px; }
        .search-hero { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 60px 20px; text-align: center; }
        .search-hero h1 { margin: 0 0 20px; font-size: 32px; }
        .search-hero .search-box { max-width: 600px; margin: 0 auto; display: flex; gap: 10px; }
        .search-hero input { flex: 1; padding: 14px 20px; border: none; border-radius: 30px; font-family: Tahoma; font-size: 15px; }
        .search-hero input:focus { outline: none; box-shadow: 0 0 0 3px rgba(255,255,255,0.3); }
        .search-hero button { padding: 14px 30px; background: #fff; color: #667eea; border: none; border-radius: 30px; font-family: Tahoma; font-weight: bold; cursor: pointer; font-size: 15px; }
        .search-hero button:hover { background: #f8f9fa; }
        .post-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; height: 100%; transition: transform 0.2s; display: flex; flex-direction: column; }
        .post-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .post-card .thumb { width: 100%; height: 180px; object-fit: cover; }
        .post-card .no-thumb { height: 180px; display: flex; align-items: center; justify-content: center; font-size: 60px; background: linear-gradient(135deg, #667eea, #764ba2); }
        .post-card .body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .post-card h4 { color: #2c3e50; font-size: 17px; margin: 0 0 10px; }
        .post-card p { color: #7f8c8d; font-size: 13px; line-height: 1.7; flex: 1; }
        .highlight { background: #fff3cd; padding: 1px 3px; border-radius: 3px; }
        @media (max-width: 768px) {
            .top-bar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
            .search-hero { padding: 40px 15px; }
            .search-hero h1 { font-size: 22px; }
            .search-hero .search-box { flex-direction: column; }
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

<!-- هدر جستجو -->
<div class="search-hero">
    <h1>🔍 جستجو در سایت</h1>
    <form method="get" class="search-box">
        <input type="text" name="q" placeholder="کلمه مورد نظر را وارد کنید..." value="<?= htmlspecialchars($q) ?>" required>
        <button type="submit">جستجو</button>
    </form>
</div>

<div class="container my-5">

    <?php if (!empty($q)): ?>
        <div class="mb-4">
            <h4 style="color: #2c3e50;">
                نتایج جستجو برای: <span style="color: #3498db;">"<?= htmlspecialchars($q) ?>"</span>
            </h4>
            <p class="text-muted"><?= $total ?> نتیجه پیدا شد</p>
        </div>
    <?php endif; ?>

    <?php if (empty($q)): ?>
        <div class="text-center py-5">
            <div style="font-size: 80px;">🔍</div>
            <h5 class="text-muted mt-3">برای شروع، کلمه‌ای را جستجو کنید</h5>
        </div>
    <?php elseif (empty($posts)): ?>
        <div class="alert alert-warning text-center py-5">
            <div style="font-size: 60px;">😕</div>
            <h5>نتیجه‌ای یافت نشد</h5>
            <p class="text-muted">لطفاً کلمات دیگری را امتحان کنید</p>
            <a href="index.php" class="btn btn-primary mt-3">بازگشت به خانه</a>
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
                                📅 <?= date('Y/m/d', strtotime($p['created_at'])) ?>
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
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">← قبلی</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">بعدی →</a>
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

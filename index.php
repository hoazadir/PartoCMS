<?php
require_once __DIR__ . '/config.php';

$pdo = getDB();

// صفحه‌بندی
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 6;
$offset = ($page - 1) * $perPage;

// تعداد کل
$total = $pdo->query("SELECT COUNT(*) FROM content_items WHERE status = 'published'")->fetchColumn();
$totalPages = ceil($total / $perPage);

// آخرین مقالات
$posts = $pdo->query("
    SELECT ci.*, ct.name as type_name, ct.icon as type_icon, u.username as author,
           c.name as category_name, c.slug as category_slug, c.color as category_color, c.icon as category_icon
    FROM content_items ci
    LEFT JOIN content_types ct ON ct.id = ci.type_id
    LEFT JOIN users u ON u.id = ci.author_id
    LEFT JOIN categories c ON c.id = ci.category_id
    WHERE ci.status = 'published'
    ORDER BY ci.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
")->fetchAll();


// 🌍 اعمال ترجمه بر اساس زبان فعلی
$posts = applyTranslationsToPosts($posts);

// دسته‌های فعال
$categories = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM content_items WHERE category_id = c.id AND status = 'published') as post_count
    FROM categories c
    WHERE c.is_active = 1 AND c.slug != 'uncategorized'
    HAVING post_count > 0
    ORDER BY c.sort_order, c.name
")->fetchAll();

$siteName = getSetting('site_name', 'وب‌سایت من');
$siteDescription = getSetting('site_description', 'ساخته شده با سیستم مدیریت محتوا');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($siteDescription) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; margin: 0; background: #f8f9fa; }
        .hero { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 80px 20px; text-align: center; }
        .hero h1 { font-size: 42px; margin-bottom: 15px; }
        .hero p { font-size: 18px; opacity: 0.9; margin-bottom: 25px; }
        .hero .search-form { max-width: 500px; margin: 0 auto; display: flex; gap: 10px; }
        .hero .search-form input { flex: 1; padding: 12px 20px; border: none; border-radius: 25px; font-family: Tahoma; }
        .hero .search-form button { padding: 12px 25px; background: #fff; color: #667eea; border: none; border-radius: 25px; font-family: Tahoma; font-weight: bold; cursor: pointer; }

        .admin-bar { background: #2c3e50; color: #fff; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; flex-wrap: wrap; gap: 10px; }
        .admin-bar a { color: #3498db; text-decoration: none; margin: 0 8px; }

        /* منوی اصلی */
        .main-menu-bar { background: #34495e; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .main-nav { display: flex; gap: 5px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; }
        .main-nav > li { position: relative; }
        .main-nav > li > a { color: #ecf0f1 !important; text-decoration: none; padding: 15px 18px; display: block; font-size: 14px; transition: background 0.2s; border-bottom: 3px solid transparent; }
        .main-nav > li > a:hover { background: rgba(255,255,255,0.1); border-bottom-color: #3498db; }
        .main-nav .submenu { display: none; position: absolute; top: 100%; right: 0; background: #2c3e50; min-width: 200px; list-style: none; padding: 5px 0; margin: 0; border-radius: 0 0 8px 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 100; }
        .main-nav li:hover > .submenu { display: block; }
        .main-nav .submenu li a { color: #ecf0f1 !important; text-decoration: none; padding: 10px 20px; display: block; font-size: 13px; }
        .main-nav .submenu li a:hover { background: rgba(255,255,255,0.1); color: #3498db !important; }
        .main-nav .submenu .submenu { top: 0; right: 100%; border-radius: 8px; }

        /* دسته‌ها */
        .categories-bar { background: #fff; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow-x: auto; }
        .categories-bar .container { display: flex; gap: 10px; flex-wrap: wrap; }
        .category-chip { display: inline-flex; align-items: center; gap: 6px; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: bold; transition: transform 0.2s; border: 2px solid; }
        .category-chip:hover { transform: translateY(-2px); }

        /* کارت پست */
        .post-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; height: 100%; transition: all 0.2s; display: flex; flex-direction: column; }
        .post-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .post-card .thumb { width: 100%; height: 200px; object-fit: cover; }
        .post-card .no-thumb { height: 200px; display: flex; align-items: center; justify-content: center; font-size: 60px; background: linear-gradient(135deg, #667eea, #764ba2); }
        .post-card .body { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .post-card .cat-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; color: #fff; font-size: 11px; margin-bottom: 10px; font-weight: bold; }
        .post-card h4 { color: #2c3e50; font-size: 18px; margin: 0 0 10px; line-height: 1.4; }
        .post-card p { color: #7f8c8d; font-size: 13px; line-height: 1.7; flex: 1; }
        .post-card .meta { font-size: 11px; color: #95a5a6; margin-bottom: 12px; }

        .pagination .page-link { color: #3498db; }
        .pagination .active .page-link { background: #3498db; border-color: #3498db; color: #fff; }

        @media (max-width: 768px) {
            .hero { padding: 50px 15px; }
            .hero h1 { font-size: 28px; }
            .hero p { font-size: 15px; }
            .main-nav { justify-content: center; }
        }
    
    /* ==================== DIRECTION SUPPORT ==================== */
    /* RTL پیش‌فرض — Bootstrap RTL خودش کار می‌کند */

    /* LTR — Bootstrap LTR خودش کار می‌کند */

    /* اصلاحات اضافی برای عناصر خاص */
    html[dir="ltr"] body { direction: ltr; text-align: left; }
    html[dir="rtl"] body { direction: rtl; text-align: right; }

    /* منوی اصلی */
    html[dir="ltr"] .main-nav { flex-direction: row; }
    html[dir="rtl"] .main-nav { flex-direction: row-reverse; }

    /* زیرمنو در LTR */
    html[dir="ltr"] .submenu { right: auto; left: 100%; }

    /* pagination */
    html[dir="ltr"] .article .content table th { text-align: left; }
    html[dir="rtl"] .article .content table th { text-align: right; }

    /* blockquote */
    html[dir="ltr"] .article .content blockquote { border-right: none; border-left: 4px solid #3498db; border-radius: 8px 0 0 8px; }

</style>
</head>
<body>

<?php if (isLoggedIn()): ?>
<div class="admin-bar" style="background: #2c3e50; color: #fff; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
    <span>👋 <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role_name'] ?? '') ?>)</span>
    <div>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'editor', 'author'])): ?>
            <a href="admin/index.php" style="color:#3498db;text-decoration:none;margin:0 8px;"><?= __t('fe_admin_panel', [], 'پنل مدیریت') ?></a>
        <?php endif; ?>
        <a href="user/index.php" style="color:#3498db;text-decoration:none;margin:0 8px;"><?= __t('fe_user_panel', [], 'پنل کاربری') ?></a>
        <a href="logout.php" style="color:#e74c3c;text-decoration:none;margin:0 8px;"><?= __t('fe_logout', [], 'خروج') ?></a>
    </div>
</div>
<?php else: ?>
<div class="admin-bar" style="background: #2c3e50; color: #fff; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
    <span><?= __t('fe_welcome', [], 'خوش آمدید!') ?></span>
    <div>
        <a href="login.php" style="color:#3498db;text-decoration:none;margin:0 8px;"><?= __t('fe_login', [], 'ورود') ?></a>
        <a href="register.php" style="color:#27ae60;text-decoration:none;margin:0 8px;"><?= __t('fe_register', [], 'ثبت‌نام') ?></a>
    </div>
</div>
<?php endif; ?>

<!-- منوی اصلی -->
<?php
$menuHtml = renderMenu('main-menu', 'main-nav', 'nav-item');
if ($menuHtml):
?>
<div class="main-menu-bar">
    <div class="container">
        <?= $menuHtml ?>
    </div>
</div>
<?php endif; ?>

<!-- Hero -->
<section class="hero">
    <div class="container">
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <p><?= htmlspecialchars($siteDescription) ?></p>
        <form method="get" action="search.php" class="search-form">
            <input type="text" name="q" placeholder="<?= __t('fe_search_site', [], 'جستجو در سایت...') ?>">
            <button type="submit"><i class="bi bi-search"></i> جستجو</button>
        </form>
    </div>
</section>

<!-- دسته‌ها -->
<?php if (!empty($categories)): ?>
<div class="categories-bar">
    <div class="container">
        <?php foreach ($categories as $c): ?>
            <a href="category.php?slug=<?= urlencode($c['slug']) ?>"
               class="category-chip"
               style="background: <?= htmlspecialchars($c['color']) ?>15; color: <?= htmlspecialchars($c['color']) ?>; border-color: <?= htmlspecialchars($c['color']) ?>;">
                <?= htmlspecialchars($c['icon']) ?>
                <?= htmlspecialchars($c['name']) ?>
                <span style="background: <?= htmlspecialchars($c['color']) ?>; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 10px;">
                    <?= $c['post_count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- محتوا -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="margin:0; color: #2c3e50;">📰 آخرین مطالب</h2>
        <span class="badge bg-secondary"><?= $total ?> مطلب</span>
    </div>

    <?php if (empty($posts)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
            هنوز محتوایی منتشر نشده است.
            <?php if (isLoggedIn()): ?>
                <br><a href="modules/content/admin.php" class="btn btn-primary mt-3">➕ ایجاد اولین محتوا</a>
            <?php endif; ?>
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
                            <?php if (!empty($p['category_name'])): ?>
                                <a href="category.php?slug=<?= urlencode($p['category_slug']) ?>"
                                   class="cat-badge"
                                   style="background:<?= htmlspecialchars($p['category_color']) ?>;text-decoration:none;">
                                    <?= htmlspecialchars($p['category_icon']) ?>
                                    <?= htmlspecialchars($p['category_name']) ?>
                                </a>
                            <?php endif; ?>
                            <h4><?= htmlspecialchars($p['title']) ?></h4>
                            <p><?= htmlspecialchars(mb_substr($p['excerpt'] ?: strip_tags($p['content']), 0, 100)) ?>...</p>
                            <div class="meta">
                                👤 <?= htmlspecialchars($p['author'] ?? 'ناشناس') ?>
                                | 📅 <?= date('Y/m/d', strtotime($p['created_at'])) ?>
                            </div>
                            <a href="post.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary" style="align-self:flex-start;"><?= __t('fe_read_more', [], 'بیشتر بخوانید →') ?></a>
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
                            <a class="page-link" href="?page=<?= $page - 1 ?>"><?= __t('fe_previous', [], '← قبلی') ?></a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>"><?= __t('fe_next', [], 'بعدی →') ?></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<footer style="background: #2c3e50; color: #bdc3c7; padding: 25px; text-align: center; font-size: 13px; margin-top: 50px;">
    © ۲۰۲۶ - <?= htmlspecialchars($siteName) ?>
</footer>

</body>
</html>

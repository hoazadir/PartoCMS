<?php
require_once __DIR__ . '/config.php';

$q = trim($_GET['q'] ?? '');
$pdo = getDB();

$posts = [];
$total = 0;
$totalPages = 1;
$page = 1;

// زبان فعلی
$langInfo = getCurrentLanguageInfo();
$currentLangId = getCurrentLanguageId();
$isDefaultLang = isDefaultLanguage();

if (!empty($q)) {
    // صفحه‌بندی
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 9;
    $offset = ($page - 1) * $perPage;

    $searchTerm = '%' . $q . '%';

    if ($isDefaultLang || !$currentLangId) {
        // ============ جستجو در زبان پیش‌فرض (فارسی) ============
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM content_items
            WHERE status = 'published'
            AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $total = $stmt->fetchColumn();

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
    } else {
        // ============ جستجو در ترجمه‌ها (زبان غیرفارسی) ============
        // ترکیب ترجمه‌های زبان فعلی + fallback به زبان اصلی

        // تعداد کل
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ci.id)
            FROM content_items ci
            LEFT JOIN content_translations ctr
                ON ctr.content_id = ci.id
                AND ctr.language_id = ?
                AND ctr.status IN ('published', 'auto')
            WHERE ci.status = 'published'
            AND (
                ctr.title LIKE ?
                OR ctr.content LIKE ?
                OR ctr.excerpt LIKE ?
                OR ci.title LIKE ?
                OR ci.content LIKE ?
                OR ci.excerpt LIKE ?
            )
        ");
        $stmt->execute([
            $currentLangId,
            $searchTerm, $searchTerm, $searchTerm,
            $searchTerm, $searchTerm, $searchTerm,
        ]);
        $total = $stmt->fetchColumn();

        // رکوردها
        $stmt = $pdo->prepare("
            SELECT
                ci.*,
                COALESCE(NULLIF(ctr.title, ''), ci.title) as title,
                COALESCE(NULLIF(ctr.excerpt, ''), ci.excerpt) as excerpt,
                COALESCE(NULLIF(ctr.content, ''), ci.content) as content,
                ct.icon as type_icon,
                u.username as author,
                c.name as category_name,
                c.color as category_color,
                c.icon as category_icon,
                CASE WHEN ctr.content_id IS NOT NULL THEN 1 ELSE 0 END as has_translation
            FROM content_items ci
            LEFT JOIN content_translations ctr
                ON ctr.content_id = ci.id
                AND ctr.language_id = ?
                AND ctr.status IN ('published', 'auto')
            LEFT JOIN content_types ct ON ct.id = ci.type_id
            LEFT JOIN users u ON u.id = ci.author_id
            LEFT JOIN categories c ON c.id = ci.category_id
            WHERE ci.status = 'published'
            AND (
                ctr.title LIKE ?
                OR ctr.content LIKE ?
                OR ctr.excerpt LIKE ?
                OR ci.title LIKE ?
                OR ci.content LIKE ?
                OR ci.excerpt LIKE ?
            )
            ORDER BY
                CASE
                    WHEN ctr.title LIKE ? THEN 1
                    WHEN ctr.excerpt LIKE ? THEN 2
                    WHEN ci.title LIKE ? THEN 3
                    ELSE 4
                END,
                ci.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute([
            $currentLangId,
            $searchTerm, $searchTerm, $searchTerm,
            $searchTerm, $searchTerm, $searchTerm,
            $searchTerm, $searchTerm, $searchTerm,
        ]);
        $posts = $stmt->fetchAll();

        // ترجمه نام دسته‌ها
        if (!empty($posts)) {
            $posts = applyTranslationsToPosts($posts);
            // ترجمه نام دسته
            foreach ($posts as &$p) {
                if (!empty($p['category_name']) && !empty($p['category_id'])) {
                    $catInfo = applyTranslationToCategory([
                        'id' => $p['category_id'],
                        'name' => $p['category_name'],
                        'description' => '',
                    ]);
                    if (!empty($catInfo['name'])) {
                        $p['category_name'] = $catInfo['name'];
                    }
                }
            }
            unset($p);
        }
    }

    $totalPages = max(1, (int) ceil($total / $perPage));
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __t('fe_search', [], 'جستجو') ?>: <?= htmlspecialchars($q) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; margin: 0; }
        .top-bar { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar .logo { color: #fff; text-decoration: none; font-size: 16px; font-weight: bold; }
        .top-bar a { color: #ecf0f1; text-decoration: none; margin-right: 15px; font-size: 13px; }
        .top-bar a:hover { color: #3498db; }
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
        .lang-notice { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 6px 12px; border-radius: 15px; display: inline-block; margin-bottom: 15px; }
        @media (max-width: 768px) {
            .top-bar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
            .search-hero { padding: 40px 15px; }
            .search-hero h1 { font-size: 22px; }
            .search-hero .search-box { flex-direction: column; }
        }

    /* ==================== DIRECTION SUPPORT ==================== */
    html[dir="ltr"] body { direction: ltr; text-align: left; }
    html[dir="rtl"] body { direction: rtl; text-align: right; }
    html[dir="ltr"] .main-nav { flex-direction: row; }
    html[dir="rtl"] .main-nav { flex-direction: row-reverse; }
    html[dir="ltr"] .submenu { right: auto; left: 100%; }
</style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="logo">🏠 <?= htmlspecialchars($siteName) ?></a>
    <div>
        <a href="index.php"><?= __t('fe_home', [], 'خانه') ?></a>
        <?php if (isLoggedIn()): ?>
            <a href="admin/index.php"><?= __t('fe_dashboard', [], 'داشبورد') ?></a>
            <a href="admin/logout.php"><?= __t('fe_logout', [], 'خروج') ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- هدر جستجو -->
<div class="search-hero">
    <h1>🔍 <?= __t('fe_search_in_site', [], 'جستجو در سایت') ?></h1>
    <form method="get" class="search-box">
        <input type="text" name="q" placeholder="<?= __t('fe_search_placeholder', [], 'کلمه مورد نظر را وارد کنید...') ?>" value="<?= htmlspecialchars($q) ?>" required>
        <button type="submit"><?= __t('fe_search', [], 'جستجو') ?></button>
    </form>
</div>

<div class="container my-5">

    <?php if (!empty($q)): ?>
        <div class="mb-4">
            <h4 style="color: #2c3e50;">
                <?= __t('fe_search_results_for', [], 'نتایج جستجو برای:') ?>
                <span style="color: #3498db;">"<?= htmlspecialchars($q) ?>"</span>
            </h4>
            <p class="text-muted"><?= $total ?> <?= __t('fe_results_found', [], 'نتیجه پیدا شد') ?></p>

            <?php if (!$isDefaultLang && $langInfo): ?>
                <span class="lang-notice">
                    🌍 <?= __t('fe_searching_in', [], 'جستجو در:') ?>
                    <?= htmlspecialchars($langInfo['flag'] ?? '🌐') ?>
                    <?= htmlspecialchars($langInfo['native_name'] ?? $langInfo['code'] ?? '') ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($q)): ?>
        <div class="text-center py-5">
            <div style="font-size: 80px;">🔍</div>
            <h5 class="text-muted mt-3"><?= __t('fe_search_start', [], 'برای شروع، کلمه‌ای را جستجو کنید') ?></h5>
        </div>
    <?php elseif (empty($posts)): ?>
        <div class="alert alert-warning text-center py-5">
            <div style="font-size: 60px;">😕</div>
            <h5><?= __t('fe_no_results', [], 'نتیجه‌ای یافت نشد') ?></h5>
            <p class="text-muted"><?= __t('fe_try_other_words', [], 'لطفاً کلمات دیگری را امتحان کنید') ?></p>
            <a href="index.php" class="btn btn-primary mt-3"><?= __t('fe_back_home', [], 'بازگشت به خانه') ?></a>
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
                                👤 <?= htmlspecialchars($p['author'] ?? __t('fe_anonymous', [], 'ناشناس')) ?>
                                | 📅 <?= date('Y/m/d', strtotime($p['created_at'])) ?>
                            </div>
                            <a href="post.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><?= __t('fe_read_more', [], 'بیشتر بخوانید →') ?></a>
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
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>"><?= __t('fe_previous', [], '← قبلی') ?></a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>"><?= __t('fe_next', [], 'بعدی →') ?></a>
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

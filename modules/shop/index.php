<?php
/**
 * PartoCMS - Shop Module Entry Point
 * نقطه ورود ماژول فروشگاه (فرانت‌اند)
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/includes/ShopManager.php';

// مقداردهی اولیه
$pdo = getDB();
$shop = new ShopManager($pdo);

// بررسی فعال بودن ماژول
if (!$shop->getSetting('enabled', '1')) {
    header('HTTP/1.0 503 Service Unavailable');
    die('فروشگاه موقتاً غیرفعال است.');
}

// بارگذاری هدر سایت
$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'فروشگاه | ' . $siteName;

// دریافت محصولات (اگر جدول وجود دارد)
$products = [];
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM shop_products p
        LEFT JOIN shop_categories c ON c.id = p.category_id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT * FROM shop_categories
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // جداول هنوز ساخته نشده‌اند
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/">
            <i class="bi bi-shop"></i> <?= htmlspecialchars($siteName) ?>
        </a>
        <div class="ms-auto">
            <a href="<?= SITE_URL ?>/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house"></i> خانه
            </a>
            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-cart"></i> سبد خرید
            </a>
        </div>
    </div>
</nav>

<div class="bg-primary text-white py-5">
    <div class="container text-center">
        <h1><i class="bi bi-shop-window"></i> فروشگاه</h1>
        <p class="lead">محصولات با کیفیت با بهترین قیمت</p>
    </div>
</div>

<?php if (!empty($categories)): ?>
<div class="container my-4">
    <h3>دسته‌بندی‌ها</h3>
    <div class="row g-3">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-3">
                <a href="<?= $shop->getUrl('category.php?slug=' . urlencode($cat['slug'])) ?>" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="bi bi-tag fs-3"></i>
                        <h5 class="card-title mt-2"><?= htmlspecialchars($cat['name']) ?></h5>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-box"></i> محصولات</h3>
        <span class="badge bg-secondary"><?= count($products) ?> محصول</span>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle fs-1"></i>
            <h4 class="mt-3">هنوز محصولی اضافه نشده است</h4>
            <p>به زودی محصولات در این بخش نمایش داده می‌شوند.</p>
            <?php if ($shop->isAdmin()): ?>
                <a href="<?= $shop->getAdminUrl('products.php') ?>" class="btn btn-primary">
                    <i class="bi bi-plus"></i> افزودن محصول
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= htmlspecialchars($p['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
                                <i class="bi bi-image fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <?php if (!empty($p['category_name'])): ?>
                                <span class="badge bg-info mb-2"><?= htmlspecialchars($p['category_name']) ?></span>
                            <?php endif; ?>
                            <h5 class="card-title"><?= htmlspecialchars($p['name']) ?></h5>
                            <p class="card-text text-muted small flex-grow-1">
                                <?= htmlspecialchars(mb_substr(strip_tags($p['description'] ?? ''), 0, 80)) ?>...
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary"><?= $shop->formatPrice((float) $p['price']) ?></strong>
                                <a href="<?= $shop->getUrl('product.php?id=' . $p['id']) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> مشاهده
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمام حقوق محفوظ است.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

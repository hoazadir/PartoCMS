<?php
/**
 * PartoCMS - Shop - Product Display
 * نمایش محصول در فرانت‌اند
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/includes/ShopManager.php';
require_once __DIR__ . '/includes/ProductManager.php';
require_once __DIR__ . '/includes/CategoryManager.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$productManager = new ProductManager($pdo);
$catManager = new CategoryManager($pdo);

$id = (int) ($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if ($id > 0) {
    $product = $productManager->getById($id);
} elseif (!empty($slug)) {
    $product = $productManager->getBySlug($slug);
} else {
    $product = null;
}

if (!$product || $product['status'] !== 'published') {
    header('HTTP/1.0 404 Not Found');
    die('محصول یافت نشد');
}

// افزایش بازدید
$productManager->incrementViews((int) $product['id']);

// محصولات مرتبط
$related = [];
if (!empty($product['category_id'])) {
    $related = $productManager->getRelated((int) $product['id'], (int) $product['category_id'], 4);
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = $product['name'] . ' | ' . $siteName;

// SEO
$seo = getSeo();
$seo->set('title', $product['meta_title'] ?: $product['name']);
$seo->set('description', $product['meta_description'] ?: mb_substr(strip_tags($product['short_description'] ?? $product['description'] ?? ''), 0, 160));
$seo->set('keywords', $product['meta_keywords'] ?? '');
$seo->set('canonical', $shop->getUrl('product.php?id=' . $product['id']));
if (!empty($product['image'])) {
    $seo->set('og_image', $product['image']);
}
$seo->set('og_type', 'product');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $seo->render() ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; }
        .product-image { max-height: 500px; object-fit: contain; border-radius: 12px; }
        .price-tag { font-size: 28px; font-weight: bold; color: #06b6d4; }
        .old-price { text-decoration: line-through; color: #94a3b8; }
    </style>
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
            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-shop-window"></i> فروشگاه
            </a>
            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-cart"></i> سبد خرید
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= SITE_URL ?>/">خانه</a></li>
            <li class="breadcrumb-item"><a href="<?= $shop->getUrl('index.php') ?>">فروشگاه</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="<?= $shop->getUrl('category.php?slug=' . urlencode($product['category_slug'])) ?>">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <!-- Product Detail -->
    <div class="row g-4">
        <div class="col-md-6">
            <?php if (!empty($product['image'])): ?>
                <img src="<?= htmlspecialchars($product['image']) ?>" class="img-fluid product-image" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <div class="bg-white d-flex align-items-center justify-content-center rounded" style="height: 400px;">
                    <i class="bi bi-image text-muted" style="font-size: 100px;"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h1><?= htmlspecialchars($product['name']) ?></h1>

            <?php if (!empty($product['category_name'])): ?>
                <span class="badge bg-info mb-2"><?= htmlspecialchars($product['category_name']) ?></span>
            <?php endif; ?>

            <div class="my-3">
                <span class="price-tag"><?= $shop->formatPrice((float) $product['price']) ?></span>
                <?php if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                    <span class="old-price ms-2"><?= $shop->formatPrice((float) $product['compare_price']) ?></span>
                    <span class="badge bg-danger ms-2">
                        <?= round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100) ?>% تخفیف
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['short_description'])): ?>
                <p class="text-muted"><?= nl2br(htmlspecialchars($product['short_description'])) ?></p>
            <?php endif; ?>

            <div class="my-4">
                <?php if ($product['quantity'] > 0): ?>
                    <span class="badge bg-success fs-6">✓ موجود در انبار (<?= (int) $product['quantity'] ?>)</span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6">✗ ناموجود</span>
                <?php endif; ?>
            </div>

            <div class="d-grid gap-2 d-md-flex">
                <button class="btn btn-primary btn-lg flex-fill" onclick="addToCart(<?= (int) $product['id'] ?>)" <?= $product['quantity'] <= 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-cart-plus"></i> افزودن به سبد خرید
                </button>
                <button class="btn btn-outline-danger btn-lg">
                    <i class="bi bi-heart"></i>
                </button>
            </div>

            <?php if (!empty($product['sku'])): ?>
                <p class="mt-3 text-muted small">کد محصول: <code><?= htmlspecialchars($product['sku']) ?></code></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Description -->
    <?php if (!empty($product['description'])): ?>
        <div class="card mt-5">
            <div class="card-header">
                <h4><i class="bi bi-file-text"></i> توضیحات کامل</h4>
            </div>
            <div class="card-body">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
        <div class="mt-5">
            <h3 class="mb-4"><i class="bi bi-box-seam"></i> محصولات مرتبط</h3>
            <div class="row g-4">
                <?php foreach ($related as $r): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100">
                            <?php if (!empty($r['image'])): ?>
                                <img src="<?= htmlspecialchars($r['image']) ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                                    <i class="bi bi-image fs-1 text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h6><?= htmlspecialchars($r['name']) ?></h6>
                                <strong class="text-primary"><?= $shop->formatPrice((float) $r['price']) ?></strong>
                            </div>
                            <div class="card-footer">
                                <a href="<?= $shop->getUrl('product.php?id=' . $r['id']) ?>" class="btn btn-sm btn-primary w-100">
                                    مشاهده
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمام حقوق محفوظ است.</p>
    </div>
</footer>

<script>
function addToCart(productId) {
    alert('محصول به سبد خرید اضافه شد (ID: ' + productId + ')');
    // TODO: پیاده‌سازی در گام ۲.۳
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

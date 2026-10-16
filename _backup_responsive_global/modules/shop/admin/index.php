<?php
/**
 * PartoCMS - Shop Admin Dashboard
 * داشبورد مدیریت فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$stats = $shop->getStats();

$pageTitle = 'داشبورد فروشگاه';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | PartoCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: Tahoma, sans-serif; }
        .main { margin-right: 260px; padding: 20px; }
        @media (max-width: 900px) { .main { margin-right: 0; } }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-shop"></i> داشبورد فروشگاه</h1>
        <a href="<?= $shop->getUrl() ?>" class="btn btn-primary" target="_blank">
            <i class="bi bi-eye"></i> مشاهده فروشگاه
        </a>
    </div>

    <!-- آمار -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-primary text-white">
                    <i class="bi bi-box"></i>
                </div>
                <div>
                    <div class="text-muted small">محصولات</div>
                    <div class="fs-4 fw-bold"><?= $stats['products'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-info text-white">
                    <i class="bi bi-tags"></i>
                </div>
                <div>
                    <div class="text-muted small">دسته‌بندی‌ها</div>
                    <div class="fs-4 fw-bold"><?= $stats['categories'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-warning text-white">
                    <i class="bi bi-bag-check"></i>
                </div>
                <div>
                    <div class="text-muted small">سفارشات</div>
                    <div class="fs-4 fw-bold"><?= $stats['orders'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-success text-white">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <div class="text-muted small">مشتریان</div>
                    <div class="fs-4 fw-bold"><?= $stats['customers'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- درآمد و سفارشات معلق -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">درآمد کل</div>
                        <div class="fs-3 fw-bold text-success">
                            <?= $shop->formatPrice($stats['revenue']) ?>
                        </div>
                    </div>
                    <i class="bi bi-cash-coin fs-1 text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">سفارشات معلق</div>
                        <div class="fs-3 fw-bold text-warning">
                            <?= $stats['pending_orders'] ?>
                        </div>
                    </div>
                    <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- دسترسی سریع -->
    <div class="stat-card">
        <h5 class="mb-3"><i class="bi bi-lightning"></i> دسترسی سریع</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="<?= $shop->getAdminUrl('products.php') ?>" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-box"></i><br>مدیریت محصولات
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= $shop->getAdminUrl('categories.php') ?>" class="btn btn-outline-info w-100 py-3">
                    <i class="bi bi-tags"></i><br>مدیریت دسته‌بندی
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= $shop->getAdminUrl('orders.php') ?>" class="btn btn-outline-warning w-100 py-3">
                    <i class="bi bi-bag-check"></i><br>مدیریت سفارشات
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= $shop->getAdminUrl('gateways.php') ?>" class="btn btn-outline-success w-100 py-3">
                    <i class="bi bi-credit-card"></i><br>درگاه‌های پرداخت
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

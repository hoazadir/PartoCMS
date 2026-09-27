<?php
/**
 * PartoCMS - Shop - Order Success
 * صفحه موفقیت سفارش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/includes/ShopManager.php';
require_once __DIR__ . '/includes/OrderManager.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$orderManager = new OrderManager($pdo);

$order = $_SESSION['last_order'] ?? null;

if (!$order) {
    header('Location: ' . $shop->getUrl('index.php'));
    exit;
}

// دریافت اطلاعات کامل سفارش
$orderData = $orderManager->getById((int) $order['id']);
$orderItems = $orderData ? $orderManager->getItems((int) $order['id']) : [];

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'سفارش موفق | ' . $siteName;
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; }
        .success-icon { font-size: 80px; color: #10b981; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/">
            <i class="bi bi-shop"></i> <?= htmlspecialchars($siteName) ?>
        </a>
        <div class="ms-auto">
            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-shop-window"></i> فروشگاه
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="text-center mb-5">
        <div class="success-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h1 class="mt-3 text-success">سفارش شما با موفقیت ثبت شد!</h1>
        <p class="lead text-muted">از خرید شما سپاسگزاریم. جزئیات سفارش در ادامه آمده است.</p>
    </div>

    <div class="row g-4">
        <!-- اطلاعات سفارش -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> شماره سفارش: <?= htmlspecialchars($order['number']) ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($orderData): ?>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <strong>وضعیت:</strong>
                                <span class="badge bg-warning">در انتظار پردازش</span>
                            </div>
                            <div class="col-md-6">
                                <strong>وضعیت پرداخت:</strong>
                                <span class="badge bg-secondary">پرداخت نشده</span>
                            </div>
                            <div class="col-md-6">
                                <strong>تاریخ:</strong>
                                <?= date('Y/m/d H:i', strtotime($orderData['created_at'])) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>روش پرداخت:</strong>
                                <?= htmlspecialchars($orderData['payment_method'] ?? 'پرداخت در محل') ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4 mb-3">محصولات سفارش:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>قیمت</th>
                                    <th>تعداد</th>
                                    <th>جمع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= $shop->formatPrice((float) $item['product_price']) ?></td>
                                        <td><?= (int) $item['quantity'] ?></td>
                                        <td><?= $shop->formatPrice((float) $item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($orderData && !empty($orderData['shipping_address'])): ?>
                <div class="card">
                    <div class="card-header"><i class="bi bi-geo-alt"></i> آدرس تحویل</div>
                    <div class="card-body">
                        <pre class="mb-0" style="font-family: Tahoma; white-space: pre-wrap;"><?= htmlspecialchars($orderData['shipping_address']) ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- خلاصه مالی -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-cash-coin"></i> خلاصه مالی</div>
                <div class="card-body">
                    <?php if ($orderData): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>جمع کالاها:</span>
                            <span><?= $shop->formatPrice((float) $orderData['subtotal']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>مالیات:</span>
                            <span><?= $shop->formatPrice((float) $orderData['tax']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>ارسال:</span>
                            <span><?= $orderData['shipping'] == 0 ? 'رایگان' : $shop->formatPrice((float) $orderData['shipping']) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>جمع کل:</strong>
                            <strong class="text-primary fs-5"><?= $shop->formatPrice((float) $orderData['total']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-primary w-100">
                        <i class="bi bi-shop-window"></i> ادامه خرید
                    </a>
                    <button class="btn btn-outline-secondary w-100 mt-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> چاپ سفارش
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

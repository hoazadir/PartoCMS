<?php
/**
 * PartoCMS - Payment Verification
 * تایید پرداخت و بازگشت از درگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/OrderManager.php';
require_once __DIR__ . '/../includes/PaymentManager.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$orderManager = new OrderManager($pdo);
$paymentManager = new PaymentManager($pdo);

$gatewaySlug = $_GET['gateway'] ?? '';
$orderId = (int) ($_GET['order'] ?? 0);

$siteName = getSetting('site_name', 'وب‌سایت من');

$status = 'pending';
$message = 'در حال بررسی پرداخت...';
$order = null;
$verifyResult = null;

try {
    if (empty($gatewaySlug) || $orderId < 1) {
        throw new Exception('اطلاعات پرداخت ناقص است');
    }

    $order = $orderManager->getById($orderId);
    if (!$order) {
        throw new Exception('سفارش یافت نشد');
    }

    // تایید پرداخت
    $params = array_merge($_GET, $_POST);
    $verifyResult = $paymentManager->verifyPayment($orderId, $gatewaySlug, $params);

    if ($verifyResult['ok']) {
        $status = 'success';
        $message = 'پرداخت با موفقیت انجام شد';

        // به‌روزرسانی وضعیت سفارش
        $orderManager->updateStatus($orderId, 'processing', 'پرداخت با موفقیت انجام شد');
    } else {
        $status = 'failed';
        $message = $verifyResult['error'] ?? 'پرداخت ناموفق بود';
    }
} catch (Exception $e) {
    $status = 'error';
    $message = $e->getMessage();
}

$pageTitle = 'نتیجه پرداخت | ' . $siteName;
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
        .result-icon { font-size: 80px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/">
            <i class="bi bi-shop"></i> <?= htmlspecialchars($siteName) ?>
        </a>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center py-5">

                    <?php if ($status === 'success'): ?>
                        <div class="result-icon text-success"><i class="bi bi-check-circle-fill"></i></div>
                        <h1 class="mt-3 text-success">پرداخت موفق!</h1>
                        <p class="lead"><?= htmlspecialchars($message) ?></p>

                        <?php if (!empty($verifyResult['reference_id'])): ?>
                            <div class="alert alert-success d-inline-block">
                                <strong>کد پیگیری:</strong> <?= htmlspecialchars($verifyResult['reference_id']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($order): ?>
                            <p class="text-muted">شماره سفارش: <code><?= htmlspecialchars($order['order_number']) ?></code></p>
                            <p class="text-muted">مبلغ: <?= $shop->formatPrice((float) $order['total']) ?></p>
                        <?php endif; ?>

                        <div class="mt-4">
                            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-primary">
                                <i class="bi bi-shop-window"></i> بازگشت به فروشگاه
                            </a>
                        </div>

                    <?php elseif ($status === 'failed'): ?>
                        <div class="result-icon text-danger"><i class="bi bi-x-circle-fill"></i></div>
                        <h1 class="mt-3 text-danger">پرداخت ناموفق</h1>
                        <p class="lead"><?= htmlspecialchars($message) ?></p>

                        <?php if ($order): ?>
                            <p class="text-muted">شماره سفارش: <code><?= htmlspecialchars($order['order_number']) ?></code></p>
                        <?php endif; ?>

                        <div class="mt-4">
                            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-warning">
                                <i class="bi bi-arrow-repeat"></i> تلاش مجدد
                            </a>
                            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-secondary">
                                <i class="bi bi-shop-window"></i> بازگشت به فروشگاه
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="result-icon text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <h1 class="mt-3 text-warning">خطا در پرداخت</h1>
                        <p class="lead"><?= htmlspecialchars($message) ?></p>

                        <div class="mt-4">
                            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-primary">
                                <i class="bi bi-cart"></i> بازگشت به سبد خرید
                            </a>
                        </div>
                    <?php endif; ?>

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

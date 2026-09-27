<?php
/**
 * PartoCMS - Shop - My Orders
 * لیست سفارشات کاربر
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

// ورود اجباری
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = (int) $_SESSION['user_id'];
$orders = $orderManager->getUserOrders($userId);

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'سفارش‌های من | ' . $siteName;

$statusLabels = [
    'pending'    => ['label' => 'در انتظار پرداخت', 'class' => 'warning'],
    'processing' => ['label' => 'در حال پردازش',   'class' => 'info'],
    'shipped'    => ['label' => 'ارسال شده',       'class' => 'primary'],
    'completed'  => ['label' => 'تکمیل شده',       'class' => 'success'],
    'cancelled'  => ['label' => 'لغو شده',         'class' => 'danger'],
    'refunded'   => ['label' => 'بازگشت داده شده', 'class' => 'secondary'],
];

$paymentLabels = [
    'unpaid'   => ['label' => 'پرداخت نشده', 'class' => 'secondary'],
    'paid'     => ['label' => 'پرداخت شده',  'class' => 'success'],
    'failed'   => ['label' => 'ناموفق',      'class' => 'danger'],
    'refunded' => ['label' => 'بازگشت',      'class' => 'warning'],
];
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
        body { background: #f1f5f9; font-family: Tahoma, sans-serif; }
        .order-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); margin-bottom: 16px; transition: .2s; }
        .order-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.1); }
        .order-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .order-body { padding: 16px 20px; }
        .order-number { font-family: monospace; color: #0891b2; font-weight: bold; }
        .order-meta { display: flex; gap: 24px; flex-wrap: wrap; color: #64748b; font-size: 14px; }
        .order-total { font-size: 20px; font-weight: bold; color: #059669; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/"><?= htmlspecialchars($siteName) ?></a>
        <div>
            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-shop"></i> فروشگاه
            </a>
            <a href="<?= SITE_URL ?>/user/index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-person"></i> پنل کاربری
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-bag-check"></i> سفارش‌های من</h1>
        <span class="badge bg-primary fs-6"><?= count($orders) ?> سفارش</span>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox" style="font-size: 64px; color: #cbd5e1;"></i>
                <h4 class="mt-3 text-muted">هنوز سفارشی ثبت نکرده‌اید</h4>
                <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-primary mt-3">
                    <i class="bi bi-shop"></i> شروع خرید
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
                $statusInfo = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'class' => 'secondary'];
                $payInfo = $paymentLabels[$order['payment_status']] ?? ['label' => $order['payment_status'], 'class' => 'secondary'];
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-number">
                            <i class="bi bi-receipt"></i>
                            <?= htmlspecialchars($order['order_number']) ?>
                        </div>
                        <small class="text-muted">
                            <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-<?= $statusInfo['class'] ?>">
                            <?= htmlspecialchars($statusInfo['label']) ?>
                        </span>
                        <span class="badge bg-<?= $payInfo['class'] ?>">
                            <?= htmlspecialchars($payInfo['label']) ?>
                        </span>
                    </div>
                </div>
                <div class="order-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="order-meta">
                            <div>
                                <i class="bi bi-cash-coin"></i>
                                <strong>مبلغ کل:</strong>
                                <span class="order-total"><?= $shop->formatPrice((float) $order['total']) ?></span>
                            </div>
                            <?php if (!empty($order['payment_method'])): ?>
                                <div>
                                    <i class="bi bi-credit-card"></i>
                                    <strong>روش پرداخت:</strong>
                                    <?= htmlspecialchars($order['payment_method']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="order-invoice.php?id=<?= (int) $order['id'] ?>&mode=preview"
                               target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-eye"></i> پیش‌نمایش
                            </a>
                            <a href="order-invoice.php?id=<?= (int) $order['id'] ?>&mode=download"
                               class="btn btn-sm btn-success">
                                <i class="bi bi-file-earmark-pdf"></i> دانلود فاکتور
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

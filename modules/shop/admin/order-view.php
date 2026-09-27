<?php
/**
 * PartoCMS - Shop Admin - Order View
 * مشاهده جزئیات سفارش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/OrderManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$orderManager = new OrderManager($pdo);

$id = (int) ($_GET['id'] ?? 0);
$order = $id > 0 ? $orderManager->getById($id) : null;

if (!$order) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'سفارش یافت نشد'];
    header('Location: orders.php');
    exit;
}

$items = $orderManager->getItems($id);

// به‌روزرسانی وضعیت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $newStatus = $_POST['status'] ?? $order['status'];
        $notes = trim($_POST['notes'] ?? '');
        $orderManager->updateStatus($id, $newStatus, $notes);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'وضعیت سفارش به‌روزرسانی شد'];
        header('Location: order-view.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'مشاهده سفارش ' . $order['order_number'];
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';

$statusMap = [
    'pending' => ['warning', 'در انتظار'],
    'processing' => ['info', 'در حال پردازش'],
    'shipped' => ['primary', 'ارسال شده'],
    'completed' => ['success', 'تکمیل شده'],
    'cancelled' => ['danger', 'لغو شده'],
    'refunded' => ['secondary', 'بازگشت داده شده'],
];
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
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-receipt"></i>
            سفارش <code><?= htmlspecialchars($order['order_number']) ?></code>
        </h1>
        <div>
            <a href="order-invoice.php?id=<?= (int) $order['id'] ?>&mode=preview" target="_blank" class="btn btn-info">
                <i class="bi bi-eye"></i> پیش‌نمایش فاکتور
            </a>
            <a href="order-invoice.php?id=<?= (int) $order['id'] ?>&mode=download" class="btn btn-success">
                <i class="bi bi-file-earmark-pdf"></i> دانلود فاکتور PDF
            </a>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> چاپ
            </button>
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="row g-4">
        <!-- اطلاعات سفارش -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle"></i> اطلاعات سفارش</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>وضعیت فعلی:</strong>
                            <?php $st = $statusMap[$order['status']] ?? ['secondary', $order['status']]; ?>
                            <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>وضعیت پرداخت:</strong>
                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'secondary' ?>">
                                <?= $order['payment_status'] === 'paid' ? 'پرداخت شده' : 'پرداخت نشده' ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>روش پرداخت:</strong>
                            <?= htmlspecialchars($order['payment_method'] ?? 'نامشخص') ?>
                        </div>
                        <div class="col-md-6">
                            <strong>تاریخ سفارش:</strong>
                            <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-box"></i> محصولات سفارش</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>قیمت واحد</th>
                                    <th>تعداد</th>
                                    <th>جمع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= $shop->formatPrice((float) $item['product_price']) ?></td>
                                        <td><?= (int) $item['quantity'] ?></td>
                                        <td><?= $shop->formatPrice((float) $item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end">جمع کالاها:</td>
                                    <td><?= $shop->formatPrice((float) $order['subtotal']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end">مالیات:</td>
                                    <td><?= $shop->formatPrice((float) $order['tax']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end">ارسال:</td>
                                    <td><?= $order['shipping'] == 0 ? 'رایگان' : $shop->formatPrice((float) $order['shipping']) ?></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end"><strong>جمع کل:</strong></td>
                                    <td><strong><?= $shop->formatPrice((float) $order['total']) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['shipping_address'])): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-geo-alt"></i> آدرس تحویل</div>
                    <div class="card-body">
                        <pre class="mb-0" style="font-family: Tahoma; white-space: pre-wrap;"><?= htmlspecialchars($order['shipping_address']) ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($order['customer_notes'])): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-chat"></i> یادداشت مشتری</div>
                    <div class="card-body"><?= nl2br(htmlspecialchars($order['customer_notes'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- تغییر وضعیت -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header"><i class="bi bi-gear"></i> تغییر وضعیت</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                        <input type="hidden" name="update_status" value="1">

                        <div class="mb-3">
                            <label class="form-label">وضعیت جدید</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statusMap as $key => $val): ?>
                                    <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                        <?= $val[1] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">یادداشت</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="دلیل تغییر وضعیت..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> به‌روزرسانی
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

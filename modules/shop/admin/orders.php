<?php
/**
 * PartoCMS - Shop Admin - Orders Management
 * مدیریت سفارشات فروشگاه
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

// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $pdo->prepare("DELETE FROM shop_orders WHERE id = ?")->execute([(int) $_POST['delete_id']]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'سفارش حذف شد'];
    }
    header('Location: orders.php');
    exit;
}

// فیلترها
$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['payment_status'])) $filters['payment_status'] = $_GET['payment_status'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$orders = $orderManager->getAll($filters, $page, $perPage);
$total = $orderManager->countAll($filters);
$totalPages = ceil($total / $perPage);
$stats = $orderManager->getStats();

$pageTitle = 'مدیریت سفارشات';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';

$statusMap = [
    'pending' => ['warning', 'در انتظار'],
    'processing' => ['info', 'در حال پردازش'],
    'shipped' => ['primary', 'ارسال شده'],
    'completed' => ['success', 'تکمیل شده'],
    'cancelled' => ['danger', 'لغو شده'],
    'refunded' => ['secondary', 'بازگشت داده شده'],
];

$paymentMap = [
    'unpaid' => ['secondary', 'پرداخت نشده'],
    'paid' => ['success', 'پرداخت شده'],
    'failed' => ['danger', 'ناموفق'],
    'refunded' => ['warning', 'بازگشت داده شده'],
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
        .stat-card { background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-bag-check"></i> مدیریت سفارشات</h1>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- آمار -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card text-center">
                <i class="bi bi-bag fs-2 text-primary"></i>
                <div class="fs-4 fw-bold"><?= $stats['total'] ?></div>
                <div class="text-muted small">کل سفارشات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <i class="bi bi-hourglass fs-2 text-warning"></i>
                <div class="fs-4 fw-bold"><?= $stats['pending'] ?></div>
                <div class="text-muted small">در انتظار</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <i class="bi bi-check-circle fs-2 text-success"></i>
                <div class="fs-4 fw-bold"><?= $stats['completed'] ?></div>
                <div class="text-muted small">تکمیل شده</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <i class="bi bi-cash-coin fs-2 text-success"></i>
                <div class="fs-6 fw-bold"><?= $shop->formatPrice($stats['revenue']) ?></div>
                <div class="text-muted small">درآمد</div>
            </div>
        </div>
    </div>

    <!-- فیلترها -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="جستجو (شماره سفارش، آدرس)..."
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        <?php foreach ($statusMap as $key => $val): ?>
                            <option value="<?= $key ?>" <?= (($_GET['status'] ?? '') === $key) ? 'selected' : '' ?>>
                                <?= $val[1] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="payment_status" class="form-select">
                        <option value="">همه پرداخت‌ها</option>
                        <?php foreach ($paymentMap as $key => $val): ?>
                            <option value="<?= $key ?>" <?= (($_GET['payment_status'] ?? '') === $key) ? 'selected' : '' ?>>
                                <?= $val[1] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> فیلتر
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- سفارشات -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-x fs-1"></i>
                    <h5 class="mt-3">هیچ سفارشی یافت نشد</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>شماره سفارش</th>
                                <th>مبلغ</th>
                                <th>وضعیت</th>
                                <th>پرداخت</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $st = $statusMap[$order['status']] ?? ['secondary', $order['status']];
                                $pay = $paymentMap[$order['payment_status']] ?? ['secondary', $order['payment_status']];
                                ?>
                                <tr>
                                    <td><?= (int) $order['id'] ?></td>
                                    <td><code><?= htmlspecialchars($order['order_number']) ?></code></td>
                                    <td><?= $shop->formatPrice((float) $order['total']) ?></td>
                                    <td><span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span></td>
                                    <td><span class="badge bg-<?= $pay[0] ?>"><?= $pay[1] ?></span></td>
                                    <td><?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <a href="order-view.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                            <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $order['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * PartoCMS - Shop Admin - Reports
 * گزارش‌های فروش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/ReportManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$reportManager = new ReportManager($pdo);

// تاریخ‌ها
$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');
$days = (int) ($_GET['days'] ?? 30);

$overview = $reportManager->getOverview($fromDate, $toDate);
$dailySales = $reportManager->getDailySales($days);
$topProducts = $reportManager->getTopProducts(10);
$ordersByStatus = $reportManager->getOrdersByStatus();
$paymentStats = $reportManager->getPaymentStats();
$topCustomers = $reportManager->getTopCustomers(10);
$salesByCategory = $reportManager->getSalesByCategory();

$pageTitle = 'گزارش‌های فروش';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';

$statusLabels = [
    'pending' => 'در انتظار',
    'processing' => 'در حال پردازش',
    'shipped' => 'ارسال شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده',
    'refunded' => 'بازگشت داده شده',
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
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-graph-up"></i> گزارش‌های فروش</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <!-- فیلتر تاریخ -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">از تاریخ</label>
                    <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($fromDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">تا تاریخ</label>
                    <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($toDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">بازه نمودار (روز)</label>
                    <select name="days" class="form-select">
                        <option value="7" <?= $days == 7 ? 'selected' : '' ?>>۷ روز</option>
                        <option value="30" <?= $days == 30 ? 'selected' : '' ?>>۳۰ روز</option>
                        <option value="60" <?= $days == 60 ? 'selected' : '' ?>>۶۰ روز</option>
                        <option value="90" <?= $days == 90 ? 'selected' : '' ?>>۹۰ روز</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> اعمال فیلتر
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- آمار کلی -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-primary text-white"><i class="bi bi-bag"></i></div>
                <div>
                    <div class="text-muted small">کل سفارشات</div>
                    <div class="fs-4 fw-bold"><?= number_format($overview['total_orders']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-success text-white"><i class="bi bi-cash-coin"></i></div>
                <div>
                    <div class="text-muted small">درآمد کل</div>
                    <div class="fs-5 fw-bold text-success"><?= $shop->formatPrice($overview['total_revenue']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-info text-white"><i class="bi bi-cart-check"></i></div>
                <div>
                    <div class="text-muted small">میانگین سبد</div>
                    <div class="fs-5 fw-bold"><?= $shop->formatPrice($overview['avg_order_value']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-warning text-white"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">مشتریان یکتا</div>
                    <div class="fs-4 fw-bold"><?= number_format($overview['unique_customers']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار فروش روزانه -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-graph-up-arrow"></i> فروش روزانه (<?= $days ?> روز اخیر)</div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- پرفروش‌ترین محصولات -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-trophy"></i> پرفروش‌ترین محصولات</div>
                <div class="card-body">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-muted text-center py-4">داده‌ای موجود نیست</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>محصول</th>
                                        <th>تعداد</th>
                                        <th>درآمد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $i => $p): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($p['product_name']) ?></td>
                                            <td><span class="badge bg-primary"><?= (int) $p['total_sold'] ?></span></td>
                                            <td><?= $shop->formatPrice((float) $p['total_revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آمار وضعیت سفارشات -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-pie-chart"></i> وضعیت سفارشات</div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- آمار روش پرداخت -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-credit-card"></i> آمار روش‌های پرداخت</div>
                <div class="card-body">
                    <?php if (empty($paymentStats)): ?>
                        <p class="text-muted text-center py-4">داده‌ای موجود نیست</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>روش پرداخت</th>
                                        <th>تعداد</th>
                                        <th>درآمد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentStats as $ps): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ps['payment_method']) ?></td>
                                            <td><?= (int) $ps['count'] ?></td>
                                            <td><?= $shop->formatPrice((float) $ps['revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- مشتریان برتر -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-star"></i> مشتریان برتر</div>
                <div class="card-body">
                    <?php if (empty($topCustomers)): ?>
                        <p class="text-muted text-center py-4">داده‌ای موجود نیست</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>مشتری</th>
                                        <th>سفارشات</th>
                                        <th>مجموع خرید</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $i => $c): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($c['username'] ?? 'مهمان') ?></td>
                                            <td><?= (int) $c['total_orders'] ?></td>
                                            <td><?= $shop->formatPrice((float) $c['total_spent']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════════
// نمودار فروش روزانه
// ═══════════════════════════════════════════════════════════
const dailyData = <?= json_encode($dailySales) ?>;

const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date),
        datasets: [{
            label: 'درآمد (تومان)',
            data: dailyData.map(d => parseFloat(d.revenue)),
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6, 182, 212, 0.1)',
            tension: 0.3,
            fill: true,
        }, {
            label: 'تعداد سفارش',
            data: dailyData.map(d => parseInt(d.orders)),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
            yAxisID: 'y1',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'درآمد' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'تعداد سفارش' },
                grid: { drawOnChartArea: false }
            }
        }
    }
});

// ═══════════════════════════════════════════════════════════
// نمودار وضعیت سفارشات
// ═══════════════════════════════════════════════════════════
const statusData = <?= json_encode($ordersByStatus) ?>;
const statusLabels = <?= json_encode($statusLabels) ?>;
const statusColors = {
    pending: '#f59e0b',
    processing: '#06b6d4',
    shipped: '#3b82f6',
    completed: '#10b981',
    cancelled: '#ef4444',
    refunded: '#94a3b8',
};

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(s => statusLabels[s.status] || s.status),
        datasets: [{
            data: statusData.map(s => parseInt(s.count)),
            backgroundColor: statusData.map(s => statusColors[s.status] || '#94a3b8'),
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

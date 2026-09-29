<?php
/**
 * PartoCMS - Ads Admin - Reports
 * گزارش‌های تحلیلی تبلیغات
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/AdManager.php';
require_once __DIR__ . '/../includes/AdTracker.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$adm = new AdManager($pdo);
$tracker = new AdTracker($pdo);

$days = (int) ($_GET['days'] ?? 30);

// آمار کلی
$stats = $adm->getStats();
$ctr = $adm->getCtr();

// روند روزانه
$dailySql = "
    SELECT
        d.day,
        COALESCE(i.cnt, 0) AS impressions,
        COALESCE(c.cnt, 0) AS clicks
    FROM (
        SELECT DATE(created_at) AS day FROM ad_impressions WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        UNION
        SELECT DATE(created_at) AS day FROM ad_clicks WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
    ) d
    LEFT JOIN (SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM ad_impressions GROUP BY DATE(created_at)) i ON i.day = d.day
    LEFT JOIN (SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM ad_clicks GROUP BY DATE(created_at)) c ON c.day = d.day
    ORDER BY d.day ASC
";
$dailyData = $pdo->query($dailySql)->fetchAll(PDO::FETCH_ASSOC);

// TOP 10 تبلیغات
$topAdsSql = "
    SELECT
        a.id, a.title,
        (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = a.id) AS impressions,
        (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = a.id) AS clicks
    FROM ads a
    ORDER BY impressions DESC
    LIMIT 10
";
$topAds = $pdo->query($topAdsSql)->fetchAll(PDO::FETCH_ASSOC);

// عملکرد به تفکیک موقعیت
$byPositionSql = "
    SELECT
        p.title AS position_title,
        p.slug AS position_slug,
        COUNT(DISTINCT a.id) AS ad_count,
        COALESCE(SUM(CASE WHEN i.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS impressions,
        COALESCE((SELECT COUNT(*) FROM ad_clicks c INNER JOIN ads a2 ON a2.id = c.ad_id WHERE a2.position_id = p.id), 0) AS clicks
    FROM ad_positions p
    LEFT JOIN ads a ON a.position_id = p.id
    LEFT JOIN ad_impressions i ON i.ad_id = a.id
    GROUP BY p.id
    ORDER BY impressions DESC
";
$byPosition = $pdo->query($byPositionSql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'گزارش‌های تبلیغات';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);
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
        .chart-container { position: relative; height: 320px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-graph-up"></i> گزارش‌های تبلیغات</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <!-- فیلتر روزها -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-3">
                    <select name="days" class="form-select">
                        <option value="7"  <?= $days == 7  ? 'selected' : '' ?>>۷ روز اخیر</option>
                        <option value="30" <?= $days == 30 ? 'selected' : '' ?>>۳۰ روز اخیر</option>
                        <option value="90" <?= $days == 90 ? 'selected' : '' ?>>۹۰ روز اخیر</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> اعمال
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- آمار -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">نمایش کل</div>
                <div class="fs-4 fw-bold"><?= number_format($stats['total_impressions']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">کلیک کل</div>
                <div class="fs-4 fw-bold"><?= number_format($stats['total_clicks']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">CTR</div>
                <div class="fs-4 fw-bold text-success"><?= number_format($ctr, 2) ?>٪</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">تبلیغات فعال</div>
                <div class="fs-4 fw-bold"><?= number_format($stats['active_ads']) ?></div>
            </div>
        </div>
    </div>

    <!-- نمودار روزانه -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-graph-up-arrow"></i> روند روزانه (<?= $days ?> روز اخیر)
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- TOP 10 -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-trophy"></i> ۱۰ تبلیغ برتر</div>
                <div class="card-body p-0">
                    <?php if (empty($topAds)): ?>
                        <p class="text-muted text-center py-4">داده‌ای موجود نیست</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>عنوان</th>
                                        <th>نمایش</th>
                                        <th>کلیک</th>
                                        <th>CTR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topAds as $i => $ad): ?>
                                        <?php $adCtr = $ad['impressions'] > 0 ? round(($ad['clicks'] / $ad['impressions']) * 100, 2) : 0; ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($ad['title']) ?></td>
                                            <td><?= number_format($ad['impressions']) ?></td>
                                            <td><?= number_format($ad['clicks']) ?></td>
                                            <td><span class="badge bg-info"><?= $adCtr ?>٪</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- به تفکیک موقعیت -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-layout-text-window"></i> عملکرد موقعیت‌ها</div>
                <div class="card-body p-0">
                    <?php if (empty($byPosition)): ?>
                        <p class="text-muted text-center py-4">داده‌ای موجود نیست</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>موقعیت</th>
                                        <th>تعداد</th>
                                        <th>نمایش</th>
                                        <th>کلیک</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($byPosition as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['position_title']) ?></td>
                                            <td><?= (int) $p['ad_count'] ?></td>
                                            <td><?= number_format($p['impressions']) ?></td>
                                            <td><?= number_format($p['clicks']) ?></td>
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
const dailyData = <?= json_encode($dailyData) ?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.day),
        datasets: [{
            label: 'نمایش',
            data: dailyData.map(d => parseInt(d.impressions)),
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6,182,212,.1)',
            tension: 0.3,
            fill: true,
        }, {
            label: 'کلیک',
            data: dailyData.map(d => parseInt(d.clicks)),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,.1)',
            tension: 0.3,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

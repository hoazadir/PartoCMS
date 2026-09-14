<?php
/**
 * PartoCMS - Graphical Security Dashboard (Chart.js)
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

// ==================== DATA COLLECTION ====================

// ۱. توزیع انواع لاگ (Pie Chart)
$typeDistribution = [];
try {
    $rows = $pdo->query("
        SELECT type, COUNT(*) as cnt
        FROM security_logs
        GROUP BY type
        ORDER BY cnt DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $typeDistribution[$r['type']] = (int)$r['cnt'];
    }
} catch (Throwable $e) {}

// ۲. روند ۳۰ روز اخیر (Line Chart)
$trend30Days = [];
try {
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trend30Days[$date] = 0;
    }

    $rows = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as cnt
        FROM security_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if (isset($trend30Days[$r['day']])) {
            $trend30Days[$r['day']] = (int)$r['cnt'];
        }
    }
} catch (Throwable $e) {}

// ۳. تلاش‌های لاگین ۷ روز اخیر (Bar Chart)
$loginTrend = [];
try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $loginTrend[$date] = ['success' => 0, 'failed' => 0];
    }

    $rows = $pdo->query("
        SELECT DATE(attempted_at) as day, success, COUNT(*) as cnt
        FROM login_attempts
        WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(attempted_at), success
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if (isset($loginTrend[$r['day']])) {
            if ($r['success']) {
                $loginTrend[$r['day']]['success'] = (int)$r['cnt'];
            } else {
                $loginTrend[$r['day']]['failed'] = (int)$r['cnt'];
            }
        }
    }
} catch (Throwable $e) {}

// ۴. آمار کلی
$stats = [
    'total_logs'       => 0,
    'malware_blocked'  => 0,
    'login_attempts'   => 0,
    'active_blocks'    => 0,
    'files_monitored'  => 0,
    'scans_today'      => 0,
    'unique_ips'       => 0,
    '2fa_users'        => 0,
];
try {
    $stats['total_logs']      = (int) $pdo->query("SELECT COUNT(*) FROM security_logs")->fetchColumn();
    $stats['malware_blocked'] = (int) $pdo->query("SELECT COUNT(*) FROM security_logs WHERE type='malware'")->fetchColumn();
    $stats['login_attempts']  = (int) $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
    $stats['active_blocks']   = (int) $pdo->query("SELECT COUNT(*) FROM login_blocks WHERE blocked_until > NOW()")->fetchColumn();
    $stats['files_monitored'] = (int) $pdo->query("SELECT COUNT(*) FROM file_hashes")->fetchColumn();
    $stats['scans_today']     = (int) $pdo->query("SELECT COUNT(*) FROM security_logs WHERE type='scan' AND DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['unique_ips']      = (int) $pdo->query("SELECT COUNT(DISTINCT ip) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $stats['2fa_users']       = (int) $pdo->query("SELECT COUNT(*) FROM user_2fa WHERE enabled = 1")->fetchColumn();
} catch (Throwable $e) {}

// ۵. آماده‌سازی داده برای Chart.js
$pieLabels = [];
$pieData = [];
$pieColors = [
    'scan'        => '#3b82f6',
    'malware'     => '#ef4444',
    'login_fail'  => '#f59e0b',
    'integrity'   => '#ec4899',
    'access'      => '#8b5cf6',
];
$otherColor = '#64748b';

foreach ($typeDistribution as $type => $count) {
    $pieLabels[] = $type;
    $pieData[] = $count;
}

$lineLabels = array_map(function($d) { return date('m/d', strtotime($d)); }, array_keys($trend30Days));
$lineData   = array_values($trend30Days);

$barLabels       = array_map(function($d) { return date('D', strtotime($d)); }, array_keys($loginTrend));
$barDataSuccess  = array_column(array_values($loginTrend), 'success');
$barDataFailed   = array_column(array_values($loginTrend), 'failed');

$pieColorPalette = [];
foreach ($pieLabels as $label) {
    $pieColorPalette[] = $pieColors[$label] ?? $otherColor;
}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نمودارهای امنیتی — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#1e40af,#3730a3);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

/* KPI Cards */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px}
.kpi-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:transform .2s;border-right:5px solid}
.kpi-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,.1)}
.kpi-card .icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;margin-bottom:12px}
.kpi-card .value{font-size:32px;font-weight:bold;color:#1e293b;line-height:1}
.kpi-card .label{font-size:12px;color:#64748b;margin-top:5px}
.kpi-card .delta{font-size:11px;margin-top:6px;color:#10b981}
.kpi-card.c-blue{border-color:#3b82f6} .kpi-card.c-blue .icon{background:linear-gradient(135deg,#3b82f6,#1e40af)}
.kpi-card.c-red{border-color:#ef4444} .kpi-card.c-red .icon{background:linear-gradient(135deg,#ef4444,#991b1b)}
.kpi-card.c-green{border-color:#10b981} .kpi-card.c-green .icon{background:linear-gradient(135deg,#10b981,#059669)}
.kpi-card.c-purple{border-color:#8b5cf6} .kpi-card.c-purple .icon{background:linear-gradient(135deg,#8b5cf6,#5b21b6)}
.kpi-card.c-orange{border-color:#f59e0b} .kpi-card.c-orange .icon{background:linear-gradient(135deg,#f59e0b,#d97706)}
.kpi-card.c-pink{border-color:#ec4899} .kpi-card.c-pink .icon{background:linear-gradient(135deg,#ec4899,#9d174d)}

/* Chart Cards */
.chart-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.chart-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}
.chart-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.chart-card .header .badge-info{background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:bold}
.chart-card .body{padding:20px;position:relative}
.chart-container{position:relative;height:300px}
.chart-container-lg{position:relative;height:350px}

.btn-action{padding:10px 18px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-size:13px;text-decoration:none;transition:.2s}
.btn-action:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
.btn-blue{background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff}
.btn-green{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>📊 نمودارهای امنیتی</h1>
        <p>نمایش گرافیکی داده‌های امنیتی PartoCMS</p>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card c-blue">
            <div class="icon"><i class="bi bi-list-check"></i></div>
            <div class="value"><?= number_format($stats['total_logs']) ?></div>
            <div class="label">کل رویدادها</div>
        </div>
        <div class="kpi-card c-red">
            <div class="icon"><i class="bi bi-shield-x"></i></div>
            <div class="value"><?= number_format($stats['malware_blocked']) ?></div>
            <div class="label">بدافزار مسدود</div>
        </div>
        <div class="kpi-card c-green">
            <div class="icon"><i class="bi bi-file-earmark-code"></i></div>
            <div class="value"><?= number_format($stats['files_monitored']) ?></div>
            <div class="label">فایل تحت نظر</div>
        </div>
        <div class="kpi-card c-purple">
            <div class="icon"><i class="bi bi-radar"></i></div>
            <div class="value"><?= number_format($stats['scans_today']) ?></div>
            <div class="label">اسکن امروز</div>
        </div>
        <div class="kpi-card c-orange">
            <div class="icon"><i class="bi bi-person-x"></i></div>
            <div class="value"><?= number_format($stats['login_attempts']) ?></div>
            <div class="label">تلاش‌های لاگین</div>
        </div>
        <div class="kpi-card c-pink">
            <div class="icon"><i class="bi bi-lock"></i></div>
            <div class="value"><?= number_format($stats['2fa_users']) ?></div>
            <div class="label">کاربر با 2FA</div>
        </div>
    </div>

    <!-- Line Chart: روند 30 روز -->
    <div class="chart-card">
        <div class="header">
            <h5><i class="bi bi-graph-up"></i> روند رویدادها (۳۰ روز اخیر)</h5>
            <span class="badge-info"><?= array_sum($lineData) ?> رویداد</span>
        </div>
        <div class="body">
            <div class="chart-container-lg">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Two columns: Pie + Bar -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="chart-card">
                <div class="header">
                    <h5><i class="bi bi-pie-chart"></i> توزیع انواع رویدادها</h5>
                </div>
                <div class="body">
                    <div class="chart-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <div class="header">
                    <h5><i class="bi bi-bar-chart"></i> تلاش‌های لاگین (۷ روز اخیر)</h5>
                </div>
                <div class="body">
                    <div class="chart-container">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="chart-card">
        <div class="header"><h5>🔄 بروزرسانی و بازگشت</h5></div>
        <div class="body">
            <a href="security_graphs.php" class="btn-action btn-green">
                <i class="bi bi-arrow-clockwise"></i> بروزرسانی داده‌ها
            </a>
            <a href="security_dashboard.php" class="btn-action btn-blue">
                <i class="bi bi-arrow-right"></i> بازگشت به داشبورد
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ==================== Chart Defaults ====================
Chart.defaults.font.family = 'Tahoma, Arial, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#64748b';

// ==================== ۱. Line Chart: 30 Days Trend ====================
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendGradient = trendCtx.createLinearGradient(0, 0, 0, 350);
trendGradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
trendGradient.addColorStop(1, 'rgba(59, 130, 246, 0.02)');

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($lineLabels) ?>,
        datasets: [{
            label: 'تعداد رویدادها',
            data: <?= json_encode($lineData) ?>,
            borderColor: '#3b82f6',
            backgroundColor: trendGradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 7,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleColor: '#fff',
                bodyColor: '#e2e8f0',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        return 'رویدادها: ' + context.parsed.y;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { precision: 0 }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// ==================== ۲. Pie Chart: Type Distribution ====================
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($pieLabels) ?>,
        datasets: [{
            data: <?= json_encode($pieData) ?>,
            backgroundColor: <?= json_encode($pieColorPalette) ?>,
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: { size: 12 }
                }
            },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                cornerRadius: 8,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const pct = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                    }
                }
            }
        }
    }
});

// ==================== ۳. Bar Chart: Login Attempts ====================
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($barLabels) ?>,
        datasets: [
            {
                label: 'موفق',
                data: <?= json_encode($barDataSuccess) ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10b981',
                borderWidth: 2,
                borderRadius: 6,
                barThickness: 20
            },
            {
                label: 'ناموفق',
                data: <?= json_encode($barDataFailed) ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: '#ef4444',
                borderWidth: 2,
                borderRadius: 6,
                barThickness: 20
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { usePointStyle: true, pointStyle: 'circle', padding: 15 }
            },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                cornerRadius: 8
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { precision: 0 }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');

// آمار کلی
$summary = [
    'views_today' => (int) $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = CURDATE()")->fetchColumn(),
    'views_yesterday' => (int) $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn(),
    'views_week' => (int) $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'views_month' => (int) $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'total_views' => (int) $pdo->query("SELECT COUNT(*) FROM views")->fetchColumn(),
    'total_posts' => (int) $pdo->query("SELECT COUNT(*) FROM content_items")->fetchColumn(),
    'total_users' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
];

// درصد رشد
$growth = $summary['views_yesterday'] > 0
    ? round((($summary['views_today'] - $summary['views_yesterday']) / $summary['views_yesterday']) * 100, 1)
    : 0;

// پربازدیدترین مقالات
$topPosts = $pdo->query("
    SELECT ci.id, ci.title, ci.views,
        (SELECT COUNT(*) FROM comments WHERE content_id = ci.id AND status='approved') as comments_count
    FROM content_items ci
    WHERE ci.status = 'published'
    ORDER BY ci.views DESC
    LIMIT 10
")->fetchAll();

// آمار نقش‌ها
$roleStats = $pdo->query("
    SELECT r.name as role_name, r.slug as role_slug, COUNT(u.id) as count
    FROM roles r
    LEFT JOIN users u ON u.role_id = r.id
    GROUP BY r.id
    ORDER BY count DESC
")->fetchAll();

// آمار دسته‌بندی
$categoryStats = $pdo->query("
    SELECT c.name, c.icon, c.color, COUNT(ci.id) as count
    FROM categories c
    LEFT JOIN content_items ci ON ci.category_id = c.id AND ci.status='published'
    WHERE c.is_active = 1
    GROUP BY c.id
    HAVING count > 0
    ORDER BY count DESC
")->fetchAll();

// آخرین بازدیدها
$recentViews = $pdo->query("
    SELECT v.*, ci.title, u.username
    FROM views v
    LEFT JOIN content_items ci ON ci.id = v.content_id
    LEFT JOIN users u ON u.id = v.user_id
    ORDER BY v.viewed_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش‌ها | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; color: #1e293b; display: flex; align-items: center; justify-content: space-between; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 15px;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .icon {
            width: 55px; height: 55px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: #fff; flex-shrink: 0;
        }
        .stat-card .info .num { font-size: 26px; font-weight: bold; color: #1e293b; line-height: 1; }
        .stat-card .info .label { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        .stat-card .info .growth { font-size: 11px; margin-top: 5px; }
        .growth-up { color: #10b981; }
        .growth-down { color: #ef4444; }

        .chart-container { position: relative; height: 300px; }

        .progress-role {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-bottom: 1px solid #f0f0f0;
        }
        .progress-role:last-child { border-bottom: none; }
        .progress-role .role-info { flex: 1; }
        .progress-role .role-name { font-weight: bold; color: #1e293b; font-size: 13px; }
        .progress-role .role-bar {
            height: 6px; background: #e2e8f0; border-radius: 3px; margin-top: 5px;
            overflow: hidden;
        }
        .progress-role .role-bar-inner {
            height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6); border-radius: 3px;
            transition: width 0.5s;
        }
        .progress-role .role-count {
            font-weight: bold; color: #1e293b; font-size: 18px;
            background: #f8fafc; padding: 5px 12px; border-radius: 8px;
        }

        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0; color:#1e293b;">📊 گزارش‌ها و آمار</h4>
            <small class="text-muted">نمای کلی عملکرد سایت</small>
        </div>
        <div class="d-flex gap-2">
            <select id="rangeSelect" class="form-select form-select-sm" style="width:auto;" onchange="loadCharts()">
                <option value="7">۷ روز اخیر</option>
                <option value="30" selected>۳۰ روز اخیر</option>
                <option value="90">۳ ماه اخیر</option>
                <option value="365">یک سال</option>
            </select>
            <button class="btn btn-success btn-sm" onclick="exportReport()">
                <i class="bi bi-download"></i> خروجی CSV
            </button>
        </div>
    </div>

    <!-- کارت‌های آماری -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="bi bi-eye"></i>
                </div>
                <div class="info">
                    <div class="num"><?= number_format($summary['views_today']) ?></div>
                    <div class="label">بازدید امروز</div>
                    <div class="growth <?= $growth >= 0 ? 'growth-up' : 'growth-down' ?>">
                        <?= $growth >= 0 ? '↑' : '↓' ?> <?= abs($growth) ?>% از دیروز
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <div class="info">
                    <div class="num"><?= number_format($summary['views_week']) ?></div>
                    <div class="label">بازدید این هفته</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="bi bi-calendar-month"></i>
                </div>
                <div class="info">
                    <div class="num"><?= number_format($summary['views_month']) ?></div>
                    <div class="label">بازدید این ماه</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="info">
                    <div class="num"><?= number_format($summary['total_views']) ?></div>
                    <div class="label">کل بازدیدها</div>
                </div>
            </div>
        </div>
    </div>

    <!-- نمودار بازدید -->
    <div class="card">
        <div class="card-header">
            <span><i class="bi bi-graph-up-arrow" style="color: #3b82f6;"></i> نمودار بازدید</span>
            <small class="text-muted" id="chartRangeLabel">۳۰ روز اخیر</small>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="viewsChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- پربازدیدترین مقالات -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <span><i class="bi bi-trophy" style="color: #f59e0b;"></i> پربازدیدترین مقالات</span>
                </div>
                <div class="card-body">
                    <?php if (empty($topPosts)): ?>
                        <p class="text-center text-muted py-4">هنوز بازدیدی ثبت نشده</p>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <tbody>
                            <?php foreach ($topPosts as $i => $p): ?>
                                <tr>
                                    <td style="width:35px;">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;font-size:11px;font-weight:bold;color:#fff;background:<?= $i < 3 ? ['#f59e0b','#94a3b8','#b45309'][$i] : '#cbd5e1' ?>;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../post.php?id=<?= $p['id'] ?>" target="_blank" style="color:#1e293b;text-decoration:none;font-size:13px;">
                                            <?= htmlspecialchars(mb_substr($p['title'], 0, 45)) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" style="background:#dbeafe;color:#1e40af;font-size:11px;">
                                            👁 <?= number_format($p['views']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" style="background:#dcfce7;color:#166534;font-size:11px;">
                                            💬 <?= $p['comments_count'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آمار نقش‌ها -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <span><i class="bi bi-people" style="color: #8b5cf6;"></i> کاربران بر اساس نقش</span>
                    <span class="badge bg-primary"><?= $summary['total_users'] ?> کاربر</span>
                </div>
                <div class="card-body">
                    <?php
                    $maxRoleCount = 0;
                    foreach ($roleStats as $r) $maxRoleCount = max($maxRoleCount, $r['count']);
                    ?>
                    <?php foreach ($roleStats as $r): ?>
                        <div class="progress-role">
                            <div class="role-info">
                                <div class="role-name"><?= htmlspecialchars($r['role_name']) ?></div>
                                <div class="role-bar">
                                    <div class="role-bar-inner" style="width:<?= $maxRoleCount > 0 ? ($r['count'] / $maxRoleCount * 100) : 0 ?>%;"></div>
                                </div>
                            </div>
                            <div class="role-count"><?= $r['count'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <!-- آمار دسته‌بندی -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <span><i class="bi bi-tags" style="color: #10b981;"></i> محتوا بر اساس دسته</span>
                </div>
                <div class="card-body">
                    <?php if (empty($categoryStats)): ?>
                        <p class="text-center text-muted py-4">دسته‌ای یافت نشد</p>
                    <?php else: ?>
                        <div class="chart-container" style="height: 250px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آخرین بازدیدها -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <span><i class="bi bi-clock-history" style="color: #f5576c;"></i> آخرین بازدیدها</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentViews)): ?>
                        <p class="text-center text-muted py-4">هنوز بازدیدی ثبت نشده</p>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0">
                            <tbody>
                            <?php foreach ($recentViews as $v): ?>
                                <tr>
                                    <td style="font-size:12px;">
                                        <strong><?= htmlspecialchars(mb_substr($v['title'] ?? 'نامشخص', 0, 30)) ?></strong>
                                    </td>
                                    <td style="font-size:11px;color:#64748b;">
                                        <?= htmlspecialchars($v['username'] ?? 'مهمان') ?>
                                    </td>
                                    <td style="font-size:11px;color:#94a3b8;">
                                        <?= date('m/d H:i', strtotime($v['viewed_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// ============================================================
// نمودار بازدید
// ============================================================
let viewsChart = null;

async function loadCharts() {
    const range = document.getElementById('rangeSelect').value;
    const rangeLabel = document.getElementById('rangeSelect').selectedOptions[0].text;
    document.getElementById('chartRangeLabel').textContent = rangeLabel;

    try {
        const res = await fetch('../api/reports.php?action=views_chart&range=' + range);
        const result = await res.json();

        if (result.status === 'success') {
            const data = result.data;

            if (viewsChart) viewsChart.destroy();

            const ctx = document.getElementById('viewsChart').getContext('2d');
            viewsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'بازدید',
                        data: data.map(d => d.count),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: { family: 'Tahoma', size: 12 },
                            bodyFont: { family: 'Tahoma', size: 13 },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'بازدید: ' + context.parsed.y.toLocaleString('fa-IR');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { family: 'Tahoma' },
                                callback: function(value) { return value.toLocaleString('fa-IR'); }
                            },
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            ticks: { font: { family: 'Tahoma' } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    } catch (e) {
        console.error('خطا:', e);
    }
}

// ============================================================
// نمودار دسته‌بندی
// ============================================================
<?php if (!empty($categoryStats)): ?>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($c) => $c['icon'] . ' ' . $c['name'], $categoryStats), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode(array_column($categoryStats, 'count')) ?>,
                backgroundColor: <?= json_encode(array_column($categoryStats, 'color')) ?>,
                borderWidth: 3,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Tahoma', size: 12 },
                        padding: 15,
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { family: 'Tahoma' },
                    bodyFont: { family: 'Tahoma' },
                    padding: 12,
                    cornerRadius: 8,
                }
            }
        }
    });
});
<?php endif; ?>

// ============================================================
// خروجی CSV
// ============================================================
async function exportReport() {
    const range = document.getElementById('rangeSelect').value;
    const res = await fetch('../api/reports.php?action=views_chart&range=' + range);
    const result = await res.json();

    if (result.status !== 'success') {
        alert('خطا در دریافت داده');
        return;
    }

    let csv = '\uFEFF'; // BOM برای UTF-8
    csv += '"تاریخ","بازدید"\n';

    result.data.forEach(row => {
        csv += `"${row.date}","${row.count}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'report-' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

// بارگذاری اولیه
document.addEventListener('DOMContentLoaded', loadCharts);
</script>

</body>
</html>

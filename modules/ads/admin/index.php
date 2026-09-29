<?php
/**
 * PartoCMS - Ads Admin - Dashboard
 * داشبورد ماژول تبلیغات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
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

$stats = $adm->getStats();
$ctr = $adm->getCtr();
$recentAds = $adm->getAll([], 1, 5);
$positions = $adm->getPositions();

$pageTitle = 'داشبورد تبلیغات';
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
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-megaphone"></i> داشبورد تبلیغات</h1>
        <a href="ad-edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> تبلیغ جدید
        </a>
    </div>

    <!-- آمار کلی -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-primary text-white"><i class="bi bi-image"></i></div>
                <div>
                    <div class="text-muted small">کل تبلیغات</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['total_ads']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-success text-white"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="text-muted small">تبلیغات فعال</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['active_ads']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-info text-white"><i class="bi bi-eye"></i></div>
                <div>
                    <div class="text-muted small">نمایش‌ها (impression)</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['total_impressions']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card d-flex align-items-center gap-3">
                <div class="icon bg-warning text-white"><i class="bi bi-hand-index"></i></div>
                <div>
                    <div class="text-muted small">کلیک‌ها</div>
                    <div class="fs-4 fw-bold"><?= number_format($stats['total_clicks']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">CTR</div>
                <div class="fs-4 fw-bold text-success"><?= number_format($ctr, 2) ?>٪</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">موقعیت‌ها</div>
                <div class="fs-4 fw-bold"><?= number_format($stats['total_positions']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">کمپین‌ها</div>
                <div class="fs-4 fw-bold"><?= number_format($stats['total_campaigns']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small">امروز (نمایش/کلیک)</div>
                <div class="fs-5 fw-bold">
                    <?= number_format($stats['today_impressions']) ?> /
                    <?= number_format($stats['today_clicks']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- تبلیغات اخیر -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history"></i> تبلیغات اخیر</span>
                    <a href="ads.php" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentAds)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox" style="font-size:32px;"></i>
                            <p class="mt-2">هنوز تبلیغی ثبت نشده</p>
                            <a href="ad-edit.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus"></i> افزودن تبلیغ
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>عنوان</th>
                                        <th>نوع</th>
                                        <th>موقعیت</th>
                                        <th>وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAds as $ad): ?>
                                        <tr>
                                            <td>
                                                <a href="ad-edit.php?id=<?= (int) $ad['id'] ?>">
                                                    <?= htmlspecialchars($ad['title']) ?>
                                                </a>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ad['type']) ?></span></td>
                                            <td><?= htmlspecialchars($ad['position_title'] ?? '—') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $ad['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                    <?= htmlspecialchars($ad['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- موقعیت‌ها -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-layout-text-window"></i> موقعیت‌ها
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($positions as $pos): ?>
                            <div class="list-group-item d-flex justify-content-between">
                                <span><?= htmlspecialchars($pos['title']) ?></span>
                                <small class="text-muted"><?= (int)$pos['width'] ?>×<?= (int)$pos['height'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

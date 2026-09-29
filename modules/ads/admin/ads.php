<?php
/**
 * PartoCMS - Ads Admin - Ads List
 * لیست تبلیغات
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

// حذف
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $adm->delete((int) $_GET['id']);
    header('Location: ads.php?msg=deleted');
    exit;
}

// تغییر وضعیت
if (($_GET['action'] ?? '') === 'status' && !empty($_GET['id']) && !empty($_GET['status'])) {
    $adm->updateStatus((int) $_GET['id'], $_GET['status']);
    header('Location: ads.php?msg=updated');
    exit;
}

// فیلترها
$filters = [
    'status'      => $_GET['status'] ?? '',
    'type'        => $_GET['type'] ?? '',
    'position_id' => $_GET['position_id'] ?? '',
    'search'      => trim($_GET['q'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$ads = $adm->getAll($filters, $page, $perPage);
$total = $adm->countAll($filters);
$totalPages = max(1, (int) ceil($total / $perPage));
$positions = $adm->getPositions();

$pageTitle = 'مدیریت تبلیغات';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);

$msg = $_GET['msg'] ?? '';
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
        .ad-thumb { width: 60px; height: 40px; object-fit: cover; border-radius: 6px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-image"></i> مدیریت تبلیغات</h1>
        <a href="ad-edit.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> تبلیغ جدید
        </a>
    </div>

    <?php if ($msg === 'deleted'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> تبلیغ حذف شد</div>
    <?php elseif ($msg === 'updated'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> وضعیت به‌روزرسانی شد</div>
    <?php endif; ?>

    <!-- فیلترها -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control"
                           placeholder="جستجو..." value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
                        <option value="paused" <?= $filters['status'] === 'paused' ? 'selected' : '' ?>>متوقف</option>
                        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>منقضی</option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">همه انواع</option>
                        <option value="image" <?= $filters['type'] === 'image' ? 'selected' : '' ?>>تصویر</option>
                        <option value="html" <?= $filters['type'] === 'html' ? 'selected' : '' ?>>HTML</option>
                        <option value="adsense" <?= $filters['type'] === 'adsense' ? 'selected' : '' ?>>AdSense</option>
                        <option value="text" <?= $filters['type'] === 'text' ? 'selected' : '' ?>>متن</option>
                        <option value="slider" <?= $filters['type'] === 'slider' ? 'selected' : '' ?>>اسلایدر</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="position_id" class="form-select">
                        <option value="">همه موقعیت‌ها</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= (int) $pos['id'] ?>" <?= (string) $filters['position_id'] === (string) $pos['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pos['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> فیلتر
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- لیست -->
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>لیست تبلیغات</span>
            <span class="badge bg-primary"><?= number_format($total) ?> تبلیغ</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($ads)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size:48px;"></i>
                    <p class="mt-3">هیچ تبلیغی یافت نشد</p>
                    <a href="ad-edit.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> افزودن اولین تبلیغ
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>تصویر</th>
                                <th>عنوان</th>
                                <th>نوع</th>
                                <th>موقعیت</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ads as $ad): ?>
                                <tr>
                                    <td><?= (int) $ad['id'] ?></td>
                                    <td>
                                        <?php if (!empty($ad['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($ad['image_url']) ?>" class="ad-thumb" alt="">
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($ad['type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="ad-edit.php?id=<?= (int) $ad['id'] ?>">
                                            <strong><?= htmlspecialchars($ad['title']) ?></strong>
                                        </a>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($ad['type']) ?></small></td>
                                    <td><?= htmlspecialchars($ad['position_title'] ?? '—') ?></td>
                                    <td>
                                        <?php
                                        $statusColor = [
                                            'active'  => 'success',
                                            'paused'  => 'warning',
                                            'expired' => 'secondary',
                                            'draft'   => 'info',
                                        ][$ad['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= htmlspecialchars($ad['status']) ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $ad['created_at'] ? date('Y/m/d', strtotime($ad['created_at'])) : '—' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="ad-edit.php?id=<?= (int) $ad['id'] ?>" class="btn btn-outline-primary" title="ویرایش">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($ad['status'] === 'active'): ?>
                                                <a href="?action=status&id=<?= (int) $ad['id'] ?>&status=paused" class="btn btn-outline-warning" title="توقف">
                                                    <i class="bi bi-pause"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=status&id=<?= (int) $ad['id'] ?>&status=active" class="btn btn-outline-success" title="فعال‌سازی">
                                                    <i class="bi bi-play"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?action=delete&id=<?= (int) $ad['id'] ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('حذف این تبلیغ و همه آمار آن؟')"
                                               title="حذف">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- صفحه‌بندی -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($filters)) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

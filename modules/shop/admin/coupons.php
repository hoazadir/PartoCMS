<?php
/**
 * PartoCMS - Shop Admin - Coupons Management
 * مدیریت کوپن‌های تخفیف
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/CouponManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$couponManager = new CouponManager($pdo);

// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $couponManager->delete((int) $_POST['delete_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'کوپن حذف شد'];
    }
    header('Location: coupons.php');
    exit;
}

$coupons = $couponManager->getAll();
$pageTitle = 'مدیریت کوپن‌ها';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
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
        <h1><i class="bi bi-ticket-perforated"></i> مدیریت کوپن‌ها</h1>
        <a href="coupon-edit.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> کوپن جدید
        </a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($coupons)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-ticket fs-1"></i>
                    <h5 class="mt-3">هنوز کوپنی وجود ندارد</h5>
                    <a href="coupon-edit.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus"></i> افزودن اولین کوپن
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>مقدار</th>
                                <th>استفاده</th>
                                <th>وضعیت</th>
                                <th>انقضا</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $c): ?>
                                <tr>
                                    <td><?= (int) $c['id'] ?></td>
                                    <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                                    <td>
                                        <?= $c['type'] === 'percentage' ? 'درصدی' : 'مبلغ ثابت' ?>
                                    </td>
                                    <td>
                                        <?= $c['type'] === 'percentage' 
                                            ? $c['value'] . '%' 
                                            : $shop->formatPrice((float) $c['value']) ?>
                                    </td>
                                    <td>
                                        <?= (int) $c['used_count'] ?>
                                        <?= $c['usage_limit'] ? '/ ' . (int) $c['usage_limit'] : '' ?>
                                    </td>
                                    <td>
                                        <?php if ($c['is_active']): ?>
                                            <span class="badge bg-success">فعال</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $c['expires_at'] ? date('Y/m/d', strtotime($c['expires_at'])) : '—' ?>
                                    </td>
                                    <td>
                                        <a href="coupon-edit.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                            <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

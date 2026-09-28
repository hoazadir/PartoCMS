<?php
/**
 * PartoCMS - Shop Admin - Customers
 * مدیریت مشتریان فروشگاه
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

// فیلترها
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// ساخت کوئری
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR mobile LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

// شمارش
$stmt = $pdo->prepare("SELECT COUNT(*) FROM shop_customers WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

// دریافت
$stmt = $pdo->prepare("
    SELECT * FROM shop_customers
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'مشتریان فروشگاه';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$baseAdmin = ADMIN_URL;
$baseSite = SITE_URL;
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
        .customer-row:hover { background: #f8fafc; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-people"></i> مشتریان فروشگاه</h1>
        <span class="badge bg-primary fs-6"><?= number_format($total) ?> مشتری</span>
    </div>

    <!-- جستجو -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="q" class="form-control"
                           placeholder="جستجو بر اساس نام، ایمیل یا موبایل..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> جستجو
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- لیست مشتریان -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($customers)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size: 48px;"></i>
                    <p class="mt-3">هیچ مشتری‌ای یافت نشد</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>نام</th>
                                <th>ایمیل</th>
                                <th>موبایل</th>
                                <th>شهر</th>
                                <th>سفارشات</th>
                                <th>مجموع خرید</th>
                                <th>تاریخ عضویت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr class="customer-row">
                                    <td><?= (int) $c['id'] ?></td>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?: '—' ?>
                                        </strong>
                                    </td>
                                    <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($c['mobile'] ?? $c['phone'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($c['city'] ?? '—') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= (int) $c['total_orders'] ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            <?= $shop->formatPrice((float) $c['total_spent']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $c['created_at'] ? date('Y/m/d', strtotime($c['created_at'])) : '—' ?>
                                        </small>
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
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * PartoCMS - Shop Admin - Products Management
 * مدیریت محصولات فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/ProductManager.php';
require_once __DIR__ . '/../includes/CategoryManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$productManager = new ProductManager($pdo);
$catManager = new CategoryManager($pdo);

// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $productManager->delete((int) $_POST['delete_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'محصول با موفقیت حذف شد'];
    }
    header('Location: products.php');
    exit;
}

// فیلترها
$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['category'])) $filters['category_id'] = (int) $_GET['category'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$products = $productManager->getAll($filters, $page, $perPage);
$total = $productManager->countAll($filters);
$totalPages = ceil($total / $perPage);
$categories = $catManager->getAll();

$pageTitle = 'مدیریت محصولات';
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
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-box"></i> مدیریت محصولات</h1>
        <a href="product-edit.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> محصول جدید
        </a>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- فیلترها -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="جستجو..."
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">همه دسته‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= (($_GET['category'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="published" <?= (($_GET['status'] ?? '') === 'published') ? 'selected' : '' ?>>منتشرشده</option>
                        <option value="draft" <?= (($_GET['status'] ?? '') === 'draft') ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="archived" <?= (($_GET['status'] ?? '') === 'archived') ? 'selected' : '' ?>>بایگانی</option>
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

    <!-- محصولات -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box fs-1"></i>
                    <h5 class="mt-3">هنوز محصولی وجود ندارد</h5>
                    <a href="product-edit.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus"></i> افزودن اولین محصول
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>تصویر</th>
                                <th>نام</th>
                                <th>دسته</th>
                                <th>قیمت</th>
                                <th>موجودی</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><?= (int) $p['id'] ?></td>
                                    <td>
                                        <?php if (!empty($p['image'])): ?>
                                            <img src="<?= htmlspecialchars($p['image']) ?>" class="product-img" alt="">
                                        <?php else: ?>
                                            <div class="product-img bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td>
                                        <?php if (!empty($p['category_name'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($p['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $shop->formatPrice((float) $p['price']) ?></td>
                                    <td>
                                        <?php if ($p['quantity'] > 0): ?>
                                            <span class="badge bg-success"><?= (int) $p['quantity'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">ناموجود</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'published' => ['success', 'منتشرشده'],
                                            'draft' => ['secondary', 'پیش‌نویس'],
                                            'archived' => ['dark', 'بایگانی'],
                                        ];
                                        $st = $statusMap[$p['status']] ?? ['secondary', $p['status']];
                                        ?>
                                        <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
                                    </td>
                                    <td>
                                        <a href="<?= $shop->getUrl('product.php?id=' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="product-edit.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                            <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- صفحه‌بندی -->
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

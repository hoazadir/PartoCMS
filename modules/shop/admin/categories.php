<?php
/**
 * PartoCMS - Shop Admin - Categories Management
 * مدیریت دسته‌بندی محصولات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/CategoryManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$catManager = new CategoryManager($pdo);

// مدیریت درخواست حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $catManager->delete((int) $_POST['delete_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'دسته‌بندی با موفقیت حذف شد'];
    }
    header('Location: categories.php');
    exit;
}

$categories = $catManager->getTree();
$pageTitle = 'مدیریت دسته‌بندی';
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
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-tags"></i> مدیریت دسته‌بندی</h1>
        <a href="category-edit.php" class="btn btn-primary">
            <i class="bi bi-plus"></i> دسته جدید
        </a>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Categories -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-tags fs-1"></i>
                    <h5 class="mt-3">هنوز دسته‌بندی‌ای وجود ندارد</h5>
                    <a href="category-edit.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus"></i> افزودن اولین دسته
                    </a>
                </div>
            <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام</th>
                            <th>Slug</th>
                            <th>تعداد محصول</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <?= renderCategoryRow($cat, $catManager, $shop) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function renderCategoryRow(array $cat, CategoryManager $manager, ShopManager $shop, int $level = 0): string
{
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
    $productCount = $manager->getProductCount((int) $cat['id']);
    $statusBadge = $cat['is_active']
        ? '<span class="badge bg-success">فعال</span>'
        : '<span class="badge bg-secondary">غیرفعال</span>';

    $html = '<tr>';
    $html .= '<td>' . (int) $cat['id'] . '</td>';
    $html .= '<td>' . $indent . '<i class="bi bi-tag text-primary"></i> ' . htmlspecialchars($cat['name']) . '</td>';
    $html .= '<td><code>' . htmlspecialchars($cat['slug']) . '</code></td>';
    $html .= '<td><span class="badge bg-info">' . $productCount . '</span></td>';
    $html .= '<td>' . $statusBadge . '</td>';
    $html .= '<td>';
    $html .= '<a href="category-edit.php?id=' . (int) $cat['id'] . '" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a> ';
    $html .= '<form method="post" style="display:inline" onsubmit="return confirm(\'آیا مطمئن هستید؟\')">';
    $html .= '<input type="hidden" name="csrf_token" value="' . getSecurity()->csrfToken() . '">';
    $html .= '<input type="hidden" name="delete_id" value="' . (int) $cat['id'] . '">';
    $html .= '<button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>';
    $html .= '</form>';
    $html .= '</td>';
    $html .= '</tr>';

    if (!empty($cat['children'])) {
        foreach ($cat['children'] as $child) {
            $html .= renderCategoryRow($child, $manager, $shop, $level + 1);
        }
    }

    return $html;
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

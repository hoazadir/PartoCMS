<?php
/**
 * PartoCMS - Shop Admin - Category Edit
 * ویرایش/افزودن دسته‌بندی
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

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$category = $isEdit ? $catManager->getById($id) : null;

if ($isEdit && !$category) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'دسته‌بندی یافت نشد'];
    header('Location: categories.php');
    exit;
}

$errors = [];
$success = false;

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $icon = trim($_POST['icon'] ?? '');
        $color = trim($_POST['color'] ?? '#3498db');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) $errors[] = 'نام دسته الزامی است';
        if (empty($slug)) $slug = $shop->generateSlug($name, 'shop_categories');

        if (empty($errors)) {
            $data = [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'parent_id' => $parentId,
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ];

            if ($isEdit) {
                $catManager->update($id, $data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'دسته‌بندی با موفقیت به‌روزرسانی شد'];
            } else {
                $catManager->create($data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'دسته‌بندی با موفقیت ایجاد شد'];
            }

            header('Location: categories.php');
            exit;
        }
    }
}

$allCategories = $catManager->getAll();
$pageTitle = $isEdit ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی';
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
        <h1>
            <i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle' ?>"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h1>
        <a href="categories.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نام دسته <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="<?= htmlspecialchars($_POST['name'] ?? $category['name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control"
                               value="<?= htmlspecialchars($_POST['slug'] ?? $category['slug'] ?? '') ?>"
                               placeholder="خالی بگذارید تا خودکار تولید شود">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">دسته والد</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- بدون والد --</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <?php if ($isEdit && $cat['id'] == $id) continue; ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= (($_POST['parent_id'] ?? $category['parent_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">آیکون (Bootstrap Icons)</label>
                        <input type="text" name="icon" class="form-control"
                               value="<?= htmlspecialchars($_POST['icon'] ?? $category['icon'] ?? '') ?>"
                               placeholder="مثال: bi-tag">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">رنگ</label>
                        <input type="color" name="color" class="form-control form-control-color"
                               value="<?= htmlspecialchars($_POST['color'] ?? $category['color'] ?? '#3498db') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">توضیحات</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? $category['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">ترتیب</label>
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= (int) ($_POST['sort_order'] ?? $category['sort_order'] ?? 0) ?>">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                   <?= (($_POST['is_active'] ?? $category['is_active'] ?? 1) ? 'checked' : '') ?>>
                            <label class="form-check-label" for="is_active">فعال</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> ذخیره
                        </button>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> انصراف
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

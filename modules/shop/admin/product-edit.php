<?php
/**
 * PartoCMS - Shop Admin - Product Edit
 * ویرایش/افزودن محصول
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

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$product = $isEdit ? $productManager->getById($id) : null;

if ($isEdit && !$product) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'محصول یافت نشد'];
    header('Location: products.php');
    exit;
}

$errors = [];

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $shortDescription = trim($_POST['short_description'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $comparePrice = !empty($_POST['compare_price']) ? (float) $_POST['compare_price'] : null;
        $costPrice = !empty($_POST['cost_price']) ? (float) $_POST['cost_price'] : null;
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $image = trim($_POST['image'] ?? '');
        $weight = !empty($_POST['weight']) ? (float) $_POST['weight'] : null;
        $dimensions = trim($_POST['dimensions'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $downloadable = isset($_POST['downloadable']) ? 1 : 0;
        $virtual = isset($_POST['virtual']) ? 1 : 0;
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if (empty($name)) $errors[] = 'نام محصول الزامی است';
        if (empty($slug)) $slug = $shop->generateSlug($name, 'shop_products');

        if (empty($errors)) {
            $data = [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'short_description' => $shortDescription,
                'sku' => $sku,
                'price' => $price,
                'compare_price' => $comparePrice,
                'cost_price' => $costPrice,
                'quantity' => $quantity,
                'category_id' => $categoryId,
                'image' => $image,
                'weight' => $weight,
                'dimensions' => $dimensions,
                'status' => $status,
                'featured' => $featured,
                'downloadable' => $downloadable,
                'virtual' => $virtual,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keywords' => $metaKeywords,
                'sort_order' => $sortOrder,
            ];

            if ($isEdit) {
                $productManager->update($id, $data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'محصول با موفقیت به‌روزرسانی شد'];
            } else {
                $productManager->create($data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'محصول با موفقیت ایجاد شد'];
            }

            header('Location: products.php');
            exit;
        }
    }
}

$categories = $catManager->getAll();
$pageTitle = $isEdit ? 'ویرایش محصول' : 'افزودن محصول';
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
        <a href="products.php" class="btn btn-secondary">
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

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">

        <div class="row g-3">
            <!-- ستون اصلی -->
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-info-circle"></i> اطلاعات اصلی</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">نام محصول <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= htmlspecialchars($_POST['name'] ?? $product['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Slug</label>
                                <input type="text" name="slug" class="form-control"
                                       value="<?= htmlspecialchars($_POST['slug'] ?? $product['slug'] ?? '') ?>"
                                       placeholder="خالی بگذارید تا خودکار تولید شود">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU (کد محصول)</label>
                                <input type="text" name="sku" class="form-control"
                                       value="<?= htmlspecialchars($_POST['sku'] ?? $product['sku'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیح کوتاه</label>
                                <textarea name="short_description" class="form-control" rows="2"><?= htmlspecialchars($_POST['short_description'] ?? $product['short_description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات کامل</label>
                                <textarea name="description" class="form-control" rows="6"><?= htmlspecialchars($_POST['description'] ?? $product['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-currency-dollar"></i> قیمت و موجودی</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">قیمت <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['price'] ?? $product['price'] ?? '0') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">قیمت مقایسه</label>
                                <input type="number" name="compare_price" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['compare_price'] ?? $product['compare_price'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">قیمت خرید</label>
                                <input type="number" name="cost_price" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['cost_price'] ?? $product['cost_price'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">موجودی</label>
                                <input type="number" name="quantity" class="form-control"
                                       value="<?= (int) ($_POST['quantity'] ?? $product['quantity'] ?? 0) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">وزن (گرم)</label>
                                <input type="number" name="weight" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['weight'] ?? $product['weight'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ابعاد</label>
                                <input type="text" name="dimensions" class="form-control"
                                       value="<?= htmlspecialchars($_POST['dimensions'] ?? $product['dimensions'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-search"></i> SEO</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" class="form-control"
                                       value="<?= htmlspecialchars($_POST['meta_title'] ?? $product['meta_title'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="2"><?= htmlspecialchars($_POST['meta_description'] ?? $product['meta_description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Meta Keywords</label>
                                <input type="text" name="meta_keywords" class="form-control"
                                       value="<?= htmlspecialchars($_POST['meta_keywords'] ?? $product['meta_keywords'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ستون کناری -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-gear"></i> وضعیت</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-select">
                                <option value="draft" <?= (($_POST['status'] ?? $product['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>پیش‌نویس</option>
                                <option value="published" <?= (($_POST['status'] ?? $product['status'] ?? '') === 'published') ? 'selected' : '' ?>>منتشرشده</option>
                                <option value="archived" <?= (($_POST['status'] ?? $product['status'] ?? '') === 'archived') ? 'selected' : '' ?>>بایگانی</option>
                            </select>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="featured" class="form-check-input" id="featured"
                                   <?= (($_POST['featured'] ?? $product['featured'] ?? 0) ? 'checked' : '') ?>>
                            <label class="form-check-label" for="featured">محصول ویژه</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="downloadable" class="form-check-input" id="downloadable"
                                   <?= (($_POST['downloadable'] ?? $product['downloadable'] ?? 0) ? 'checked' : '') ?>>
                            <label class="form-check-label" for="downloadable">قابل دانلود</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="virtual" class="form-check-input" id="virtual"
                                   <?= (($_POST['virtual'] ?? $product['virtual'] ?? 0) ? 'checked' : '') ?>>
                            <label class="form-check-label" for="virtual">محصول مجازی</label>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-tags"></i> دسته‌بندی</div>
                    <div class="card-body">
                        <select name="category_id" class="form-select">
                            <option value="">-- بدون دسته --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= (($_POST['category_id'] ?? $product['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-image"></i> تصویر</div>
                    <div class="card-body">
                        <label class="form-label">URL تصویر</label>
                        <input type="text" name="image" class="form-control" id="imageUrl"
                               value="<?= htmlspecialchars($_POST['image'] ?? $product['image'] ?? '') ?>">
                        <?php if (!empty($product['image']) || !empty($_POST['image'])): ?>
                            <img src="<?= htmlspecialchars($_POST['image'] ?? $product['image'] ?? '') ?>"
                                 class="img-fluid mt-2 rounded" style="max-height: 200px;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-sort-numeric-up"></i> ترتیب</div>
                    <div class="card-body">
                        <input type="number" name="sort_order" class="form-control"
                               value="<?= (int) ($_POST['sort_order'] ?? $product['sort_order'] ?? 0) ?>">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> ذخیره
                    </button>
                    <a href="products.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> انصراف
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

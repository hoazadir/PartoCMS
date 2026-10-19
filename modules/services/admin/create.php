<?php
/**
 * PartoCMS — Services Admin
 * ایجاد خدمت جدید
 */
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$service = $GLOBALS['services_service'];

$form = [
    'title' => '', 'slug' => '', 'description' => '', 'content' => '',
    'icon' => '', 'image_url' => '', 'price' => '', 'price_unit' => '',
    'features_str' => '', 'category' => '', 'status' => 'published',
    'is_featured' => false, 'sort_order' => 0, 'language' => 'fa-IR',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!services_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $form['title']       = trim((string) ($_POST['title'] ?? ''));
        $form['slug']        = trim((string) ($_POST['slug'] ?? ''));
        $form['description'] = trim((string) ($_POST['description'] ?? ''));
        $form['content']     = trim((string) ($_POST['content'] ?? ''));
        $form['icon']        = trim((string) ($_POST['icon'] ?? ''));
        $form['image_url']   = trim((string) ($_POST['image_url'] ?? ''));
        $form['price']       = trim((string) ($_POST['price'] ?? ''));
        $form['price_unit']  = trim((string) ($_POST['price_unit'] ?? ''));
        $form['features_str']= trim((string) ($_POST['features_str'] ?? ''));
        $form['category']    = trim((string) ($_POST['category'] ?? ''));
        $form['status']      = (string) ($_POST['status'] ?? 'published');
        $form['is_featured'] = !empty($_POST['is_featured']);
        $form['sort_order']  = (int) ($_POST['sort_order'] ?? 0);
        $form['language']    = (string) ($_POST['language'] ?? 'fa-IR');

        if ($form['title'] === '') $errors[] = 'عنوان الزامی است';
        if ($form['price'] !== '' && !is_numeric($form['price'])) $errors[] = 'قیمت باید عدد باشد';
        if ($form['image_url'] !== '' && !filter_var($form['image_url'], FILTER_VALIDATE_URL)) $errors[] = 'URL تصویر نامعتبر است';

        if (empty($errors)) {
            try {
                $features = array_filter(array_map('trim', explode("\n", $form['features_str'])));
                $id = $service->createByAdmin([
                    'title' => $form['title'],
                    'slug' => $form['slug'] ?: null,
                    'description' => $form['description'] ?: null,
                    'content' => $form['content'] ?: null,
                    'icon' => $form['icon'] ?: null,
                    'image_url' => $form['image_url'] ?: null,
                    'price' => $form['price'] !== '' ? (float) $form['price'] : null,
                    'price_unit' => $form['price_unit'] ?: null,
                    'features' => array_values($features),
                    'category' => $form['category'] ?: null,
                    'status' => $form['status'],
                    'is_featured' => $form['is_featured'],
                    'sort_order' => $form['sort_order'],
                    'language' => $form['language'],
                ], $form['status'] === 'published');
                services_redirect(services_admin_url('index.php'), 'success', "خدمت ایجاد شد (ID: $id)");
            } catch (\Throwable $e) {
                $errors[] = 'خطا: ' . $e->getMessage();
            }
        }
    }
}
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خدمت جدید | <?= t_esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h2 { margin: 0; font-size: 20px; color: #1e293b; }
        .card { background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field label { display: block; font-size: 13px; color: #334155; font-weight: 600; margin-bottom: 6px; }
        .field label .req { color: #dc2626; }
        .field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color .15s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .field textarea { min-height: 120px; resize: vertical; }
        .field small { display: block; margin-top: 4px; color: #64748b; font-size: 11px; }
        .checkbox-field { display: flex; align-items: center; gap: 8px; }
        .checkbox-field input { width: 18px; height: 18px; cursor: pointer; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .alert-error ul { margin: 0; padding-right: 20px; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; } .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h2>➕ خدمت جدید</h2>
        <a href="<?= t_esc(services_admin_url('index.php')) ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><strong>خطا:</strong><ul><?php foreach ($errors as $e): ?><li><?= t_esc($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="">
            <?= services_csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label>عنوان <span class="req">*</span></label>
                    <input type="text" name="title" value="<?= t_esc($form['title']) ?>" required maxlength="150">
                </div>
                <div class="field">
                    <label>دسته‌بندی</label>
                    <input type="text" name="category" value="<?= t_esc($form['category']) ?>" maxlength="100">
                </div>
                <div class="field full">
                    <label>توضیح کوتاه</label>
                    <textarea name="description" maxlength="500"><?= t_esc($form['description']) ?></textarea>
                </div>
                <div class="field full">
                    <label>محتوای کامل</label>
                    <textarea name="content"><?= t_esc($form['content']) ?></textarea>
                </div>
                <div class="field">
                    <label>آیکون (emoji)</label>
                    <input type="text" name="icon" value="<?= t_esc($form['icon']) ?>" placeholder="مثلاً 🌐">
                </div>
                <div class="field">
                    <label>URL تصویر</label>
                    <input type="url" name="image_url" value="<?= t_esc($form['image_url']) ?>">
                </div>
                <div class="field">
                    <label>قیمت</label>
                    <input type="number" step="0.01" name="price" value="<?= t_esc($form['price']) ?>">
                </div>
                <div class="field">
                    <label>واحد قیمت</label>
                    <input type="text" name="price_unit" value="<?= t_esc($form['price_unit']) ?>" placeholder="تومان / ساعت">
                </div>
                <div class="field full">
                    <label>ویژگی‌ها (هر خط یک ویژگی)</label>
                    <textarea name="features_str" placeholder="مثلاً:&#10;پشتیبانی ۲۴/۷&#10;ضمانت بازگشت وجه"><?= t_esc($form['features_str']) ?></textarea>
                </div>
                <div class="field">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>✅ منتشرشده</option>
                        <option value="hidden" <?= $form['status'] === 'hidden' ? 'selected' : '' ?>>🙈 مخفی</option>
                    </select>
                </div>
                <div class="field">
                    <label>ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>" min="0">
                </div>
                <div class="field">
                    <label>زبان</label>
                    <select name="language">
                        <option value="fa-IR" <?= $form['language'] === 'fa-IR' ? 'selected' : '' ?>>فارسی</option>
                        <option value="en-US" <?= $form['language'] === 'en-US' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                <div class="field full">
                    <div class="checkbox-field">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>>
                        <label for="is_featured">⭐ نمایش در بخش ویژه</label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> ایجاد</button>
                <a href="<?= t_esc(services_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

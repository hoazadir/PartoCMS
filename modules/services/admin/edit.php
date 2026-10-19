<?php
/**
 * PartoCMS — Services Admin
 * ویرایش خدمت
 */
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$service = $GLOBALS['services_service'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    services_redirect(services_admin_url('index.php'), 'error', 'شناسه نامعتبر است');
}

try {
    $item = $service->getByIdOrFail($id);
} catch (\Throwable $e) {
    services_redirect(services_admin_url('index.php'), 'error', 'خدمت یافت نشد');
}

$form = [
    'title' => $item->getTitle(), 'slug' => $item->getSlug() ?? '',
    'description' => $item->getDescription() ?? '', 'content' => $item->getContent() ?? '',
    'icon' => $item->getIcon() ?? '', 'image_url' => $item->getImageUrl() ?? '',
    'price' => $item->getPrice() !== null ? (string) $item->getPrice() : '',
    'price_unit' => $item->getPriceUnit() ?? '',
    'features_str' => implode("\n", $item->getFeatures()),
    'category' => $item->getCategory() ?? '', 'status' => $item->getStatus(),
    'is_featured' => $item->isFeatured(), 'sort_order' => $item->getSortOrder(),
    'language' => $item->getLanguage(),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!services_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $form['title']       = trim((string) ($_POST['title'] ?? ''));
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

        if (empty($errors)) {
            try {
                $features = array_filter(array_map('trim', explode("\n", $form['features_str'])));
                $service->updateByAdmin($id, [
                    'title' => $form['title'],
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
                ]);
                services_redirect(services_admin_url('index.php'), 'success', 'خدمت به‌روزرسانی شد');
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
    <title>ویرایش خدمت | <?= t_esc($siteName) ?></title>
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
        .field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .field textarea { min-height: 120px; resize: vertical; }
        .checkbox-field { display: flex; align-items: center; gap: 8px; }
        .checkbox-field input { width: 18px; height: 18px; cursor: pointer; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; justify-content: space-between; flex-wrap: wrap; }
        .form-actions .left, .form-actions .right { display: flex; gap: 10px; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h2>✏️ ویرایش خدمت #<?= (int) $id ?></h2>
        <a href="<?= t_esc(services_admin_url('index.php')) ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><strong>خطا:</strong><ul><?php foreach ($errors as $e): ?><li><?= t_esc($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="" id="edit-form">
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
                    <textarea name="description" id="desc-editor" maxlength="500"><?= t_esc($form['description']) ?></textarea>
                </div>
                <div class="field full">
                    <label>محتوای کامل</label>
                    <textarea name="content" id="content-editor"><?= t_esc($form['content']) ?></textarea>
                </div>
                <div class="field" style="position: relative;">
                    <label>آیکون (emoji)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" name="icon" id="icon-input" value="<?= t_esc($form['icon']) ?>" placeholder="مثلاً 🌐" style="flex: 1;">
                        <button type="button" id="emoji-toggle-btn" style="padding: 0 15px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-size: 18px;">😀</button>
                    </div>
                    <div id="emoji-picker-wrapper" style="display: none; position: absolute; top: 100%; right: 0; z-index: 1000; margin-top: 5px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
                        <emoji-picker></emoji-picker>
                    </div>
                </div>
                <div class="field">
                    <label>تصویر (Image)</label>
                    <div id="service-image-box" style="border: 2px dashed #cbd5e1; padding: 20px; text-align: center; cursor: pointer; border-radius: 8px; background: #f8fafc; margin-bottom: 10px;">
                        <div id="service-image-placeholder" style="display: <?= $form['image_url'] ? 'none' : 'block' ?>; color: #64748b;">
                            📁 برای انتخاب تصویر کلیک کنید یا عکس را اینجا رها کنید
                        </div>
                        <img id="service-image-preview" src="<?= t_esc($form['image_url']) ?>" style="display: <?= $form['image_url'] ? 'block' : 'none' ?>; max-width: 100%; max-height: 200px; margin: 0 auto; border-radius: 6px;" />
                    </div>
                    <input type="hidden" name="image_url" id="service_image_url_input" value="<?= t_esc($form['image_url']) ?>">
                    <input type="file" id="service-image-file" accept="image/*" style="display: none;">
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
                    <textarea name="features_str"><?= t_esc($form['features_str']) ?></textarea>
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
                <div class="left">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> ذخیره تغییرات</button>
                    <a href="<?= t_esc(services_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
                </div>
                <div class="right">
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()"><i class="bi bi-trash"></i> حذف</button>
                </div>
            </div>
        </form>

        <form method="post" action="<?= t_esc(services_admin_url('index.php')) ?>" id="delete-form" style="display:none;">
            <?= services_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete() {
    if (confirm('آیا از حذف این خدمت مطمئن هستید؟')) {
        document.getElementById('delete-form').submit();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#desc-editor, #content-editor',
    height: 300,
    menubar: false,
    plugins: 'lists link image table code fullscreen',
    toolbar: 'undo redo | bold italic underline | bullist numlist | link image | alignleft aligncenter alignright | code fullscreen',
    directionality: 'rtl',
    branding: false,
    promotion: false,
    content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 14px; direction: rtl; }'
});
</script>


    <!-- Service Image Uploader JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const imgBox = document.getElementById('service-image-box');
        if (!imgBox) return;
        const fileInput = document.getElementById('service-image-file');
        const hiddenInput = document.getElementById('service_image_url_input');
        const preview = document.getElementById('service-image-preview');
        const placeholder = document.getElementById('service-image-placeholder');

        imgBox.addEventListener('click', () => fileInput.click());
        imgBox.addEventListener('dragover', (e) => { e.preventDefault(); imgBox.style.borderColor = '#6d28d9'; });
        imgBox.addEventListener('dragleave', () => { imgBox.style.borderColor = '#cbd5e1'; });
        imgBox.addEventListener('drop', (e) => {
            e.preventDefault();
            imgBox.style.borderColor = '#cbd5e1';
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                uploadServiceImage(fileInput.files[0]);
            }
        });

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) uploadServiceImage(this.files[0]);
        });

        async function uploadServiceImage(file) {
            placeholder.style.display = 'block';
            placeholder.innerHTML = '⏳ در حال آپلود...';
            preview.style.display = 'none';

            const fd = new FormData();
            fd.append('files[]', file);
            const csrfMeta = document.querySelector('input[name="_csrf"]');
            fd.append('_csrf', csrfMeta ? csrfMeta.value : '');

            try {
                const response = await fetch('../ajax/upload.php', { method: 'POST', body: fd });
                const data = await response.json();
                if (data.success && data.urls && data.urls.length > 0) {
                    const url = data.urls[0];
                    hiddenInput.value = url;
                    preview.src = url;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                } else {
                    alert('خطا در آپلود: ' + (data.message || 'پاسخ نامعتبر'));
                    placeholder.innerHTML = '📁 برای انتخاب تصویر کلیک کنید';
                }
            } catch (error) {
                console.error(error);
                alert('خطای شبکه در آپلود تصویر.');
                placeholder.innerHTML = '📁 برای انتخاب تصویر کلیک کنید';
            }
        }
    });
    </script>


    <!-- Emoji Picker -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('emoji-toggle-btn');
        const wrapper = document.getElementById('emoji-picker-wrapper');
        const input = document.getElementById('icon-input');
        const picker = document.querySelector('emoji-picker');

        if (toggleBtn && wrapper && input && picker) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                wrapper.style.display = wrapper.style.display === 'none' ? 'block' : 'none';
            });

            picker.addEventListener('emoji-click', event => {
                input.value = event.detail.unicode;
                wrapper.style.display = 'none';
            });

            // بستن پنل با کلیک بیرون از آن
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    wrapper.style.display = 'none';
                }
            });
        }
    });
    </script>

</body>
</html>

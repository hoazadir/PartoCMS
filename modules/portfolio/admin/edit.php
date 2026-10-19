<?php
/**
 * PartoCMS — Portfolio Admin
 * ویرایش نمونه‌کار
 */
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$service = $GLOBALS['portfolio_service'];
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    portfolio_redirect(portfolio_admin_url('index.php'), 'error', 'شناسه نامعتبر است');
}

try {
    $item = $service->getByIdOrFail($id);
} catch (\Throwable $e) {
    portfolio_redirect(portfolio_admin_url('index.php'), 'error', 'نمونه‌کار یافت نشد');
}

$form = [
    'title' => $item->getTitle(), 'slug' => $item->getSlug() ?? '',
    'client_name' => $item->getClientName() ?? '',
    'description' => $item->getDescription() ?? '',
    'content' => $item->getContent() ?? '',
    'thumbnail_url' => $item->getThumbnailUrl() ?? '',
    'gallery_str' => implode("\n", $item->getGallery()),
    'category' => $item->getCategory() ?? '',
    'tags_str' => implode(', ', $item->getTags()),
    'project_date' => $item->getProjectDate() ?? '',
    'project_url' => $item->getProjectUrl() ?? '',
    'status' => $item->getStatus(),
    'is_featured' => $item->isFeatured(),
    'sort_order' => $item->getSortOrder(),
    'language' => $item->getLanguage(),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!portfolio_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        foreach (['title','slug','client_name','description','content','thumbnail_url','gallery_str','category','tags_str','project_date','project_url','status','language'] as $f) {
            $form[$f] = trim((string) ($_POST[$f] ?? ''));
        }
        $form['is_featured'] = !empty($_POST['is_featured']);
        $form['sort_order'] = (int) ($_POST['sort_order'] ?? 0);

        if ($form['title'] === '') $errors[] = 'عنوان الزامی است';

        if (empty($errors)) {
            try {
                $gallery = array_values(array_filter(array_map('trim', explode("\n", $form['gallery_str']))));
                $tags = array_values(array_filter(array_map('trim', explode(',', $form['tags_str']))));
                $service->updateByAdmin($id, [
                    'title' => $form['title'],
                    'slug' => $form['slug'] ?: null,
                    'client_name' => $form['client_name'] ?: null,
                    'description' => $form['description'] ?: null,
                    'content' => $form['content'] ?: null,
                    'thumbnail_url' => $form['thumbnail_url'] ?: null,
                    'gallery' => $gallery,
                    'category' => $form['category'] ?: null,
                    'tags' => $tags,
                    'project_date' => $form['project_date'] ?: null,
                    'project_url' => $form['project_url'] ?: null,
                    'status' => $form['status'],
                    'is_featured' => $form['is_featured'],
                    'sort_order' => $form['sort_order'],
                    'language' => $form['language'],
                ]);
                portfolio_redirect(portfolio_admin_url('index.php'), 'success', 'نمونه‌کار به‌روزرسانی شد');
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
    <title>ویرایش نمونه‌کار | <?= t_esc($siteName) ?></title>
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
        .field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .field textarea { min-height: 100px; resize: vertical; }
        .field small { display: block; margin-top: 4px; color: #64748b; font-size: 11px; }
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
        .tox-tinymce { border-radius: 8px !important; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .form-grid { grid-template-columns: 1fr; } }
    
        /* ─── Gallery Manager ─── */
        .gallery-manager {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 15px;
            background: #f8fafc;
        }
        .gallery-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .gallery-preview:empty { display: none; }
        .gallery-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .gallery-remove {
            position: absolute;
            top: 4px;
            left: 4px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: none;
            background: rgba(220, 38, 38, 0.9);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-remove:hover { background: #b91c1c; }
        .gallery-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .btn-gallery-upload {
            padding: 10px 20px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-gallery-upload:hover { background: #4f46e5; }
        .btn-gallery-upload:disabled { opacity: 0.6; cursor: wait; }
        .gallery-hint { font-size: 12px; color: #64748b; }
        #gallery-textarea {
            min-height: 80px;
            margin-top: 10px;
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-family: monospace;
        }

    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h2>✏️ ویرایش نمونه‌کار #<?= (int) $id ?></h2>
        <a href="<?= t_esc(portfolio_admin_url('index.php')) ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><strong>خطا:</strong><ul><?php foreach ($errors as $e): ?><li><?= t_esc($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="" id="edit-form">
            <?= portfolio_csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label>عنوان <span style="color:#dc2626">*</span></label>
                    <input type="text" name="title" value="<?= t_esc($form['title']) ?>" required maxlength="200">
                </div>
                <div class="field">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= t_esc($form['slug']) ?>" maxlength="200">
                </div>
                <div class="field">
                    <label>نام مشتری</label>
                    <input type="text" name="client_name" value="<?= t_esc($form['client_name']) ?>" maxlength="150">
                </div>
                <div class="field">
                    <label>دسته‌بندی</label>
                    <input type="text" name="category" value="<?= t_esc($form['category']) ?>" maxlength="100">
                </div>
                <div class="field">
                    <label>URL تصویر اصلی</label>
                    <input type="url" name="thumbnail_url" value="<?= t_esc($form['thumbnail_url']) ?>" maxlength="255">
                </div>
                <div class="field">
                    <label>تاریخ پروژه</label>
                    <input type="date" name="project_date" value="<?= t_esc($form['project_date']) ?>">
                </div>
                <div class="field full">
                    <label>لینک پروژه</label>
                    <input type="url" name="project_url" value="<?= t_esc($form['project_url']) ?>" maxlength="255">
                </div>
                <div class="field full">
                    <label>توضیح کوتاه</label>
                    <textarea name="description" id="desc-editor"><?= t_esc($form['description']) ?></textarea>
                </div>
                <div class="field full">
                    <label>محتوای کامل</label>
                    <textarea name="content" id="content-editor"><?= t_esc($form['content']) ?></textarea>
                </div>
                                <div class="field full">
                    <label>گالری تصاویر</label>
                    <div class="gallery-manager">
                        <div class="gallery-preview" id="gallery-preview"></div>
                        <div class="gallery-actions">
                            <button type="button" class="btn-gallery-upload" id="btn-gallery-upload">
                                📁 انتخاب فایل از سیستم
                            </button>
                            <span class="gallery-hint">یا URLها را دستی وارد کنید (هر خط یک URL)</span>
                        </div>
                        <input type="file" id="gallery-file-input" accept="image/*" multiple style="display:none">
                        <textarea name="gallery_str" id="gallery-textarea" placeholder="https://example.com/img1.jpg&#10;https://example.com/img2.jpg"><?= t_esc($form['gallery_str']) ?></textarea>
                    </div>
                </div>
                <div class="field full">
                    <label>تگ‌ها (با کاما جدا کنید)</label>
                    <input type="text" name="tags_str" value="<?= t_esc($form['tags_str']) ?>">
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
                    <a href="<?= t_esc(portfolio_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
                </div>
                <div class="right">
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()"><i class="bi bi-trash"></i> حذف</button>
                </div>
            </div>
        </form>

        <form method="post" action="<?= t_esc(portfolio_admin_url('index.php')) ?>" id="delete-form" style="display:none;">
            <?= portfolio_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#desc-editor, #content-editor',
    height: 300,
    menubar: false,
    plugins: 'lists link image table code fullscreen',
    toolbar: 'undo redo | bold italic underline | bullist numlist | link image | alignleft aligncenter alignright | code fullscreen',
    directionality: 'rtl',
    language: 'fa',
    branding: false,
    promotion: false,
    content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 14px; direction: rtl; }'
});

function confirmDelete() {
    if (confirm('آیا از حذف این نمونه‌کار مطمئن هستید؟')) {
        document.getElementById('delete-form').submit();
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
// ─── TinyMCE ───
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

// ─── Gallery Manager ───
(function() {
    const textarea = document.getElementById('gallery-textarea');
    const preview = document.getElementById('gallery-preview');
    const fileInput = document.getElementById('gallery-file-input');
    const uploadBtn = document.getElementById('btn-gallery-upload');
    const csrfToken = '<?= portfolio_csrf_token() ?>';

    if (!textarea || !preview || !fileInput || !uploadBtn) return;

    function getUrls() {
        return textarea.value.split('\n').map(u => u.trim()).filter(u => u !== '');
    }

    function setUrls(urls) {
        textarea.value = urls.join('\n');
        renderPreview(urls);
    }

    function renderPreview(urls) {
        preview.innerHTML = '';
        urls.forEach((url, idx) => {
            const div = document.createElement('div');
            div.className = 'gallery-item';

            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.onerror = () => { img.style.opacity = '0.3'; };
            div.appendChild(img);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gallery-remove';
            btn.textContent = '×';
            btn.addEventListener('click', () => {
                const current = getUrls();
                current.splice(idx, 1);
                setUrls(current);
            });
            div.appendChild(btn);

            preview.appendChild(div);
        });
    }

    textarea.addEventListener('input', () => renderPreview(getUrls()));

    uploadBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async () => {
        const files = fileInput.files;
        if (!files.length) return;

        uploadBtn.disabled = true;
        const originalText = uploadBtn.textContent;
        uploadBtn.textContent = '⏳ در حال آپلود...';

        const currentUrls = getUrls();
        let success = 0;
        let failed = 0;

        for (const file of files) {
            const fd = new FormData();
            fd.append('files[]', file);
            fd.append('_csrf', csrfToken);

            try {
                const res = await fetch('/modules/portfolio/ajax/upload.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.success && json.urls) {
                    currentUrls.push(...json.urls);
                    success++;
                } else {
                    failed++;
                    console.error('Upload failed:', json.message);
                }
            } catch (err) {
                failed++;
                console.error('Upload error:', err);
            }
        }

        setUrls(currentUrls);
        uploadBtn.disabled = false;
        uploadBtn.textContent = originalText;
        fileInput.value = '';

        if (failed > 0) {
            alert('آپلود: ' + success + ' موفق، ' + failed + ' ناموفق');
        }
    });

    renderPreview(getUrls());
})();
</script>

</body>
</html>

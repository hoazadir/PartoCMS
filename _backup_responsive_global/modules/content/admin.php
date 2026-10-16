<?php
require_once dirname(__DIR__, 2) . '/config.php';

$perm = getPermissions();
$perm->require('content.view');

$pdo = getDB();
$pageTitle = 'مدیریت محتوا';
$activePage = 'content';

// ========== دریافت دسته‌بندی‌ها و رسانه‌ها ==========
$categories = [];
$mediaList = [];
try {
    $categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
    $mediaList = $pdo->query("SELECT * FROM media ORDER BY created_at DESC LIMIT 30")->fetchAll();
} catch (Exception $e) {
    // جداول هنوز ساخته نشدن
}

// ========== پردازش عملیات ==========
$success = '';
$error = '';

// حذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!$perm->can($_SESSION['user_id'], 'content.delete')) {
        $error = 'شما مجوز حذف ندارید';
    } else {
        $stmt = $pdo->prepare("DELETE FROM content_items WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header('Location: ?msg=deleted');
        exit;
    }
}

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $isNew = empty($id);

    if ($isNew && !$perm->can($_SESSION['user_id'], 'content.create')) {
        $error = 'شما مجوز ایجاد محتوا ندارید';
    } elseif (!$isNew && !$perm->can($_SESSION['user_id'], 'content.edit')) {
        $error = 'شما مجوز ویرایش ندارید';
    } else {
        $data = [
            'type_id' => (int) ($_POST['type_id'] ?? 0),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'title' => trim($_POST['title'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'content' => $_POST['content'] ?? '',
            'excerpt' => trim($_POST['excerpt'] ?? ''),
            'featured_image' => trim($_POST['featured_image'] ?? ''),
            'status' => in_array($_POST['status'] ?? '', ['draft','published','archived']) ? $_POST['status'] : 'draft',
            'meta_title' => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'meta_keywords' => trim($_POST['meta_keywords'] ?? ''),
            'og_image' => trim($_POST['og_image'] ?? ''),
            'canonical_url' => trim($_POST['canonical_url'] ?? ''),
            'no_index' => isset($_POST['no_index']) ? 1 : 0,
        ];

        if (empty($data['slug'])) {
            $slug = $data['title'];
            $slug = str_replace([' ', '‌', '،', '.', '?', '!', ':', ';', '(', ')', '«', '»', '"', "'"], '-', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
            $data['slug'] = $slug ?: 'post-' . time();
        }

        if (empty($data['title'])) {
            $error = 'عنوان الزامی است';
        } else {
            try {
                if ($isNew) {
                    $stmt = $pdo->prepare("
                        INSERT INTO content_items
                        (type_id, category_id, title, slug, content, excerpt, featured_image, status, author_id,
                         meta_title, meta_description, meta_keywords, og_image, canonical_url, no_index)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['type_id'], $data['category_id'], $data['title'], $data['slug'],
                        $data['content'], $data['excerpt'], $data['featured_image'], $data['status'],
                        $_SESSION['user_id'],
                        $data['meta_title'], $data['meta_description'], $data['meta_keywords'],
                        $data['og_image'], $data['canonical_url'], $data['no_index']
                    ]);
                    $savedContentId = (int)$pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE content_items
                        SET type_id=?, category_id=?, title=?, slug=?, content=?, excerpt=?,
                            featured_image=?, status=?, meta_title=?, meta_description=?,
                            meta_keywords=?, og_image=?, canonical_url=?, no_index=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $data['type_id'], $data['category_id'], $data['title'], $data['slug'],
                        $data['content'], $data['excerpt'], $data['featured_image'], $data['status'],
                        $data['meta_title'], $data['meta_description'], $data['meta_keywords'],
                        $data['og_image'], $data['canonical_url'], $data['no_index'],
                        $id
                    ]);
                    $savedContentId = (int)$id;
                }

                // ==================== AUTO TRANSLATE HOOK (v2 — Queue) ====================
                // وقتی محتوا publish شد، به صف ترجمه اضافه می‌شود
                if ($data['status'] === 'published' && $savedContentId > 0) {
                    try {
                        require_once __DIR__ . '/../../admin/includes/queue_manager.php';
                        require_once __DIR__ . '/../../admin/includes/environment.php';

                        if (class_exists('QueueManager')) {
                            // چک فعال بودن
                            $enabled = false;
                            try {
                                $stmtS = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_translate_on_publish' LIMIT 1");
                                $stmtS->execute();
                                $enabled = ($stmtS->fetchColumn() === '1');
                            } catch (Throwable $e) {}

                            if ($enabled) {
                                $queue = new QueueManager($pdo);
                                $result = $queue->enqueueContent($savedContentId, $_SESSION['user_id'] ?? null, 5);

                                // ⚡ اگه محیط اجازه می‌ده، سریع شروع کن (اختیاری)
                                if (!empty($result['added']) && Environment::hasSetsid()) {
                                    $script = realpath(__DIR__ . '/../../admin/cron_translation_queue.php');
                                    if ($script) {
                                        $php = PHP_BINARY ?: 'php';
                                        $logFile = __DIR__ . '/../../logs/translation_queue.log';
                                        $cmd = sprintf(
                                            '%s nohup %s %s %d >> %s 2>&1 < /dev/null &',
                                            escapeshellcmd(trim((string)@shell_exec('which setsid 2>/dev/null'))),
                                            escapeshellcmd($php),
                                            escapeshellarg($script),
                                            min(5, (int)$result['added']),
                                            escapeshellarg($logFile)
                                        );
                                        @exec($cmd);
                                    }
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        error_log("AutoTranslate hook error: " . $e->getMessage());
                    }
                }
                // ==================== END AUTO TRANSLATE HOOK ====================

                header('Location: ?msg=saved');
                exit;
            } catch (PDOException $e) {
                $error = 'خطا: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'deleted' ? 'حذف شد' : ($_GET['msg'] === 'saved' ? 'ذخیره شد' : '');
}

// ========== داده‌ها ==========
$items = $pdo->query("
    SELECT ci.*,
        ct.name as type_name, ct.icon as type_icon,
        u.username as author_name,
        c.name as category_name, c.color as category_color, c.icon as category_icon
    FROM content_items ci
    LEFT JOIN content_types ct ON ct.id = ci.type_id
    LEFT JOIN users u ON u.id = ci.author_id
    LEFT JOIN categories c ON c.id = ci.category_id
    ORDER BY ci.created_at DESC
")->fetchAll();

$types = $pdo->query("SELECT * FROM content_types WHERE is_active = 1")->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; border-radius: 12px 12px 0 0 !important; padding: 15px 20px; }
        .top-bar { background: #fff; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .category-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; color: #fff; font-size: 11px; font-weight: bold; }
        .featured-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 2px solid #eee; }
        .media-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; max-height: 400px; overflow-y: auto; }
        .media-picker-item { cursor: pointer; border-radius: 8px; overflow: hidden; border: 2px solid #eee; transition: all 0.2s; }
        .media-picker-item:hover { border-color: #3b82f6; transform: scale(1.03); }
        .media-picker-item img { width: 100%; height: 100px; object-fit: cover; display: block; }
        .form-label { font-weight: bold; font-size: 13px; color: #34495e; }
        .seo-section { margin-top: 25px; border-top: 2px solid #f0f0f0; padding-top: 20px; }
        .seo-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; margin-bottom: 15px; }
        .seo-header h5 { margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
        .seo-arrow { font-size: 14px; transition: transform 0.3s; color: #95a5a6; }
        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h5 style="margin:0;">📝 مدیریت محتوا</h5>
        <?php if ($perm->can($_SESSION['user_id'], 'content.create')): ?>
            <button class="btn btn-primary" onclick="showForm()"><i class="bi bi-plus-lg"></i> محتوای جدید</button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

    <!-- فرم ایجاد/ویرایش -->
    <div class="card mb-4" id="formCard" style="display: <?= $edit ? 'block' : 'none' ?>;">
        <div class="card-header"><strong><?= $edit ? '✏️ ویرایش' : '➕ محتوای جدید' ?></strong></div>
        <div class="card-body">
            <form method="post" id="contentForm">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">عنوان <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="mainTitle" class="form-control" required value="<?= htmlspecialchars($edit['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نوع</label>
                        <select name="type_id" class="form-select">
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($edit['type_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                    <?= $t['icon'] ?> <?= $t['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">دسته‌بندی</label>
                        <select name="category_id" class="form-select">
                            <option value="">— بدون دسته —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($edit['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نامک (slug)</label>
                        <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($edit['slug'] ?? '') ?>" placeholder="خودکار تولید می‌شود">
                    </div>
                </div>

                <!-- تصویر شاخص -->
                <div class="mb-3">
                    <label class="form-label">تصویر شاخص</label>
                    <div class="input-group">
                        <input type="text" name="featured_image" id="featuredImage" class="form-control"
                               value="<?= htmlspecialchars($edit['featured_image'] ?? '') ?>"
                               placeholder="آدرس تصویر یا از گالری انتخاب کنید">
                        <button type="button" class="btn btn-outline-secondary" onclick="showMediaPicker('featured')">
                            <i class="bi bi-images"></i> انتخاب از گالری
                        </button>
                    </div>
                    <div id="imagePreview" style="margin-top:10px;">
                        <?php if (!empty($edit['featured_image'])): ?>
                            <img src="<?= htmlspecialchars($edit['featured_image']) ?>" style="max-width:200px;border-radius:8px;border:2px solid #eee;">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">وضعیت</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= ($edit['status'] ?? '') == 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                            <option value="published" <?= ($edit['status'] ?? '') == 'published' ? 'selected' : '' ?>>منتشر شده</option>
                            <option value="archived" <?= ($edit['status'] ?? '') == 'archived' ? 'selected' : '' ?>>بایگانی</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">خلاصه</label>
                    <textarea name="excerpt" id="mainExcerpt" class="form-control" rows="2"><?= htmlspecialchars($edit['excerpt'] ?? '') ?></textarea>
                </div>

                <!-- ==================== بخش SEO ==================== -->
                <div class="seo-section">
                    <div class="seo-header" onclick="toggleSeoSection()">
                        <h5>
                            🔍 تنظیمات SEO
                            <span style="font-size: 11px; color: #95a5a6; font-weight: normal;">(اختیاری)</span>
                        </h5>
                        <span class="seo-arrow" id="seoArrow">▼</span>
                    </div>

                    <div id="seoFields" style="display: <?= $edit && (!empty($edit['meta_title']) || !empty($edit['meta_description'])) ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">عنوان SEO (Meta Title)</label>
                                <input type="text" name="meta_title" id="metaTitle" class="form-control" maxlength="70"
                                       value="<?= htmlspecialchars($edit['meta_title'] ?? '') ?>"
                                       placeholder="عنوان برای موتورهای جستجو (حداکثر ۷۰ کاراکتر)">
                                <small class="text-muted">اگه خالی باشه، عنوان مقاله استفاده می‌شه</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">کلمات کلیدی</label>
                                <input type="text" name="meta_keywords" class="form-control"
                                       value="<?= htmlspecialchars($edit['meta_keywords'] ?? '') ?>"
                                       placeholder="کلمه۱, کلمه۲, کلمه۳">
                                <small class="text-muted">با کاما جدا کنید</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">توضیحات SEO (Meta Description)</label>
                            <textarea name="meta_description" id="metaDesc" class="form-control" rows="2" maxlength="160"
                                      placeholder="توضیحات کوتاه برای موتورهای جستجو (حداکثر ۱۶۰ کاراکتر)"><?= htmlspecialchars($edit['meta_description'] ?? '') ?></textarea>
                            <small class="text-muted">اگه خالی باشه، از خلاصه استفاده می‌شه</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تصویر Open Graph</label>
                                <div class="input-group">
                                    <input type="text" name="og_image" id="ogImage" class="form-control"
                                           value="<?= htmlspecialchars($edit['og_image'] ?? '') ?>"
                                           placeholder="تصویر برای شبکه‌های اجتماعی">
                                    <button type="button" class="btn btn-outline-secondary" onclick="showMediaPicker('og')">
                                        <i class="bi bi-images"></i>
                                    </button>
                                </div>
                                <div id="ogImagePreview" style="margin-top: 8px;">
                                    <?php if (!empty($edit['og_image'])): ?>
                                        <img src="<?= htmlspecialchars($edit['og_image']) ?>" style="max-width:150px;border-radius:6px;border:1px solid #eee;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">آدرس Canonical</label>
                                <input type="url" name="canonical_url" class="form-control"
                                       value="<?= htmlspecialchars($edit['canonical_url'] ?? '') ?>"
                                       placeholder="https://example.com/post/...">
                                <small class="text-muted">برای جلوگیری از محتوای تکراری</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="no_index" id="noIndex"
                                       <?= !empty($edit['no_index']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="noIndex">
                                    <strong>عدم نمایش در موتورهای جستجو</strong>
                                    <small class="text-muted d-block">اگه فعال باشه، به ربات‌های گوگل دستور noindex داده می‌شه</small>
                                </label>
                            </div>
                        </div>

                        <!-- پیش‌نمایش Google -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <small class="text-muted d-block mb-2">📱 پیش‌نمایش در Google:</small>
                            <div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e0e0e0;">
                                <div style="color: #1a0dab; font-size: 16px; margin-bottom: 3px;" id="googlePreviewTitle">
                                    <?= htmlspecialchars($edit['meta_title'] ?? $edit['title'] ?? 'عنوان مقاله') ?>
                                </div>
                                <div style="color: #006621; font-size: 12px; margin-bottom: 3px; direction: ltr; text-align: left;">
                                    <?= SITE_URL ?>/post.php?id=<?= $edit['id'] ?? '?' ?>
                                </div>
                                <div style="color: #545454; font-size: 12px; line-height: 1.4;" id="googlePreviewDesc">
                                    <?= htmlspecialchars(mb_substr($edit['meta_description'] ?? $edit['excerpt'] ?? 'توضیحات مقاله...', 0, 160)) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3" style="margin-top: 25px;">
                    <label class="form-label">محتوا</label>
                    <textarea name="content" id="contentEditor" class="form-control" rows="12"><?= htmlspecialchars($edit['content'] ?? '') ?></textarea>
                </div>

                <hr>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save"></i> ذخیره</button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="hideForm()">انصراف</button>
            </form>
        </div>
    </div>

    <!-- لیست -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>تصویر</th>
                            <th>نوع</th>
                            <th>عنوان</th>
                            <th>دسته</th>
                            <th>نویسنده</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                هنوز محتوایی ایجاد نشده
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['featured_image'])): ?>
                                            <img src="<?= htmlspecialchars($item['featured_image']) ?>" class="featured-thumb" alt="تصویر">
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $item['type_icon'] ?> <?= htmlspecialchars($item['type_name'] ?? '-') ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                                        <?php if (!empty($item['meta_title'])): ?>
                                            <span class="badge bg-info" style="font-size:9px;">SEO ✓</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['category_name']): ?>
                                            <span class="category-badge" style="background:<?= htmlspecialchars($item['category_color'] ?? '#95a5a6') ?>;">
                                                <?= htmlspecialchars($item['category_icon'] ?? '📁') ?>
                                                <?= htmlspecialchars($item['category_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['author_name'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $badge = ['published' => 'success', 'draft' => 'warning', 'archived' => 'secondary'][$item['status']] ?? 'secondary';
                                        $label = ['published' => 'منتشر', 'draft' => 'پیش‌نویس', 'archived' => 'بایگانی'][$item['status']] ?? $item['status'];
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
                                    </td>
                                    <td class="text-muted small"><?= date('Y/m/d', strtotime($item['created_at'])) ?></td>
                                    <td>
                                        <?php if ($perm->can($_SESSION['user_id'], 'content.edit')): ?>
                                            <a href="?edit=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="ویرایش"><i class="bi bi-pencil"></i></a>
                                        <?php endif; ?>
                                        <?php if ($perm->can($_SESSION['user_id'], 'content.delete')): ?>
                                            <a href="?delete=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف؟')" title="حذف"><i class="bi bi-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- مدال انتخاب رسانه -->
<div id="mediaPicker" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;padding:20px;overflow:auto;" data-target="featured">
    <div style="background:#fff;border-radius:12px;max-width:900px;margin:40px auto;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
            <h5 style="margin:0;">🖼 انتخاب تصویر از گالری</h5>
            <button onclick="hideMediaPicker()" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
        </div>

        <?php if (empty($mediaList)): ?>
            <div class="text-center py-5">
                <div style="font-size:60px;">📭</div>
                <p class="text-muted">هنوز فایلی آپلود نشده است</p>
                <a href="<?= ADMIN_URL ?>/media.php" class="btn btn-primary">
                    <i class="bi bi-upload"></i> آپلود تصویر جدید
                </a>
            </div>
        <?php else: ?>
            <div class="media-picker-grid">
                <?php foreach ($mediaList as $m): ?>
                    <div class="media-picker-item" onclick="selectImage('<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>')">
                        <img src="<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>" alt="<?= htmlspecialchars($m['alt_text'] ?: $m['original_name']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ==================== TinyMCE ====================
function initTinyMCE() {
    if (typeof tinymce === 'undefined') return;

    tinymce.init({
        selector: '#contentEditor',
        language: 'fa',
        directionality: 'rtl',
        height: 500,
        menubar: true,
        branding: false,
        promotion: false,
        plugins: ['advlist','autolink','lists','link','image','charmap','preview','anchor','searchreplace','visualblocks','code','fullscreen','insertdatetime','media','table','help','wordcount','directionality','codesample','emoticons','quickbars'],
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | codesample blockquote hr | removeformat | fullscreen code preview help',
        toolbar_mode: 'wrap',
        content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 15px; line-height: 1.9; direction: rtl; padding: 15px; background: #fff; } img { max-width: 100%; height: auto; border-radius: 8px; } table { border-collapse: collapse; width: 100%; } table td, table th { border: 1px solid #ddd; padding: 8px; }',
        images_upload_handler: function (blobInfo, progress) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                fetch('../../api/upload-editor.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => data.location ? resolve(data.location) : reject({ message: data.error || 'خطا' }))
                .catch(() => reject({ message: 'خطا در ارتباط' }));
            });
        },
        link_default_target: '_blank',
    });
}

// ==================== توابع اصلی ====================
function showForm() {
    document.getElementById('formCard').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => {
        if (typeof tinymce !== 'undefined') {
            tinymce.remove('#contentEditor');
            initTinyMCE();
        }
    }, 100);
}

function hideForm() {
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
    window.location.href = '?';
}

function toggleSeoSection() {
    const fields = document.getElementById('seoFields');
    const arrow = document.getElementById('seoArrow');
    if (fields.style.display === 'none') {
        fields.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        fields.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
    }
}

// ==================== انتخاب تصویر ====================
function showMediaPicker(target) {
    const picker = document.getElementById('mediaPicker');
    picker.dataset.target = target || 'featured';
    picker.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideMediaPicker() {
    document.getElementById('mediaPicker').style.display = 'none';
    document.body.style.overflow = '';
}

function selectImage(url) {
    const picker = document.getElementById('mediaPicker');
    const target = picker.dataset.target || 'featured';

    if (target === 'og') {
        document.getElementById('ogImage').value = url;
        document.getElementById('ogImagePreview').innerHTML = '<img src="' + url + '" style="max-width:150px;border-radius:6px;border:1px solid #eee;">';
    } else {
        document.getElementById('featuredImage').value = url;
        document.getElementById('imagePreview').innerHTML = '<img src="' + url + '" style="max-width:200px;border-radius:8px;border:2px solid #eee;">';
    }

    hideMediaPicker();
}

// ==================== پیش‌نمایش زنده SEO ====================
document.addEventListener('DOMContentLoaded', function() {
    const metaTitle = document.getElementById('metaTitle');
    const metaDesc = document.getElementById('metaDesc');
    const titleInput = document.getElementById('mainTitle');
    const excerptInput = document.getElementById('mainExcerpt');

    if (metaTitle) {
        metaTitle.addEventListener('input', function() {
            document.getElementById('googlePreviewTitle').textContent = this.value || (titleInput ? titleInput.value : 'عنوان مقاله');
        });
    }

    if (metaDesc) {
        metaDesc.addEventListener('input', function() {
            document.getElementById('googlePreviewDesc').textContent = this.value.substring(0, 160) || (excerptInput ? excerptInput.value.substring(0, 160) : 'توضیحات مقاله...');
        });
    }

    if (titleInput) {
        titleInput.addEventListener('input', function() {
            const preview = document.getElementById('googlePreviewTitle');
            if (preview && metaTitle && !metaTitle.value) {
                preview.textContent = this.value || 'عنوان مقاله';
            }
        });
    }

    <?php if ($edit): ?>
    setTimeout(initTinyMCE, 300);
    <?php endif; ?>
});

// بستن با Esc
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') hideMediaPicker();
});

// بستن مدال با کلیک بیرون
const pickerEl = document.getElementById('mediaPicker');
if (pickerEl) {
    pickerEl.addEventListener('click', function(e) {
        if (e.target === this) hideMediaPicker();
    });
}

// قبل از submit
document.getElementById('contentForm').addEventListener('submit', function() {
    if (typeof tinymce !== 'undefined') tinymce.triggerSave();
});
</script>

</body>
</html>

<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    getSecurity()->requireCsrf();
    
    $settings = [
        'default_meta_title' => $_POST['default_meta_title'] ?? '',
        'default_meta_description' => $_POST['default_meta_description'] ?? '',
        'default_meta_keywords' => $_POST['default_meta_keywords'] ?? '',
        'og_site_name' => $_POST['og_site_name'] ?? '',
        'twitter_handle' => $_POST['twitter_handle'] ?? '',
        'google_analytics_id' => $_POST['google_analytics_id'] ?? '',
        'robots_txt' => $_POST['robots_txt'] ?? '',
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO seo_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$key, $value, $value]);
    }

    getSecurity()->log('update_seo', 'seo', null, 'بروزرسانی تنظیمات SEO');
    header('Location: seo.php?msg=saved');
    exit;
}

if (isset($_GET['msg'])) $success = 'تنظیمات SEO ذخیره شد';

// لود تنظیمات
$seoSettings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM seo_settings");
while ($row = $stmt->fetch()) {
    $seoSettings[$row['setting_key']] = $row['setting_value'];
}

// مقالات بدون SEO
$missingSeo = $pdo->query("
    SELECT id, title 
    FROM content_items 
    WHERE status = 'published' 
    AND (meta_description IS NULL OR meta_description = '')
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات SEO | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .form-label { font-weight: bold; font-size: 13px; color: #34495e; }
        @media (max-width: 900px) { .main { margin-right: 70px; padding: 15px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0; color:#1e293b;">🔍 تنظیمات SEO</h4>
            <small class="text-muted">بهینه‌سازی برای موتورهای جستجو</small>
        </div>
        <div class="d-flex gap-2">
            <a href="../sitemap.php" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-diagram-3"></i> Sitemap
            </a>
            <a href="../robots.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-robot"></i> Robots.txt
            </a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post">
        <?= getSecurity()->csrfField() ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-tag" style="color:#3b82f6;"></i>
                        متاتگ‌های پیش‌فرض
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">عنوان پیش‌فرض</label>
                            <input type="text" name="default_meta_title" class="form-control"
                                   value="<?= htmlspecialchars($seoSettings['default_meta_title'] ?? '') ?>"
                                   maxlength="70">
                            <small class="text-muted">حداکثر ۷۰ کاراکتر</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات پیش‌فرض</label>
                            <textarea name="default_meta_description" class="form-control" rows="3" maxlength="160"><?= htmlspecialchars($seoSettings['default_meta_description'] ?? '') ?></textarea>
                            <small class="text-muted">حداکثر ۱۶۰ کاراکتر</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">کلمات کلیدی پیش‌فرض</label>
                            <input type="text" name="default_meta_keywords" class="form-control"
                                   value="<?= htmlspecialchars($seoSettings['default_meta_keywords'] ?? '') ?>"
                                   placeholder="کلمه۱, کلمه۲, کلمه۳">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-share" style="color:#8b5cf6;"></i>
                        شبکه‌های اجتماعی
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">نام سایت در Open Graph</label>
                                <input type="text" name="og_site_name" class="form-control"
                                       value="<?= htmlspecialchars($seoSettings['og_site_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اکانت Twitter</label>
                                <input type="text" name="twitter_handle" class="form-control"
                                       value="<?= htmlspecialchars($seoSettings['twitter_handle'] ?? '') ?>"
                                       placeholder="@username">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-graph-up" style="color:#10b981;"></i>
                        Google Analytics
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">شناسه Google Analytics</label>
                            <input type="text" name="google_analytics_id" class="form-control"
                                   value="<?= htmlspecialchars($seoSettings['google_analytics_id'] ?? '') ?>"
                                   placeholder="G-XXXXXXXXXX">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-robot" style="color:#f59e0b;"></i>
                        محتوای Robots.txt
                    </div>
                    <div class="card-body">
                        <textarea name="robots_txt" class="form-control" rows="8" style="font-family: monospace; direction: ltr; text-align: left;"><?= htmlspecialchars($seoSettings['robots_txt'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-save"></i> ذخیره تنظیمات SEO
                </button>
            </div>

            <div class="col-md-4">
                <!-- پیش‌نمایش Google -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-google" style="color:#4285f4;"></i>
                        پیش‌نمایش در Google
                    </div>
                    <div class="card-body">
                        <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                            <div style="color: #1a0dab; font-size: 18px; margin-bottom: 4px; line-height: 1.3;" id="previewTitle">
                                <?= htmlspecialchars($seoSettings['default_meta_title'] ?? getSetting('site_name', 'وب‌سایت من')) ?>
                            </div>
                            <div style="color: #006621; font-size: 13px; margin-bottom: 4px; direction: ltr; text-align: left;">
                                <?= SITE_URL ?>
                            </div>
                            <div style="color: #545454; font-size: 13px; line-height: 1.5;" id="previewDesc">
                                <?= htmlspecialchars(mb_substr($seoSettings['default_meta_description'] ?? '', 0, 160)) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- مقالات بدون SEO -->
                <?php if (!empty($missingSeo)): ?>
                <div class="card">
                    <div class="card-header" style="background: #fef3c7;">
                        <i class="bi bi-exclamation-triangle" style="color:#d97706;"></i>
                        مقالات بدون توضیحات SEO
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">این مقالات توضیحات SEO ندارند:</small>
                        <?php foreach ($missingSeo as $p): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px;">
                                <a href="../modules/content/admin.php?edit=<?= $p['id'] ?>" style="color:#1e293b;text-decoration:none;">
                                    <?= htmlspecialchars(mb_substr($p['title'], 0, 40)) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- راهنما -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle" style="color:#8b5cf6;"></i>
                        راهنمای SEO
                    </div>
                    <div class="card-body" style="font-size: 12px; line-height: 1.9; color: #64748b;">
                        <strong>✅ نکات مهم:</strong>
                        <ul style="padding-right: 18px; margin-top: 10px;">
                            <li>عنوان بین ۵۰-۷۰ کاراکتر</li>
                            <li>توضیحات بین ۱۲۰-۱۶۰ کاراکتر</li>
                            <li>کلمات کلیدی مرتبط</li>
                            <li>تصویر شاخص برای شبکه‌های اجتماعی</li>
                            <li>Sitemap خودکار ساخته می‌شود</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>

<script>
// پیش‌نمایش زنده
document.querySelector('input[name="default_meta_title"]').addEventListener('input', function() {
    document.getElementById('previewTitle').textContent = this.value || 'عنوان پیش‌فرض';
});
document.querySelector('textarea[name="default_meta_description"]').addEventListener('input', function() {
    document.getElementById('previewDesc').textContent = this.value.substring(0, 160) || 'توضیحات پیش‌فرض...';
});
</script>

</body>
</html>

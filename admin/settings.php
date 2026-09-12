<?php
// admin/settings.php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// ذخیره تنظیمات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_name' => $_POST['site_name'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'admin_email' => $_POST['admin_email'] ?? '',
        'theme_color' => $_POST['theme_color'] ?? '#3b97e3',
        'posts_per_page' => (int) ($_POST['posts_per_page'] ?? 6),
        'comments_auto_approve' => isset($_POST['comments_auto_approve']) ? '1' : '0',
        'site_language' => $_POST['site_language'] ?? 'fa',
    ];

    try {
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        $success = 'تنظیمات با موفقیت ذخیره شد';
        $siteName = $settings['site_name'];
    } catch (PDOException $e) {
        $error = 'خطا در ذخیره: ' . $e->getMessage();
    }
}

// دریافت تنظیمات فعلی
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// اطلاعات سیستم
$systemInfo = [
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص',
    'db_size' => (function($pdo) {
        try {
            $result = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
            return $result ? $result . ' MB' : '0 MB';
        } catch(Exception $e) { return 'نامشخص'; }
    })($pdo),
    'tables_count' => (function($pdo) {
        try {
            return $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
        } catch(Exception $e) { return 0; }
    })($pdo),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات سایت | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .form-label { font-weight: bold; font-size: 13px; color: #34495e; }
        .form-control, .form-select { border: 2px solid #e0e0e0; border-radius: 8px; padding: 10px 12px; font-family: Tahoma; font-size: 14px; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        .info-item:last-child { border-bottom: none; }
        .info-item .label { color: #64748b; }
        .info-item .value { font-weight: bold; color: #1e293b; }
        .color-preview { width: 40px; height: 40px; border-radius: 8px; border: 2px solid #e0e0e0; display: inline-block; vertical-align: middle; margin-left: 10px; cursor: pointer; }
        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0; color:#1e293b;">⚙️ تنظیمات سایت</h4>
            <small class="text-muted">پیکربندی و تنظیمات کلی</small>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
        <div class="row">
            <!-- تنظیمات اصلی -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-globe" style="color: #3b82f6;"></i>
                        تنظیمات عمومی
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">نام سایت</label>
                            <input type="text" name="site_name" class="form-control"
                                   value="<?= htmlspecialchars($settings['site_name'] ?? 'وب‌سایت من') ?>"
                                   placeholder="نام سایت شما">
                            <small class="text-muted">در بالای صفحات و عنوان‌ها نمایش داده می‌شود</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">توضیحات سایت</label>
                            <textarea name="site_description" class="form-control" rows="3"
                                      placeholder="توضیح کوتاه درباره سایت"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                            <small class="text-muted">در بخش Hero صفحه اصلی و برای SEO استفاده می‌شود</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ایمیل مدیر</label>
                                <input type="email" name="admin_email" class="form-control"
                                       value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>"
                                       placeholder="admin@example.com">
                                <small class="text-muted">دریافت اعلان‌ها و تماس‌ها</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">زبان سایت</label>
                                <select name="site_language" class="form-select">
                                    <option value="fa" <?= ($settings['site_language'] ?? 'fa') === 'fa' ? 'selected' : '' ?>>فارسی (RTL)</option>
                                    <option value="en" <?= ($settings['site_language'] ?? 'fa') === 'en' ? 'selected' : '' ?>>English (LTR)</option>
                                    <option value="ar" <?= ($settings['site_language'] ?? 'fa') === 'ar' ? 'selected' : '' ?>>العربية (RTL)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تعداد مقالات در هر صفحه</label>
                                <input type="number" name="posts_per_page" class="form-control"
                                       value="<?= htmlspecialchars($settings['posts_per_page'] ?? '6') ?>"
                                       min="1" max="50">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رنگ اصلی تم</label>
                                <input type="color" name="theme_color" class="form-control form-control-color"
                                       value="<?= htmlspecialchars($settings['theme_color'] ?? '#3b97e3') ?>"
                                       style="height:42px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="comments_auto_approve" id="autoApprove"
                                       <?= ($settings['comments_auto_approve'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoApprove">
                                    <strong>تأیید خودکار دیدگاه‌ها</strong>
                                    <small class="text-muted d-block">اگر فعال باشد، دیدگاه‌ها بدون تأیید نمایش داده می‌شوند</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- اطلاعات سیستم -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle" style="color: #8b5cf6;"></i>
                        اطلاعات سیستم
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <span class="label">نسخه PHP</span>
                            <span class="value"><?= htmlspecialchars($systemInfo['php_version']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">وب‌سرور</span>
                            <span class="value" style="font-size:11px;"><?= htmlspecialchars($systemInfo['server']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">حجم دیتابیس</span>
                            <span class="value"><?= htmlspecialchars($systemInfo['db_size']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">تعداد جداول</span>
                            <span class="value"><?= $systemInfo['tables_count'] ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">آدرس سایت</span>
                            <span class="value" style="font-size:11px;direction:ltr;"><?= htmlspecialchars(SITE_URL) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- دکمه ذخیره -->
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>ذخیره تغییرات</strong>
                    <small class="text-muted d-block">تغییرات بلافاصله اعمال می‌شوند</small>
                </div>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-save"></i> ذخیره تنظیمات
                </button>
            </div>
        </div>
    </form>

</div>

</body>
</html>

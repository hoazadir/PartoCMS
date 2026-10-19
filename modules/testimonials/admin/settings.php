<?php
/**
 * PartoCMS — Testimonials Admin
 * تنظیمات ماژول
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

// ─────────────────────────────────────────────────────────────
// خواندن تنظیمات فعلی
// ─────────────────────────────────────────────────────────────
$config = testimonials_config();
$defaults = $config['defaults'];

// ─────────────────────────────────────────────────────────────
// تعریف فیلدهای تنظیمات (گروه‌بندی شده)
// ─────────────────────────────────────────────────────────────
$fields = [
    'module_status' => [
        'title' => '🔌 وضعیت ماژول',
        'description' => 'فعال یا غیرفعال کردن کل ماژول نظرات مشتریان در سایت',
        'fields' => [
            'is_enabled' => [
                'type'  => 'checkbox',
                'label' => 'فعال بودن ماژول',
                'help'  => 'اگر غیرفعال باشد، منو و فرم‌های ثبت نظر در سایت نمایش داده نمی‌شوند.',
                'source' => 'db_module',
            ],
        ],
    ],
    'general' => [
        'title' => '⚙️ تنظیمات عمومی',
        'description' => 'تنظیمات پایه برای ثبت و نمایش نظرات',
        'fields' => [
            'auto_approve' => [
                'type'  => 'checkbox',
                'label' => 'تأیید خودکار نظرات',
                'help'  => 'اگر فعال باشد، نظرات فرانت‌اند بدون نیاز به تأیید منتشر می‌شوند',
            ],
            'require_email' => [
                'type'  => 'checkbox',
                'label' => 'ایمیل اجباری',
                'help'  => 'کاربر باید ایمیل وارد کند (برای تأیید و تماس)',
            ],
            'allow_guest' => [
                'type'  => 'checkbox',
                'label' => 'اجازه به مهمان',
                'help'  => 'کاربران بدون ثبت‌نام می‌توانند نظر ثبت کنند',
            ],
            'default_status' => [
                'type'    => 'select',
                'label'   => 'وضعیت پیش‌فرض نظرات جدید',
                'help'    => 'وضعیت نظراتی که از فرانت‌اند ثبت می‌شوند',
                'options' => [
                    'pending'   => '⏳ در انتظار تأیید',
                    'published' => '✅ منتشرشده (مستقیم)',
                ],
            ],
            'items_per_page' => [
                'type'  => 'number',
                'label' => 'تعداد آیتم در صفحه (Admin)',
                'help'  => 'تعداد نظرات نمایش داده شده در لیست مدیریت',
                'min'   => 5,
                'max'   => 100,
            ],
        ],
    ],
    'validation' => [
        'title' => '🛡️ ضد اسپم و اعتبارسنجی',
        'description' => 'قوانین تشخیص و جلوگیری از اسپم',
        'fields' => [
            'spam_cooldown' => [
                'type'  => 'number',
                'label' => 'فاصله بین دو نظر (دقیقه)',
                'help'  => 'یک IP باید حداقل این مدت صبر کند بین دو نظر',
                'min'   => 0,
                'max'   => 1440,
            ],
            'spam_daily_limit' => [
                'type'  => 'number',
                'label' => 'حداکثر نظر روزانه از یک IP',
                'help'  => 'اگر IP بیشتر از این تعداد نظر ثبت کند، بلاک می‌شود',
                'min'   => 1,
                'max'   => 100,
            ],
            'min_content_length' => [
                'type'  => 'number',
                'label' => 'حداقل طول متن (کاراکتر)',
                'help'  => 'متن نظر باید حداقل این تعداد کاراکتر داشته باشد',
                'min'   => 0,
                'max'   => 500,
            ],
            'max_content_length' => [
                'type'  => 'number',
                'label' => 'حداکثر طول متن (کاراکتر)',
                'help'  => 'متن نظر نباید بیشتر از این تعداد باشد',
                'min'   => 100,
                'max'   => 20000,
            ],
        ],
    ],
    'display' => [
        'title' => '🎨 نمایش در سایت',
        'description' => 'چه اطلاعاتی در ویجت نمایش داده شوند',
        'fields' => [
            'show_avatar' => [
                'type'  => 'checkbox',
                'label' => 'نمایش آواتار',
                'help'  => 'عکس مشتری در کنار نظر نمایش داده شود',
            ],
            'show_company' => [
                'type'  => 'checkbox',
                'label' => 'نمایش شرکت',
                'help'  => 'نام شرکت مشتری زیر نام نمایش داده شود',
            ],
            'show_position' => [
                'type'  => 'checkbox',
                'label' => 'نمایش سمت',
                'help'  => 'سمت شغلی (مثلاً مدیرعامل) نمایش داده شود',
            ],
            'show_rating' => [
                'type'  => 'checkbox',
                'label' => 'نمایش امتیاز ستاره‌ای',
                'help'  => 'ستاره‌های امتیاز کنار نظر نمایش داده شوند',
            ],
        ],
    ],
    'widget' => [
        'title' => '🧩 تنظیمات ویجت (شورت‌کد)',
        'description' => 'پیش‌فرض‌های نمایش ویجت در فرانت‌اند',
        'fields' => [
            'limit' => [
                'type'  => 'number',
                'label' => 'تعداد آیتم پیش‌فرض',
                'help'  => 'تعداد نظرات نمایش داده شده در ویجت (اگر در شورت‌کد تعریف نشده)',
                'min'   => 1,
                'max'   => 50,
                'source' => 'widget',
            ],
            'columns' => [
                'type'  => 'number',
                'label' => 'تعداد ستون (Grid)',
                'help'  => 'تعداد ستون در حالت grid',
                'min'   => 1,
                'max'   => 4,
                'source' => 'widget',
            ],
            'style' => [
                'type'    => 'select',
                'label'   => 'حالت نمایش پیش‌فرض',
                'help'    => 'شکل نمایش ویجت',
                'options' => [
                    'grid'     => 'شبکه (Grid)',
                    'slider'   => 'اسلایدر (Slider)',
                    'carousel' => 'کاروسل (Carousel)',
                ],
                'source' => 'widget',
            ],
        ],
    ],
];

// ─────────────────────────────────────────────────────────────
// خواندن مقادیر فعلی
// ─────────────────────────────────────────────────────────────
$current = [];
$pdo = getDB();
$stmtMod = $pdo->prepare("SELECT is_enabled FROM modules WHERE slug = 'testimonials' LIMIT 1");
$stmtMod->execute();
$current['is_enabled'] = (bool) $stmtMod->fetchColumn();

foreach ($fields as $group) {
    foreach ($group['fields'] as $key => $def) {
        if ($key === 'is_enabled') continue;
        $source = $def['source'] ?? 'defaults';
        if ($source === 'widget') {
            $widgetConfig = $config['widget'];
            $current[$key] = $widgetConfig[$key] ?? $defaults[$key] ?? null;
        } else {
            // از DB بخوان، اگر نبود از defaults
            $current[$key] = testimonials_setting($key);
        }
    }
}

// ─────────────────────────────────────────────────────────────
// POST: ذخیره تنظیمات
// ─────────────────────────────────────────────────────────────
$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!testimonials_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");

            $stmtMod = $pdo->prepare("UPDATE modules SET is_enabled = ? WHERE slug = 'testimonials'");
            $stmtMod->execute([!empty($_POST['settings_is_enabled']) ? 1 : 0]);

            foreach ($fields as $group) {
                foreach ($group['fields'] as $key => $def) {
                    if ($key === 'is_enabled') continue; // قبلاً ذخیره شد
                    
                    $inputName = 'settings_' . $key;
                    $value = null;

                    if ($def['type'] === 'checkbox') {
                        $value = !empty($_POST[$inputName]) ? '1' : '0';
                    } elseif ($def['type'] === 'number') {
                        $val = (int) ($_POST[$inputName] ?? 0);
                        $val = max($def['min'] ?? PHP_INT_MIN, min($def['max'] ?? PHP_INT_MAX, $val));
                        $value = (string) $val;
                    } else {
                        $value = (string) ($_POST[$inputName] ?? '');
                    }

                    $stmt->execute(['testimonials_' . $key, $value]);
                }
            }

            $saved = true;
            // reload مقادیر
            foreach ($fields as $group) {
                foreach ($group['fields'] as $key => $def) {
                    $current[$key] = testimonials_setting($key);
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'خطا در ذخیره: ' . $e->getMessage();
        }
    }
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات نظرات مشتریان | <?= t_esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h2 { margin: 0; font-size: 20px; color: #1e293b; }
        .group-card { background: #fff; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        .group-header { padding: 18px 25px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(to right, #f8fafc, #fff); }
        .group-header h3 { margin: 0; font-size: 16px; color: #1e293b; }
        .group-header p { margin: 4px 0 0; font-size: 12px; color: #64748b; }
        .group-body { padding: 20px 25px; }
        .field { padding: 15px 0; border-bottom: 1px solid #f1f5f9; }
        .field:last-child { border-bottom: none; }
        .field-row { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
        .field-label { flex: 1; min-width: 250px; }
        .field-label label { display: block; font-size: 14px; color: #1e293b; font-weight: 600; margin-bottom: 4px; cursor: pointer; }
        .field-label small { display: block; font-size: 12px; color: #64748b; line-height: 1.5; }
        .field-input { flex-shrink: 0; }
        .field-input input[type=number], .field-input select {
            padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;
            font-size: 13px; min-width: 200px; font-family: inherit;
        }
        .field-input input[type=number]:focus, .field-input select:focus {
            outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }
        .toggle { position: relative; display: inline-block; width: 52px; height: 28px; cursor: pointer; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 28px; transition: .3s; }
        .toggle .slider:before { content: ""; position: absolute; height: 22px; width: 22px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; }
        .toggle input:checked + .slider { background: #6366f1; }
        .toggle input:checked + .slider:before { transform: translateX(24px); }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border-right: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .form-actions { position: sticky; bottom: 20px; background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 -2px 20px rgba(0,0,0,0.08); display: flex; gap: 10px; justify-content: flex-end; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        @media (max-width: 1100px) {
            .main { margin-right: 0; padding: 70px 15px 15px; }
            .field-row { flex-direction: column; align-items: flex-start; }
            .field-input { width: 100%; }
            .field-input input, .field-input select { width: 100%; }
            .form-actions { flex-direction: column-reverse; }
            .form-actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h2>⚙️ تنظیمات نظرات مشتریان</h2>
        <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت به لیست
        </a>
    </div>

    <?php if ($saved): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> تنظیمات با موفقیت ذخیره شد
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>خطا:</strong>
            <ul style="margin:0; padding-right:20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= t_esc($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= testimonials_csrf_field() ?>

        <?php foreach ($fields as $groupKey => $group): ?>
            <div class="group-card">
                <div class="group-header">
                    <h3><?= t_esc($group['title']) ?></h3>
                    <p><?= t_esc($group['description']) ?></p>
                </div>
                <div class="group-body">
                    <?php foreach ($group['fields'] as $key => $def): ?>
                        <?php $inputName = 'settings_' . $key; ?>
                        <div class="field">
                            <div class="field-row">
                                <div class="field-label">
                                    <label for="<?= t_esc($inputName) ?>"><?= t_esc($def['label']) ?></label>
                                    <small><?= t_esc($def['help']) ?></small>
                                </div>
                                <div class="field-input">
                                    <?php if ($def['type'] === 'checkbox'): ?>
                                        <label class="toggle">
                                            <input type="checkbox" id="<?= t_esc($inputName) ?>" name="<?= t_esc($inputName) ?>" value="1" <?= !empty($current[$key]) ? 'checked' : '' ?>>
                                            <span class="slider"></span>
                                        </label>
                                    <?php elseif ($def['type'] === 'number'): ?>
                                        <input type="number" id="<?= t_esc($inputName) ?>" name="<?= t_esc($inputName) ?>"
                                               value="<?= (int) $current[$key] ?>"
                                               min="<?= (int) ($def['min'] ?? 0) ?>"
                                               max="<?= (int) ($def['max'] ?? 999) ?>">
                                    <?php elseif ($def['type'] === 'select'): ?>
                                        <select id="<?= t_esc($inputName) ?>" name="<?= t_esc($inputName) ?>">
                                            <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                                                <option value="<?= t_esc($optVal) ?>" <?= (string) $current[$key] === (string) $optVal ? 'selected' : '' ?>>
                                                    <?= t_esc($optLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> ذخیره تنظیمات
            </button>
        </div>

    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

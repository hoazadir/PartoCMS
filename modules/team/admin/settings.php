<?php
/**
 * PartoCMS — Team Admin
 * تنظیمات ماژول تیم
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$config = team_config();
$defaults = $config['defaults'];

// ─── تعریف فیلدها ───
$fields = [
    'module_status' => [
        'title' => '🔌 وضعیت ماژول',
        'description' => 'فعال یا غیرفعال کردن کل ماژول تیم در سایت',
        'fields' => [
            'is_enabled' => [
                'type'  => 'checkbox',
                'label' => 'فعال بودن ماژول',
                'help'  => 'اگر غیرفعال باشد، منو و ویجت‌های تیم در سایت نمایش داده نمی‌شوند.',
            ],
        ],
    ],
    'general' => [
        'title' => '⚙️ تنظیمات عمومی',
        'description' => 'تنظیمات پایه نمایش اعضای تیم',
        'fields' => [
            'items_per_page' => [
                'type'  => 'number',
                'label' => 'تعداد آیتم در صفحه (Admin)',
                'help'  => 'تعداد اعضا در هر صفحه لیست مدیریت',
                'min'   => 5,
                'max'   => 100,
            ],
            'default_status' => [
                'type'    => 'select',
                'label'   => 'وضعیت پیش‌فرض اعضای جدید',
                'help'    => 'وضعیت اعضایی که اضافه می‌شوند',
                'options' => [
                    'published' => '✅ منتشرشده',
                    'hidden'    => '🙈 مخفی',
                ],
            ],
        ],
    ],
    'display' => [
        'title' => '🎨 نمایش در سایت',
        'description' => 'چه اطلاعاتی در ویجت تیم نمایش داده شوند',
        'fields' => [
            'show_photo' => [
                'type'  => 'checkbox',
                'label' => 'نمایش عکس',
                'help'  => 'عکس عضو تیم نمایش داده شود',
            ],
            'show_position' => [
                'type'  => 'checkbox',
                'label' => 'نمایش سمت',
                'help'  => 'سمت شغلی زیر نام نمایش داده شود',
            ],
            'show_department' => [
                'type'  => 'checkbox',
                'label' => 'نمایش دپارتمان',
                'help'  => 'نام دپارتمان نمایش داده شود',
            ],
            'show_bio' => [
                'type'  => 'checkbox',
                'label' => 'نمایش بیوگرافی',
                'help'  => 'بیوگرافی کوتاه نمایش داده شود',
            ],
            'show_social' => [
                'type'  => 'checkbox',
                'label' => 'نمایش شبکه‌های اجتماعی',
                'help'  => 'آیکون‌های LinkedIn، Twitter، Instagram نمایش داده شوند',
            ],
        ],
    ],
    'widget' => [
        'title' => '🧩 تنظیمات ویجت',
        'description' => 'پیش‌فرض‌های نمایش ویجت تیم',
        'fields' => [
            'limit' => [
                'type'  => 'number',
                'label' => 'تعداد پیش‌فرض',
                'help'  => 'تعداد اعضای نمایش‌داده‌شده',
                'min'   => 1,
                'max'   => 50,
                'source' => 'widget',
            ],
            'columns' => [
                'type'  => 'number',
                'label' => 'تعداد ستون',
                'help'  => 'تعداد ستون‌های شبکه',
                'min'   => 1,
                'max'   => 4,
                'source' => 'widget',
            ],
            'style' => [
                'type'    => 'select',
                'label'   => 'حالت نمایش',
                'help'    => 'شکل نمایش ویجت',
                'options' => [
                    'grid'    => 'شبکه (Grid)',
                    'slider'  => 'اسلایدر (Slider)',
                    'cards'   => 'کارت‌ها (Cards)',
                ],
                'source' => 'widget',
            ],
        ],
    ],
];

// ─── خواندن مقادیر فعلی ───
$current = [];
$pdo = getDB();
$stmtMod = $pdo->prepare("SELECT is_enabled FROM modules WHERE slug = 'team' LIMIT 1");
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
            $current[$key] = team_setting($key);
        }
    }
}

// ─── ذخیره ───
$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!team_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        try {
            // وضعیت ماژول
            $stmtUpd = $pdo->prepare("UPDATE modules SET is_enabled = ? WHERE slug = 'team'");
            $stmtUpd->execute([!empty($_POST['settings_is_enabled']) ? 1 : 0]);

            // سایر تنظیمات
            $stmtSet = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");

            foreach ($fields as $group) {
                foreach ($group['fields'] as $key => $def) {
                    if ($key === 'is_enabled') continue;

                    $inputName = 'settings_' . $key;
                    $value = null;

                    if ($def['type'] === 'checkbox') {
                        $value = !empty($_POST[$inputName]) ? '1' : '0';
                    } elseif ($def['type'] === 'number') {
                        $val = (int) ($_POST[$inputName] ?? 0);
                        $val = max($def['min'] ?? 0, min($def['max'] ?? 999, $val));
                        $value = (string) $val;
                    } else {
                        $value = (string) ($_POST[$inputName] ?? '');
                    }

                    $stmtSet->execute(['team_' . $key, $value]);
                }
            }

            $saved = true;
            // Reload
            $stmtMod->execute();
            $current['is_enabled'] = (bool) $stmtMod->fetchColumn();
            foreach ($fields as $group) {
                foreach ($group['fields'] as $key => $def) {
                    if ($key !== 'is_enabled') {
                        $current[$key] = team_setting($key);
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'خطا: ' . $e->getMessage();
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
    <title>تنظیمات تیم | <?= t_esc($siteName) ?></title>
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
        .field-label label { display: block; font-size: 14px; color: #1e293b; font-weight: 600; margin-bottom: 4px; }
        .field-label small { display: block; font-size: 12px; color: #64748b; line-height: 1.5; }
        .field-input { flex-shrink: 0; }
        .field-input input[type=number], .field-input select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; min-width: 200px; font-family: inherit; }
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
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .field-row { flex-direction: column; align-items: flex-start; } .field-input { width: 100%; } .field-input input, .field-input select { width: 100%; } .form-actions { flex-direction: column-reverse; } .form-actions .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h2>⚙️ تنظیمات تیم</h2>
        <a href="<?= t_esc(team_admin_url('index.php')) ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if ($saved): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> تنظیمات ذخیره شد</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>خطا:</strong>
            <ul style="margin:0; padding-right:20px;"><?php foreach ($errors as $e): ?><li><?= t_esc($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?= team_csrf_field() ?>

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
                                        <input type="number" id="<?= t_esc($inputName) ?>" name="<?= t_esc($inputName) ?>" value="<?= (int) $current[$key] ?>" min="<?= (int) ($def['min'] ?? 0) ?>" max="<?= (int) ($def['max'] ?? 999) ?>">
                                    <?php elseif ($def['type'] === 'select'): ?>
                                        <select id="<?= t_esc($inputName) ?>" name="<?= t_esc($inputName) ?>">
                                            <?php foreach ($def['options'] as $optVal => $optLabel): ?>
                                                <option value="<?= t_esc($optVal) ?>" <?= (string) $current[$key] === (string) $optVal ? 'selected' : '' ?>><?= t_esc($optLabel) ?></option>
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
            <a href="<?= t_esc(team_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> ذخیره تنظیمات</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

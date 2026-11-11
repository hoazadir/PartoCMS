<?php
/**
 * PartoCMS — Properties Admin: تنظیمات ماژول املاک
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$settings = properties_settings_service();
$schema   = $settings->schema();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!properties_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE modules SET is_enabled = ? WHERE slug = 'properties'");
            $stmt->execute([!empty($_POST['settings_is_enabled']) ? 1 : 0]);
        } catch (\Throwable $e) {
            $errors[] = 'خطا در به‌روزرسانی وضعیت ماژول: ' . $e->getMessage();
        }

        $payload = [];
        foreach ($schema as $key => $def) {
            if ($key === 'is_enabled') continue;
            $input = 'settings_' . $key;
            $payload[$key] = match ($def['type']) {
                'bool' => !empty($_POST[$input]),
                'int'  => (int) ($_POST[$input] ?? 0),
                default => trim((string) ($_POST[$input] ?? '')),
            };
        }

        $result = $settings->saveMany($payload);
        if ($result['errors']) {
            foreach ($result['errors'] as $k => $msg) $errors[] = "$k: $msg";
        } else {
            properties_flash('success', 'تنظیمات با موفقیت ذخیره شد.');
            properties_redirect(properties_admin_url('settings.php'));
        }
    }
}

$values = $settings->all();
$flash  = properties_flash_pull();

$pageTitle = 'تنظیمات املاک';
require_once __DIR__ . '/../../../admin/includes/header.php';
require_once __DIR__ . '/../../../admin/includes/sidebar.php';
?>
<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">⚙️ تنظیمات ماژول املاک</h2>
        <a href="<?= properties_e(properties_admin_url('index.php')) ?>" class="btn btn-outline-secondary">← بازگشت</a>
    </div>
    <?php if ($flash): ?>
        <div class="alert alert-<?= properties_e($flash['type']) ?>"><?= properties_e($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= properties_e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
        <input type="hidden" name="_csrf" value="<?= properties_e(properties_csrf_token()) ?>">
        <div class="card-body">
            <h5 class="border-bottom pb-2 mb-3">🔌 وضعیت ماژول</h5>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="settings_is_enabled"
                       name="settings_is_enabled" value="1" <?= !empty($values['is_enabled']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="settings_is_enabled">فعال بودن ماژول املاک</label>
            </div>

            <h5 class="border-bottom pb-2 mb-3">📦 نمایش ویجت</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="settings_widget_location" class="form-label">محل نمایش خودکار</label>
                    <select id="settings_widget_location" name="settings_widget_location" class="form-select">
                        <?php foreach (['none' => 'هیچ‌کدام (فقط شورت‌کد)', 'home' => 'صفحه اصلی', 'footer' => 'فوتر', 'header' => 'هدر', 'all_pages' => 'همه صفحات'] as $v => $l): ?>
                            <option value="<?= properties_e($v) ?>" <?= $values['widget_location'] === $v ? 'selected' : '' ?>><?= properties_e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="settings_widget_limit" class="form-label">تعداد</label>
                    <input type="number" id="settings_widget_limit" name="settings_widget_limit" class="form-control" min="1" max="50" value="<?= (int) $values['widget_limit'] ?>">
                </div>
                <div class="col-md-2">
                    <label for="settings_widget_columns" class="form-label">ستون‌ها</label>
                    <input type="number" id="settings_widget_columns" name="settings_widget_columns" class="form-control" min="1" max="4" value="<?= (int) $values['widget_columns'] ?>">
                </div>
                <div class="col-md-2">
                    <label for="settings_widget_style" class="form-label">حالت</label>
                    <select id="settings_widget_style" name="settings_widget_style" class="form-select">
                        <?php foreach (['grid' => 'شبکه‌ای', 'slider' => 'اسلایدر', 'list' => 'لیستی'] as $v => $l): ?>
                            <option value="<?= properties_e($v) ?>" <?= $values['widget_style'] === $v ? 'selected' : '' ?>><?= properties_e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="settings_widget_deal_type" class="form-label">نوع معامله</label>
                    <select id="settings_widget_deal_type" name="settings_widget_deal_type" class="form-select">
                        <?php foreach (['' => 'همه', 'sale' => 'فروش', 'rent' => 'اجاره', 'mortgage' => 'رهن'] as $v => $l): ?>
                            <option value="<?= properties_e($v) ?>" <?= $values['widget_deal_type'] === $v ? 'selected' : '' ?>><?= properties_e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="settings_widget_featured"
                       name="settings_widget_featured" value="1" <?= !empty($values['widget_featured']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="settings_widget_featured">فقط املاک ویژه</label>
            </div>

            <h5 class="border-bottom pb-2 mb-3">📄 آرشیو</h5>
            <div class="col-md-3">
                <label for="settings_items_per_page" class="form-label">تعداد ملک در هر صفحه</label>
                <input type="number" id="settings_items_per_page" name="settings_items_per_page" class="form-control" min="1" max="100" value="<?= (int) $values['items_per_page'] ?>">
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">💾 ذخیره تنظیمات</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../../admin/includes/footer.php'; ?>

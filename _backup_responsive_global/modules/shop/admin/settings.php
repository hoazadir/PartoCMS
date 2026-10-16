<?php
/**
 * PartoCMS - Shop Admin - Settings
 * تنظیمات فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);

$success = '';
$error = '';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $allowed = [
            'enabled', 'currency', 'currency_symbol', 'tax_rate',
            'shipping_cost', 'free_shipping_over', 'products_per_page',
            'order_prefix', 'low_stock_threshold', 'guest_checkout'
        ];

        $stmt = $pdo->prepare("
            INSERT INTO shop_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([$key, trim((string) $_POST[$key])]);
            } else {
                // چک‌باکس‌ها وقتی تیک نخورده باشند ارسال نمی‌شوند
                if (in_array($key, ['enabled', 'guest_checkout'], true)) {
                    $stmt->execute([$key, '0']);
                }
            }
        }

        $success = 'تنظیمات با موفقیت ذخیره شد';
        // بازخوانی تنظیمات
        $shop = new ShopManager($pdo);
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

// خواندن تنظیمات فعلی
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM shop_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Throwable $e) {}

$get = fn($k, $d = '') => $settings[$k] ?? $d;

$pageTitle = 'تنظیمات فروشگاه';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);
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
        .card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-gear"></i> تنظیمات فروشگاه</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت به داشبورد
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <!-- وضعیت فروشگاه -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-shop"></i> وضعیت فروشگاه
            </div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled"
                           value="1" <?= $get('enabled', '1') == '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="enabled">
                        فروشگاه فعال باشد
                    </label>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" name="guest_checkout" id="guest_checkout"
                           value="1" <?= $get('guest_checkout', '1') == '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="guest_checkout">
                        خرید مهمان (بدون نیاز به ثبت‌نام) فعال باشد
                    </label>
                </div>
            </div>
        </div>

        <!-- واحد پول -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-currency-exchange"></i> واحد پول
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">کد ارز</label>
                        <input type="text" name="currency" class="form-control"
                               value="<?= htmlspecialchars($get('currency', 'IRR')) ?>"
                               placeholder="IRR, USD, EUR">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">نماد ارز</label>
                        <input type="text" name="currency_symbol" class="form-control"
                               value="<?= htmlspecialchars($get('currency_symbol', 'تومان')) ?>"
                               placeholder="تومان، $">
                    </div>
                </div>
            </div>
        </div>

        <!-- مالیات و ارسال -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <i class="bi bi-calculator"></i> مالیات و ارسال
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نرخ مالیات (درصد)</label>
                        <input type="number" name="tax_rate" class="form-control"
                               value="<?= htmlspecialchars($get('tax_rate', '9')) ?>"
                               step="0.01" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">هزینه ارسال (تومان)</label>
                        <input type="number" name="shipping_cost" class="form-control"
                               value="<?= htmlspecialchars($get('shipping_cost', '50000')) ?>"
                               step="1000" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ارسال رایگان برای خرید بالای (تومان)</label>
                        <input type="number" name="free_shipping_over" class="form-control"
                               value="<?= htmlspecialchars($get('free_shipping_over', '1000000')) ?>"
                               step="10000" min="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- محصولات و سفارشات -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-box"></i> محصولات و سفارشات
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">تعداد محصول در هر صفحه</label>
                        <input type="number" name="products_per_page" class="form-control"
                               value="<?= htmlspecialchars($get('products_per_page', '12')) ?>"
                               min="1" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">پیشوند شماره سفارش</label>
                        <input type="text" name="order_prefix" class="form-control"
                               value="<?= htmlspecialchars($get('order_prefix', 'ORD-')) ?>"
                               placeholder="ORD-">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">حد آستانه موجودی کم</label>
                        <input type="number" name="low_stock_threshold" class="form-control"
                               value="<?= htmlspecialchars($get('low_stock_threshold', '5')) ?>"
                               min="1">
                    </div>
                </div>
            </div>
        </div>

        <!-- دکمه ذخیره -->
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> ذخیره تنظیمات
            </button>
            <a href="index.php" class="btn btn-outline-secondary btn-lg">
                انصراف
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

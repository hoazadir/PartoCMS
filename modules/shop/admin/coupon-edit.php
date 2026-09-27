<?php
/**
 * PartoCMS - Shop Admin - Coupon Edit
 * ویرایش/افزودن کوپن تخفیف
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/CouponManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$couponManager = new CouponManager($pdo);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$coupon = $isEdit ? $couponManager->getById($id) : null;

if ($isEdit && !$coupon) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'کوپن یافت نشد'];
    header('Location: coupons.php');
    exit;
}

$errors = [];

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'percentage';
        $value = (float) ($_POST['value'] ?? 0);
        $minOrderAmount = !empty($_POST['min_order_amount']) ? (float) $_POST['min_order_amount'] : null;
        $maxDiscount = !empty($_POST['max_discount']) ? (float) $_POST['max_discount'] : null;
        $usageLimit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;
        $usageLimitPerUser = (int) ($_POST['usage_limit_per_user'] ?? 1);
        $startsAt = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code)) $errors[] = 'کد کوپن الزامی است';
        if ($value <= 0) $errors[] = 'مقدار تخفیف باید بیشتر از صفر باشد';
        if ($type === 'percentage' && $value > 100) $errors[] = 'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد';

        if (empty($errors)) {
            $data = [
                'code' => $code,
                'description' => $description,
                'type' => $type,
                'value' => $value,
                'min_order_amount' => $minOrderAmount,
                'max_discount' => $maxDiscount,
                'usage_limit' => $usageLimit,
                'usage_limit_per_user' => $usageLimitPerUser,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'is_active' => $isActive,
            ];

            if ($isEdit) {
                $couponManager->update($id, $data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'کوپن به‌روزرسانی شد'];
            } else {
                $couponManager->create($data);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'کوپن ایجاد شد'];
            }

            header('Location: coupons.php');
            exit;
        }
    }
}

$pageTitle = $isEdit ? 'ویرایش کوپن' : 'افزودن کوپن';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
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
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle' ?>"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h1>
        <a href="coupons.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-info-circle"></i> اطلاعات کوپن</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">کد کوپن <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" required
                                       style="text-transform: uppercase;"
                                       value="<?= htmlspecialchars($_POST['code'] ?? $coupon['code'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع تخفیف <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" id="typeSelect" onchange="toggleMaxDiscount()">
                                    <option value="percentage" <?= (($_POST['type'] ?? $coupon['type'] ?? '') === 'percentage') ? 'selected' : '' ?>>درصدی</option>
                                    <option value="fixed" <?= (($_POST['type'] ?? $coupon['type'] ?? '') === 'fixed') ? 'selected' : '' ?>>مبلغ ثابت</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">مقدار <span class="text-danger">*</span></label>
                                <input type="number" name="value" class="form-control" step="0.01" required
                                       value="<?= htmlspecialchars($_POST['value'] ?? $coupon['value'] ?? '') ?>">
                            </div>
                            <div class="col-md-6" id="maxDiscountField">
                                <label class="form-label">حداکثر تخفیف (تومان)</label>
                                <input type="number" name="max_discount" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['max_discount'] ?? $coupon['max_discount'] ?? '') ?>">
                                <div class="form-text">فقط برای تخفیف درصدی</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">توضیحات</label>
                                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $coupon['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-gear"></i> محدودیت‌ها</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">حداقل مبلغ سفارش (تومان)</label>
                                <input type="number" name="min_order_amount" class="form-control" step="0.01"
                                       value="<?= htmlspecialchars($_POST['min_order_amount'] ?? $coupon['min_order_amount'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">محدودیت کل استفاده</label>
                                <input type="number" name="usage_limit" class="form-control"
                                       value="<?= htmlspecialchars($_POST['usage_limit'] ?? $coupon['usage_limit'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ شروع</label>
                                <input type="datetime-local" name="starts_at" class="form-control"
                                       value="<?= htmlspecialchars($_POST['starts_at'] ?? $coupon['starts_at'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">تاریخ انقضا</label>
                                <input type="datetime-local" name="expires_at" class="form-control"
                                       value="<?= htmlspecialchars($_POST['expires_at'] ?? $coupon['expires_at'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">محدودیت هر کاربر</label>
                                <input type="number" name="usage_limit_per_user" class="form-control" min="1"
                                       value="<?= (int) ($_POST['usage_limit_per_user'] ?? $coupon['usage_limit_per_user'] ?? 1) ?>">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                           <?= (($_POST['is_active'] ?? $coupon['is_active'] ?? 1) ? 'checked' : '') ?>>
                                    <label class="form-check-label" for="is_active">فعال</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> ذخیره
                    </button>
                    <a href="coupons.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> انصراف
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleMaxDiscount() {
    const type = document.getElementById('typeSelect').value;
    const field = document.getElementById('maxDiscountField');
    field.style.display = type === 'percentage' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleMaxDiscount);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * PartoCMS - Shop - Checkout
 * فرم پرداخت سفارش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/includes/ShopManager.php';
require_once __DIR__ . '/includes/CartManager.php';
require_once __DIR__ . '/includes/OrderManager.php';
require_once __DIR__ . '/includes/CouponManager.php';
require_once __DIR__ . '/includes/ShippingManager.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$cart = new CartManager($pdo);
$orderManager = new OrderManager($pdo);

$items = $cart->getItems();
$subtotal = $cart->subtotal();
$itemCount = $cart->count();

// اگر سبد خالی است، به صفحه فروشگاه برگردان
if ($cart->isEmpty()) {
    header('Location: ' . $shop->getUrl('index.php'));
    exit;
}

// محاسبات
$taxRate = (float) $shop->getSetting('tax_rate', '9');
$couponManager = new CouponManager($pdo);
$shippingManager = new ShippingManager($pdo);

// کوپن
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$discount = 0;
if ($appliedCoupon) {
    $recheck = $couponManager->validate($appliedCoupon['code'], $subtotal);
    if ($recheck['ok']) {
        $discount = $recheck['discount'];
    } else {
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
    }
}

// روش‌های ارسال
$shippingMethods = $shippingManager->getAll();
$selectedShippingId = (int) ($_POST['shipping_method_id'] ?? $_SESSION['selected_shipping_id'] ?? 0);
if (!$selectedShippingId && !empty($shippingMethods)) {
    $selectedShippingId = (int) $shippingMethods[0]['id'];
}
$_SESSION['selected_shipping_id'] = $selectedShippingId;

$shipping = $shippingManager->calculateCost($selectedShippingId, $subtotal);
$taxable = max(0, $subtotal - $discount);
$tax = ($taxable * $taxRate) / 100;
$total = $taxable + $tax + $shipping;

$errors = [];
$success = false;
$orderNumber = '';

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cod';
        $notes = trim($_POST['notes'] ?? '');

        if (empty($firstName)) $errors[] = 'نام الزامی است';
        if (empty($lastName)) $errors[] = 'نام خانوادگی الزامی است';
        if (empty($phone)) $errors[] = 'شماره تماس الزامی است';
        if (empty($address)) $errors[] = 'آدرس الزامی است';
        if (empty($city)) $errors[] = 'شهر الزامی است';

        if (empty($errors)) {
            $orderData = [
                'user_id' => $_SESSION['user_id'] ?? null,
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total,
                'coupon_code' => $appliedCoupon['code'] ?? null,
                'discount' => $discount,
                'shipping_method' => (string) $selectedShippingId,
                'shipping_address' => "{$firstName} {$lastName}\n{$phone}\n{$address}\n{$city}، {$state}، {$postalCode}",
                'customer_notes' => $notes,
                'items' => array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'product_price' => $item['price'],
                        'quantity' => $item['quantity'],
                    ];
                }, $items),
            ];

            $result = $orderManager->create($orderData);

            if ($result['ok']) {
                // خالی کردن سبد
                $cart->clear();

                // ثبت استفاده از کوپن
                if ($appliedCoupon) {
                    $couponManager->recordUsage(
                        (int) $appliedCoupon['id'],
                        $_SESSION['user_id'] ?? null,
                        (int) $result['order_id'],
                        $discount
                    );
                    unset($_SESSION['applied_coupon']);
                }

                // ذخیره اطلاعات در session برای صفحه موفقیت
                $_SESSION['last_order'] = [
                    'id' => $result['order_id'],
                    'number' => $result['order_number'],
                ];

                header('Location: ' . $shop->getUrl('order-success.php'));
                exit;
            } else {
                $errors[] = 'خطا در ثبت سفارش: ' . $result['error'];
            }
        }
    }
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'تکمیل خرید | ' . $siteName;
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/">
            <i class="bi bi-shop"></i> <?= htmlspecialchars($siteName) ?>
        </a>
        <div class="ms-auto">
            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-cart"></i> بازگشت به سبد
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-credit-card"></i> تکمیل خرید</h1>

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

        <div class="row g-4">
            <!-- فرم اطلاعات -->
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person"></i> اطلاعات خریدار</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نام <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ایمیل</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">شماره تماس <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-geo-alt"></i> آدرس تحویل</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">آدرس کامل <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">شهر <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required
                                       value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">استان</label>
                                <input type="text" name="state" class="form-control"
                                       value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">کد پستی</label>
                                <input type="text" name="postal_code" class="form-control"
                                       value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-truck"></i> روش ارسال</div>
                    <div class="card-body">
                        <?php if (empty($shippingMethods)): ?>
                            <p class="text-muted mb-0">هیچ روش ارسالی تعریف نشده است</p>
                        <?php else: ?>
                            <?php foreach ($shippingMethods as $sm): ?>
                                <?php
                                $cost = $sm['free_over'] && $subtotal >= $sm['free_over'] ? 0 : (float) $sm['cost'];
                                ?>
                                <div class="form-check mb-2">
                                    <input type="radio" name="shipping_method_id" 
                                           value="<?= (int) $sm['id'] ?>"
                                           class="form-check-input" 
                                           id="ship_<?= (int) $sm['id'] ?>"
                                           onchange="this.form.submit()"
                                           <?= $selectedShippingId == $sm['id'] ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="ship_<?= (int) $sm['id'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($sm['name']) ?></strong>
                                            <span>
                                                <?= $cost == 0 ? '<span class="text-success">رایگان</span>' : $shop->formatPrice($cost) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($sm['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($sm['description']) ?></small>
                                        <?php endif; ?>
                                        <small class="text-muted d-block">زمان تحویل: <?= (int) $sm['min_days'] ?>-<?= (int) $sm['max_days'] ?> روز</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-credit-card"></i> روش پرداخت</div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input type="radio" name="payment_method" value="cod" class="form-check-input" id="cod" checked>
                            <label class="form-check-label" for="cod">
                                <i class="bi bi-cash"></i> پرداخت در محل (COD)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="radio" name="payment_method" value="zarinpal" class="form-check-input" id="zarinpal">
                            <label class="form-check-label" for="zarinpal">
                                <i class="bi bi-credit-card-2-front"></i> زرین‌پال
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="radio" name="payment_method" value="idpay" class="form-check-input" id="idpay">
                            <label class="form-check-label" for="idpay">
                                <i class="bi bi-credit-card-2-front"></i> آیدی‌پی
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-chat"></i> یادداشت</div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="2" placeholder="اگر توضیح خاصی دارید بنویسید..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- خلاصه سفارش -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-receipt"></i> خلاصه سفارش</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <?php foreach ($items as $item): ?>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span><?= htmlspecialchars($item['name']) ?> × <?= $item['quantity'] ?></span>
                                    <span><?= $shop->formatPrice((float) ($item['price'] * $item['quantity'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>جمع کالاها:</span>
                            <span><?= $shop->formatPrice($subtotal) ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>تخفیف (<?= htmlspecialchars($appliedCoupon['code']) ?>):</span>
                            <span>− <?= $shop->formatPrice($discount) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>مالیات:</span>
                            <span><?= $shop->formatPrice($tax) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>ارسال:</span>
                            <span><?= $shipping == 0 ? 'رایگان' : $shop->formatPrice($shipping) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>جمع کل:</strong>
                            <strong class="text-primary fs-5"><?= $shop->formatPrice($total) ?></strong>
                        </div>
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-check-circle"></i> ثبت سفارش
                        </button>
                        <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-arrow-right"></i> بازگشت به سبد
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

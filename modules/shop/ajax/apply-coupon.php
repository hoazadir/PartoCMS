<?php
/**
 * PartoCMS - Shop - AJAX Apply Coupon
 * اعمال کوپن تخفیف
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/CartManager.php';
require_once __DIR__ . '/../includes/CouponManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'روش نامعتبر']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (empty($code)) {
    echo json_encode(['ok' => false, 'error' => 'کد تخفیف را وارد کنید']);
    exit;
}

try {
    $pdo = getDB();
    $shop = new ShopManager($pdo);
    $cart = new CartManager($pdo);
    $couponManager = new CouponManager($pdo);

    $subtotal = $cart->subtotal();

    if ($subtotal <= 0) {
        echo json_encode(['ok' => false, 'error' => 'سبد خرید خالی است']);
        exit;
    }

    $result = $couponManager->validate($code, $subtotal);

    if (!$result['ok']) {
        echo json_encode($result);
        exit;
    }

    // ذخیره کوپن در session
    $_SESSION['applied_coupon'] = [
        'id' => $result['coupon']['id'],
        'code' => $result['code'],
        'type' => $result['coupon']['type'],
        'value' => $result['coupon']['value'],
        'discount' => $result['discount'],
    ];

    // محاسبه مجدد
    $taxRate = (float) $shop->getSetting('tax_rate', '9');
    $discount = $result['discount'];
    $taxable = max(0, $subtotal - $discount);
    $tax = ($taxable * $taxRate) / 100;

    echo json_encode([
        'ok' => true,
        'message' => 'کد تخفیف با موفقیت اعمال شد',
        'code' => $result['code'],
        'discount' => $discount,
        'discount_formatted' => $shop->formatPrice($discount),
        'subtotal' => $subtotal,
        'subtotal_formatted' => $shop->formatPrice($subtotal),
        'tax' => $tax,
        'tax_formatted' => $shop->formatPrice($tax),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور: ' . $e->getMessage()]);
}

<?php
/**
 * PartoCMS - Shop - Cart Page
 * نمایش سبد خرید
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/includes/ShopManager.php';
require_once __DIR__ . '/includes/CartManager.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$cart = new CartManager($pdo);

$items = $cart->getItems();
$subtotal = $cart->subtotal();
$itemCount = $cart->count();

// محاسبه مالیات و ارسال
$taxRate = (float) $shop->getSetting('tax_rate', '9');
$shippingCost = (float) $shop->getSetting('shipping_cost', '50000');
$freeShippingOver = (float) $shop->getSetting('free_shipping_over', '1000000');

$tax = ($subtotal * $taxRate) / 100;
$shipping = ($subtotal >= $freeShippingOver) ? 0 : $shippingCost;
$total = $subtotal + $tax + $shipping;

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'سبد خرید | ' . $siteName;
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
        .cart-item-img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= SITE_URL ?>/">
            <i class="bi bi-shop"></i> <?= htmlspecialchars($siteName) ?>
        </a>
        <div class="ms-auto">
            <a href="<?= SITE_URL ?>/" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house"></i> خانه
            </a>
            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-outline-light btn-sm">
                <i class="bi bi-shop-window"></i> فروشگاه
            </a>
            <a href="<?= $shop->getUrl('cart.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-cart"></i> سبد (<span id="cartCount"><?= $itemCount ?></span>)
            </a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h1 class="mb-4"><i class="bi bi-cart3"></i> سبد خرید</h1>

    <div id="alertBox"></div>

    <?php if (empty($items)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-cart-x fs-1"></i>
            <h4 class="mt-3">سبد خرید شما خالی است</h4>
            <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-primary mt-3">
                <i class="bi bi-shop-window"></i> مشاهده محصولات
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- آیتم‌های سبد -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>محصول</th>
                                    <th>قیمت</th>
                                    <th>تعداد</th>
                                    <th>جمع</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr id="cart-item-<?= $item['id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($item['image'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image']) ?>" class="cart-item-img" alt="">
                                                <?php else: ?>
                                                    <div class="cart-item-img bg-light d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="<?= $shop->getUrl('product.php?id=' . $item['product_id']) ?>"
                                                       class="text-decoration-none fw-bold">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </a>
                                                    <?php if ($item['stock'] < 1): ?>
                                                        <div class="text-danger small">ناموجود</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= $shop->formatPrice((float) $item['price']) ?></td>
                                        <td>
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <button class="btn btn-outline-secondary" onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)">−</button>
                                                <input type="text" class="form-control text-center" value="<?= $item['quantity'] ?>" readonly>
                                                <button class="btn btn-outline-secondary" onclick="updateQuantity(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)">+</button>
                                            </div>
                                        </td>
                                        <td class="fw-bold">
                                            <?= $shop->formatPrice((float) ($item['price'] * $item['quantity'])) ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="removeItem(<?= $item['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?= $shop->getUrl('index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> ادامه خرید
                    </a>
                </div>
            </div>

            <!-- خلاصه سبد -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-receipt"></i> خلاصه سفارش</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>جمع کالاها:</span>
                            <span><?= $shop->formatPrice($subtotal) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>مالیات (<?= $taxRate ?>%):</span>
                            <span><?= $shop->formatPrice($tax) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>هزینه ارسال:</span>
                            <span>
                                <?php if ($shipping == 0): ?>
                                    <span class="text-success">رایگان</span>
                                <?php else: ?>
                                    <?= $shop->formatPrice($shipping) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($subtotal < $freeShippingOver): ?>
                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle"></i>
                                برای ارسال رایگان، <?= $shop->formatPrice($freeShippingOver - $subtotal) ?> دیگر خرید کنید.
                            </div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>جمع کل:</strong>
                            <strong class="text-primary fs-5"><?= $shop->formatPrice($total) ?></strong>
                        </div>
                        <a href="<?= $shop->getUrl('checkout.php') ?>" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-credit-card"></i> ادامه و پرداخت
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></p>
    </div>
</footer>

<script>
const BASE_URL = '<?= $shop->getUrl() ?>';

function showAlert(type, message) {
    const box = document.getElementById('alertBox');
    box.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' +
        message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    setTimeout(() => box.innerHTML = '', 3000);
}

function updateQuantity(itemId, quantity) {
    if (quantity < 1) {
        removeItem(itemId);
        return;
    }
    fetch(BASE_URL + '/ajax/cart-update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({item_id: itemId, quantity: quantity})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            showAlert('danger', data.error || 'خطا در به‌روزرسانی');
        }
    })
    .catch(() => showAlert('danger', 'خطای شبکه'));
}

function removeItem(itemId) {
    if (!confirm('این محصول از سبد حذف شود؟')) return;
    fetch(BASE_URL + '/ajax/cart-remove.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({item_id: itemId})
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            document.getElementById('cart-item-' + itemId).remove();
            document.getElementById('cartCount').textContent = data.count;
            showAlert('success', 'محصول از سبد حذف شد');
            if (data.count === 0) location.reload();
        } else {
            showAlert('danger', data.error || 'خطا در حذف');
        }
    })
    .catch(() => showAlert('danger', 'خطای شبکه'));
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

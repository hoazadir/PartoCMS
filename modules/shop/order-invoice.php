<?php
/**
 * PartoCMS - Shop - Order Invoice (Customer)
 * دانلود فاکتور PDF سفارش (مشتری)
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/includes/ShopManager.php';
require_once __DIR__ . '/includes/OrderManager.php';
require_once __DIR__ . '/includes/InvoiceGenerator.php';

$pdo = getDB();
$shop = new ShopManager($pdo);
$orderManager = new OrderManager($pdo);

$id = (int) ($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'download'; // download | preview

if ($id < 1) {
    http_response_code(400);
    die('شناسه سفارش نامعتبر است');
}

$order = $orderManager->getById($id);
if (!$order) {
    http_response_code(404);
    die('سفارش یافت نشد');
}

// ═══ بررسی دسترسی ═══
$hasAccess = false;

// ۱. ادمین → دسترسی آزاد
if (isLoggedIn() && isAdmin()) {
    $hasAccess = true;
}

// ۲. صاحب سفارش → مقایسه user_id
if (!$hasAccess && isLoggedIn()) {
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $orderUserId = (int) ($order['user_id'] ?? 0);
    if ($currentUserId > 0 && $orderUserId === $currentUserId) {
        $hasAccess = true;
    }
}

// ۳. مهمان با session last_order → فقط همان سفارش
if (!$hasAccess) {
    $lastOrder = $_SESSION['last_order'] ?? null;
    if ($lastOrder && (int) $lastOrder['id'] === $id) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    die('شما به این فاکتور دسترسی ندارید');
}

// ═══ تولید فاکتور ═══
try {
    $generator = new InvoiceGenerator($pdo);
    $generator->download($id, $mode === 'preview');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    die('خطا در تولید فاکتور: ' . $e->getMessage());
}

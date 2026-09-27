<?php
/**
 * PartoCMS - Shop Admin - Order Invoice
 * دانلود فاکتور PDF سفارش (ادمین)
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/OrderManager.php';
require_once __DIR__ . '/../includes/InvoiceGenerator.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
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

try {
    $generator = new InvoiceGenerator($pdo);
    $generator->download($id, $mode === 'preview');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    die('خطا در تولید فاکتور: ' . $e->getMessage());
}

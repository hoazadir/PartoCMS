<?php
/**
 * PartoCMS - Shop - AJAX Cart Add
 * افزودن محصول به سبد خرید
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/CartManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'روش نامعتبر']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = (int) ($input['product_id'] ?? 0);
$quantity = max(1, (int) ($input['quantity'] ?? 1));

if ($productId < 1) {
    echo json_encode(['ok' => false, 'error' => 'شناسه محصول نامعتبر']);
    exit;
}

try {
    $pdo = getDB();
    $cart = new CartManager($pdo);
    $result = $cart->add($productId, $quantity);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور: ' . $e->getMessage()]);
}

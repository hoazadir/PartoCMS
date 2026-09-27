<?php
/**
 * PartoCMS - Shop - AJAX Remove Coupon
 * حذف کوپن اعمال‌شده
 */

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

unset($_SESSION['applied_coupon']);
echo json_encode(['ok' => true, 'message' => 'کوپن حذف شد']);

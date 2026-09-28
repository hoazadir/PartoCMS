<?php
/**
 * PartoCMS - Ads - Track Impression v2
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/AdTracker.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$adId = (int) ($_GET['id'] ?? 0);

if ($adId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_id']);
    exit;
}

try {
    $pdo = getDB();
    $tracker = new AdTracker($pdo);
    $ok = $tracker->trackImpression($adId);
    echo json_encode(['ok' => $ok]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}

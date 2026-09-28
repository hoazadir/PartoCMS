<?php
/**
 * PartoCMS - Ads - Track Click v2
 * ردیابی + ریدایرکت
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/AdTracker.php';

$adId = (int) ($_GET['id'] ?? 0);
$to = $_GET['to'] ?? '';

if ($adId < 1) {
    http_response_code(400);
    die('شناسه تبلیغ نامعتبر');
}

try {
    $pdo = getDB();
    $tracker = new AdTracker($pdo);
    $tracker->trackClick($adId);

    // ریدایرکت
    $target = '';
    if (!empty($to)) {
        $target = urldecode($to);
    } else {
        $stmt = $pdo->prepare("SELECT target_url FROM ads WHERE id = ? LIMIT 1");
        $stmt->execute([$adId]);
        $target = (string) $stmt->fetchColumn();
    }

    // امنیت — بررسی URL
    if (empty($target)) {
        header('Location: ' . SITE_URL);
        exit;
    }

    if (!preg_match('#^https?://#i', $target)) {
        $target = 'http://' . $target;
    }

    header('Location: ' . $target);
    exit;

} catch (Throwable $e) {
    header('Location: ' . SITE_URL);
    exit;
}

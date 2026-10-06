<?php
/**
 * 🆕 PartoCMS - Reviews - AJAX Vote
 * رأی مفید/غیرمفید
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/ReviewManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'فقط POST مجاز است']);
    exit;
}

try {
    $pdo = getDB();
    $manager = new ReviewManager($pdo);

    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $type     = trim($_POST['vote_type'] ?? '');

    if ($reviewId < 1) {
        echo json_encode(['ok' => false, 'error' => 'شناسه نظر نامعتبر']);
        exit;
    }

    $result = $manager->vote(
        $reviewId,
        $type,
        isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    );

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Review vote error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
}

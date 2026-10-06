<?php
/**
 * 🆕 PartoCMS - Reviews - AJAX Submit
 * ثبت نظر جدید
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

    // ─── دریافت داده ───
    $data = [
        'entity_type'  => trim($_POST['entity_type']  ?? ''),
        'entity_id'    => (int) ($_POST['entity_id']  ?? 0),
        'author_name'  => trim($_POST['author_name']  ?? ''),
        'author_email' => trim($_POST['author_email'] ?? ''),
        'content'      => trim($_POST['content']      ?? ''),
        'title'        => trim($_POST['title']        ?? ''),
        'rating'       => !empty($_POST['rating']) ? (int) $_POST['rating'] : null,
        'parent_id'    => !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null,
        'author_ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_id'      => isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null,
    ];

    // ─── بررسی فعال بودن ───
    $settings = $manager->getSettings($data['entity_type']);
    if (empty($settings['enabled'])) {
        echo json_encode(['ok' => false, 'error' => 'سیستم نظرات غیرفعال است']);
        exit;
    }

    // ─── بررسی مهمان ───
    if (empty($settings['allow_guest']) && !isLoggedIn()) {
        echo json_encode(['ok' => false, 'error' => 'برای ثبت نظر باید وارد شوید']);
        exit;
    }

    // ─── Rate Limiting ساده ───
    $ip = $data['author_ip'];
    $recentCount = $pdo->prepare("
        SELECT COUNT(*) FROM reviews
        WHERE author_ip = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $recentCount->execute([$ip]);
    if ((int) $recentCount->fetchColumn() > 3) {
        echo json_encode(['ok' => false, 'error' => 'تعداد نظرات شما زیاد است. لطفاً کمی صبر کنید']);
        exit;
    }

    // ─── ثبت ───
    $result = $manager->create($data);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Review submit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
}

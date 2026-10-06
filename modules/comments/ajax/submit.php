<?php
/**
 * PartoCMS - Comments - AJAX Submit
 * ثبت دیدگاه جدید
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../includes/CommentManager.php';

header('Content-Type: application/json; charset=utf-8');

// فقط POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'فقط POST مجاز است']);
    exit;
}

// تشخیص AJAX — اگر AJAX نبود، redirect به post
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    $pdo = getDB();
    $manager = new CommentManager($pdo);

    // دریافت داده
    $data = [
        'content_id'     => (int) ($_POST['content_id'] ?? 0),
        'parent_id'      => !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null,
        'author_name'    => trim($_POST['author_name'] ?? ''),
        'author_email'   => trim($_POST['author_email'] ?? ''),
        'author_website' => trim($_POST['author_website'] ?? ''),
        'comment'        => trim($_POST['comment'] ?? ''),
        'author_ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_id'        => isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null,
    ];

    // CSRF (اگر تابع موجود است)
    if (function_exists('csrf_verify')) {
        $token = $_POST['csrf_token'] ?? '';
        if (!csrf_verify($token)) {
            echo json_encode(['ok' => false, 'error' => 'توکن امنیتی نامعتبر']);
            exit;
        }
    }

    // ثبت
    $result = $manager->create($data);

    if ($result['ok']) {
        echo json_encode([
            'ok'      => true,
            'id'      => $result['id'],
            'message' => '✅ دیدگاه شما ثبت شد و در انتظار تأیید است',
        ]);
    } else {
        echo json_encode([
            'ok'    => false,
            'error' => $result['error'] ?? 'خطای ناشناخته',
        ]);
    }

} catch (Throwable $e) {
    error_log('Comments submit error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
}

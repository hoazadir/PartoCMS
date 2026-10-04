<?php
/**
 * 🆕 PartoCMS - AJAX: AI Generate Ad Copy
 * Endpoint برای AdAiAssistant
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/AdAiAssistant.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'فقط POST مجاز است']);
    exit;
}

$ai = new AdAiAssistant();
if (!$ai->isAvailable()) {
    echo json_encode([
        'ok'    => false,
        'error' => 'دستیار هوشمند غیرفعال است. از بخش «هوش مصنوعی» آن را فعال کنید.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? 'variants';

try {
    switch ($action) {
        case 'generate':
            $result = $ai->generateCopy([
                'product'  => trim($_POST['product']  ?? ''),
                'audience' => trim($_POST['audience'] ?? ''),
                'tone'     => $_POST['tone']     ?? 'professional',
                'cta'      => trim($_POST['cta']      ?? ''),
                'language' => $_POST['language'] ?? null,
            ]);
            break;

        case 'variants':
            $count = max(2, min(5, (int) ($_POST['count'] ?? 3)));
            $result = $ai->generateVariants([
                'product'  => trim($_POST['product']  ?? ''),
                'audience' => trim($_POST['audience'] ?? ''),
                'tone'     => $_POST['tone']     ?? 'professional',
                'language' => $_POST['language'] ?? null,
            ], $count);
            break;

        case 'improve':
            $result = $ai->improveCopy(
                trim($_POST['title'] ?? ''),
                trim($_POST['description'] ?? ''),
                $_POST['language'] ?? null
            );
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'action نامعتبر'], JSON_UNESCAPED_UNICODE);
            exit;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

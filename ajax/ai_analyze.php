<?php
/**
 * PartoCMS - AI Error Analyzer Endpoint
 * دریافت خطا و برگرداندن تحلیل هوشمند
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/auth_check.php';
require_once __DIR__ . '/../includes/AiAssistant.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی دسترسی
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

// بررسی فعال بودن AI
$ai = new AiAssistant();
if (!$ai->isEnabled()) {
    echo json_encode(['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است. ابتدا از تنظیمات فعال کنید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// دریافت داده
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['error'])) {
    echo json_encode(['ok' => false, 'error' => 'خطایی برای تحلیل ارسال نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$error = trim($input['error']);
$context = [
    'table' => $input['table'] ?? '',
    'columns' => $input['columns'] ?? [],
];

// تحلیل
try {
    $result = $ai->analyzeError($error, $context);

    if (empty($result['ok'])) {
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? 'خطای نامشخص',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // نتیجه
    $analysis = $result['analysis'] ?? [];
    echo json_encode([
        'ok' => true,
        'analysis' => [
            'summary' => $analysis['summary'] ?? 'تحلیل انجام شد',
            'cause' => $analysis['cause'] ?? '',
            'solution' => $analysis['solution'] ?? '',
            'field' => $analysis['field'] ?? '',
            'severity' => $analysis['severity'] ?? 'medium',
        ],
        'model' => $ai->getModel(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * PartoCMS - AI STT Pull Start Endpoint
 * 
 * مسئولیت: دریافت درخواست دانلود → ساخت queue
 * 
 * @version 1.0
 * @date 2026-09-21
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(30);

header('Content-Type: application/json; charset=utf-8');

// ═══════════════════════════════════════════════════════════
//  ۱. بررسی دسترسی
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/auth_check.php';
require_once __DIR__ . '/../includes/AI/AIProviderFactory.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  ۲. بررسی متد
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'متد نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  ۳. اعتبارسنجی ورودی
// ═══════════════════════════════════════════════════════════
$modelName = trim($_POST['model'] ?? '');

if (empty($modelName)) {
    echo json_encode(['ok' => false, 'error' => 'نام مدل الزامی است'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $modelName)) {
    echo json_encode(['ok' => false, 'error' => 'نام مدل نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  ۴. ساخت Provider و اجرا
// ═══════════════════════════════════════════════════════════
try {
    $providerSlug = getSetting('ai_stt_provider', 'whisper_cpp');
    $stt = AIProviderFactory::makeSTT($providerSlug);

    if (!$stt) {
        echo json_encode([
            'ok' => false,
            'error' => "Provider «{$providerSlug}» یافت نشد",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = $stt->pullModel($modelName);

    if (empty($result['ok'])) {
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? 'خطای ناشناخته',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => $result['message'] ?? 'دانلود شروع شد',
        'sources_count' => $result['sources_count'] ?? 0,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[AI STT Pull Start] ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور',
    ], JSON_UNESCAPED_UNICODE);
}

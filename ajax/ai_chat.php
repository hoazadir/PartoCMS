<?php
/**
 * PartoCMS - AI Chat Endpoint
 * ارسال پیام، دریافت پاسخ، ذخیره تاریخچه
 */
// تنظیم زمان اجرا برای AI (مدل‌های سنگین)
ini_set('max_execution_time', 300);
ini_set('default_socket_timeout', 300);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/auth_check.php';
require_once __DIR__ . '/../includes/AiAssistant.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(300); // 5 دقیقه برای پاسخ AI

// بررسی دسترسی
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = $userRole === 'admin';

if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'کاربر نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ai = new AiAssistant();

if (!$ai->isEnabled() && !$isAdmin) {
    echo json_encode(['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ═══════════════════════════════════════════════════════════
    // ۱. لیست مکالمات
    // ═══════════════════════════════════════════════════════════
    if (isset($_GET['list'])) {
        $conversations = $ai->listConversations($userId);
        echo json_encode(['ok' => true, 'conversations' => $conversations], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // ۲. حذف مکالمه
    // ═══════════════════════════════════════════════════════════
    if (isset($_GET['delete'])) {
        $convId = (int)$_GET['delete'];
        $ok = $ai->deleteConversation($convId, $userId);
        echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // ۳. ارسال پیام
    // ═══════════════════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'متد نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $userMessage = trim($input['message'] ?? '');
    $convId = (int)($input['conversation_id'] ?? 0);

    if (empty($userMessage)) {
        echo json_encode(['ok' => false, 'error' => 'پیام خالی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($userMessage) > 5000) {
        echo json_encode(['ok' => false, 'error' => 'پیام طولانی است (حداکثر ۵۰۰۰ کاراکتر)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // اگر مکالمه جدید است، بساز
    if (!$convId) {
        $title = mb_substr($userMessage, 0, 40);
        if (mb_strlen($userMessage) > 40) $title .= '...';
        $convId = $ai->createConversation($userId, $title);
        if (!$convId) {
            echo json_encode(['ok' => false, 'error' => 'خطا در ساخت مکالمه'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // ذخیره پیام کاربر
    $ai->saveMessage($convId, 'user', $userMessage);

    // خواندن تاریخچه برای context
    $history = $ai->getMessages($convId, $userId);

    // محدود کردن به ۱۰ پیام آخر (برای سرعت)
    $recent = array_slice($history, -10);
    $messages = array_map(fn($m) => [
        'role' => $m['role'],
        'content' => $m['content'],
    ], $recent);

    // ارسال به AI
    $result = $ai->chat($messages, [
        'temperature' => 0.7,
        'num_predict' => 1000,
    ]);

    if (!$result['ok']) {
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? 'خطا در دریافت پاسخ',
            'conversation_id' => $convId,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response = $result['response'];

    // ذخیره پاسخ AI
    $ai->saveMessage($convId, 'assistant', $response);

    echo json_encode([
        'ok' => true,
        'response' => $response,
        'conversation_id' => $convId,
        'model' => $result['model'] ?? $ai->getModel(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

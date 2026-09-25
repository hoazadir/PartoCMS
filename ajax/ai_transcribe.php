<?php
/**
 * PartoCMS - AI Transcribe Endpoint (Modular Final)
 * تبدیل صدا به متن با انتخاب Provider/Model توسط مدیر یا کاربر
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('max_execution_time', 300);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/auth_check.php';
require_once __DIR__ . '/../includes/AI/AIProviderFactory.php';

// ═══════════════════════════════════════════════════════════
// ۱. بررسی دسترسی
// ═══════════════════════════════════════════════════════════
if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// ۲. بررسی فایل ورودی
// ═══════════════════════════════════════════════════════════
if (empty($_FILES['audio'])) {
    echo json_encode(['ok' => false, 'error' => 'فایل صوتی ارسال نشد'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['audio'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'خطا در آپلود: ' . $file['error']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($file['size'] > 25 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'حجم فایل بیش از ۲۵MB است'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// ۳. ذخیره فایل موقت
// ═══════════════════════════════════════════════════════════
$uploadDir = __DIR__ . '/../uploads/ai_audio';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'webm');
if (!in_array($ext, ['webm', 'ogg', 'mp3', 'mp4', 'wav', 'm4a', 'aac', 'flac'])) {
    $ext = 'webm';
}

$tempFile = $uploadDir . '/rec_' . uniqid() . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره فایل'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
// ۴. تعیین Provider و Model
// ═══════════════════════════════════════════════════════════
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$userModelChoice = getSetting('ai_user_model_choice', '0') === '1';

$providerSlug = getSetting('ai_stt_provider', 'whisper_cpp');
$model        = getSetting('ai_stt_model', 'base');
$language     = getSetting('ai_stt_language', 'auto');

// اگر کاربر مجاز باشد، از درخواست بخوان
if ($isAdmin || $userModelChoice) {
    if (!empty($_POST['provider']) && preg_match('/^[a-z_]+$/', $_POST['provider'])) {
        $providerSlug = $_POST['provider'];
    }
    if (!empty($_POST['model']) && preg_match('/^[a-zA-Z0-9._-]+$/', $_POST['model'])) {
        $model = $_POST['model'];
    }
    if (!empty($_POST['language']) && preg_match('/^[a-z]{2,3}$|^auto$/i', $_POST['language'])) {
        $language = $_POST['language'];
    }
}

// ═══════════════════════════════════════════════════════════
// ۵. ساخت Provider و اجرا
// ═══════════════════════════════════════════════════════════
$stt = AIProviderFactory::makeSTT($providerSlug);
if (!$stt) {
    @unlink($tempFile);
    echo json_encode([
        'ok' => false,
        'error' => "STT Provider «{$providerSlug}» یافت نشد یا نصب نیست",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = $stt->transcribe($tempFile, [
        'model' => $model,
        'language' => $language,
    ]);

    @unlink($tempFile);

    if (empty($result['ok'])) {
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? 'خطای ناشناخته',
            'provider' => $stt->getSlug(),
            'model' => $model,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'text' => $result['text'],
        'language' => $result['language'] ?? $language,
        'model' => $result['model'] ?? $model,
        'provider' => $stt->getSlug(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    @unlink($tempFile);
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

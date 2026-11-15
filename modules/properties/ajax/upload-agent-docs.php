<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * اطلاع‌رسانی آلودگی به ادمین (تلگرام + لاگ امنیتی)
 */
function notify_admin_infected_upload(string $field, string $error, int $userId = 0): void {
    try {
        $pdo = getDB();
        $tgToken  = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
        $tgChatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
        if (!empty($tgToken) && !empty($tgChatId) && file_exists(__DIR__ . '/../../../admin/includes/telegram_notifier.php')) {
            require_once __DIR__ . '/../../../admin/includes/telegram_notifier.php';
            if (class_exists('TelegramNotifier')) {
                $tg = new TelegramNotifier($tgToken, $tgChatId);
                $tg->sendSecurityAlert(
                    'infected_upload',
                    'فایل آلوده در آپلود مدارک مشاور',
                    [
                        'field'   => $field,
                        'error'   => $error,
                        'user_id' => $userId ?: ($_SESSION['user_id'] ?? 0),
                        'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ],
                    'danger'
                );
            }
        }
        $stmt = $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute(['infected_upload', "[$field] $error", $_SERVER['REMOTE_ADDR'] ?? 'unknown', $userId ?: ($_SESSION['user_id'] ?? 0)]);
    } catch (Throwable $e) {
        error_log('notify_admin_infected_upload: ' . $e->getMessage());
    }
}

/**
 * اطلاع‌رسانی آلودگی به ادمین (تلگرام + لاگ امنیتی)
 */

// CSRF
$token = $_POST['_csrf'] ?? '';
if (!hash_equals($_SESSION['agent_csrf'] ?? '', $token) || empty($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر']);
    exit;
}

$docType = $_POST['doc_type'] ?? '';
$allowed = ['avatar', 'national_card_front', 'national_card_back', 'license', 'manager_photo', 'contract', 'social_security'];
if (!in_array($docType, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'نوع مدرک نامعتبر']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فایلی ارسال نشد']);
    exit;
}

$maxSize = 3 * 1024 * 1024;
if ($_FILES['file']['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حجم فایل بیش از ۳ مگابایت']);
    exit;
}

$mimeAllowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['file']['tmp_name']);
if (!isset($mimeAllowed[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فرمت مجاز نیست (JPG/PNG/WebP)']);
    exit;
}

$ext = $mimeAllowed[$mime];
$uploadDir = dirname(__DIR__, 3) . '/assets/uploads/agents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = $docType . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$target   = $uploadDir . $filename;

// ─── اسکن آنتی‌ویروس با ClamAV ───
$clamscan = trim((string)shell_exec('which clamscan 2>/dev/null'));
if ($clamscan === '') {
    // اگر ClamAV نصب نیست، از VirusTotal استفاده کن (در صورت وجود)
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'سیستم آنتی‌ویروس در دسترس نیست. لطفاً بعداً تلاش کنید.']);
    exit;
}

$scanResult = shell_exec(escapeshellarg($clamscan) . ' --no-summary ' . escapeshellarg($_FILES['file']['tmp_name']) . ' 2>&1');
$exitCode = (int)shell_exec('echo $?'); // این روش دقیق نیست، بهتر است از exec استفاده کنیم

// روش دقیق‌تر با exec
$output = [];
$returnCode = 0;
exec(escapeshellarg($clamscan) . ' --no-summary ' . escapeshellarg($_FILES['file']['tmp_name']) . ' 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    // 0 = clean, 1 = infected, 2 = error
    $scanText = implode(' ', $output);
    if ($returnCode === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فایل آلوده به ویروس/تروجان است و آپلود نشد.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اسکن آنتی‌ویروس. لطفاً دوباره تلاش کنید.']);
    }
    exit;
}

// ─── اسکن آنتی‌ویروس با ClamAV (لایه اول) ───
$clamscan = trim((string)shell_exec('which clamscan 2>/dev/null'));
if ($clamscan !== '') {
    $output = [];
    $returnCode = 0;
    exec(escapeshellarg($clamscan) . ' --no-summary ' . escapeshellarg($_FILES['file']['tmp_name']) . ' 2>&1', $output, $returnCode);

    if ($returnCode === 1) {
        http_response_code(400);
        notify_admin_infected_upload($docType, 'ClamAV detected virus');
        echo json_encode(['success' => false, 'message' => '⚠️ فایل آلوده به ویروس است (ClamAV). لطفاً تصویر دیگری انتخاب کنید.']);
        exit;
    } elseif ($returnCode !== 0) {
        error_log('ClamAV scan error: ' . implode(' ', $output));
    }
}

// ─── اسکن آنتی‌ویروس با VirusTotal (لایه دوم — اختیاری) ───
$vtApiKey = function_exists('getSetting') ? getSetting('virustotal_api_key', '') : '';
if (!empty($vtApiKey) && file_exists(dirname(__DIR__, 2) . '/admin/includes/virustotal_scanner.php')) {
        if (class_exists('VirusTotalScanner')) {
        try {
            $vtScanner = new VirusTotalScanner($vtApiKey);
            $scanResult = $vtScanner->checkByHash($_FILES['file']['tmp_name']);
            if (empty($scanResult['ok']) && ($scanResult['error'] ?? '') === 'unknown') {
                $scanResult = $vtScanner->scanFile($_FILES['file']['tmp_name']);
            }
            if (!empty($scanResult['ok']) && isset($scanResult['safe']) && $scanResult['safe'] === false) {
                http_response_code(400);
                notify_admin_infected_upload($docType, 'VirusTotal detected virus');
                echo json_encode(['success' => false, 'message' => '⚠️ فایل آلوده است (VirusTotal). لطفاً تصویر دیگری انتخاب کنید.']);
                exit;
            }
        } catch (Throwable $e) {
            error_log('VirusTotal scan error: ' . $e->getMessage());
            // خطا در VirusTotal مانع آپلود نمی‌شود (ClamAV کافی است)
        }
    }
}

// ─── اسکن VirusTotal (لایه دوم — اختیاری) ───
$vtApiKey = function_exists('getSetting') ? getSetting('virustotal_api_key', '') : '';
if (!empty($vtApiKey) && file_exists(dirname(__DIR__, 3) . '/admin/includes/virustotal_scanner.php')) {
    require_once dirname(__DIR__, 3) . '/admin/includes/virustotal_scanner.php';
    if (class_exists('VirusTotalScanner')) {
        try {
            $vtScanner = new VirusTotalScanner($vtApiKey);
            $scanResult = $vtScanner->checkByHash($_FILES['file']['tmp_name']);
            if (empty($scanResult['ok']) && ($scanResult['error'] ?? '') === 'unknown') {
                $scanResult = $vtScanner->scanFile($_FILES['file']['tmp_name']);
            }
            if (!empty($scanResult['ok']) && isset($scanResult['safe']) && $scanResult['safe'] === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => '⚠️ فایل آلوده است (VirusTotal). لطفاً تصویر دیگری انتخاب کنید.']);
                exit;
            }
        } catch (Throwable $e) {
            error_log('VirusTotal scan error: ' . $e->getMessage());
        }
    }
}

// ─── بازپردازش تصویر (Re-encode) برای حذف کد مخرب جاسازی‌شده ───
$image = @imagecreatefromstring(file_get_contents($_FILES['file']['tmp_name']));
if ($image === false) {
    notify_admin_infected_upload($docType, 'تصویر معتبر نیست (re-encode failed)');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '⚠️ فایل یک تصویر معتبر نیست. لطفاً تصویر دیگری انتخاب کنید.']);
    exit;
}
$filename = $docType . '_' . bin2hex(random_bytes(8)) . '.jpg';
$target = $uploadDir . $filename;
imagejpeg($image, $target, 85);
imagedestroy($image);
@unlink($_FILES['file']['tmp_name']);

$url = '/assets/uploads/agents/' . $filename;
$_SESSION['agent_docs'][$docType] = $url;

echo json_encode(['success' => true, 'url' => $url, 'doc_type' => $docType], JSON_UNESCAPED_UNICODE);

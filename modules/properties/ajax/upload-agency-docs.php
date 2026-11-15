<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF
$token = $_POST['_csrf'] ?? '';
if (!hash_equals($_SESSION['agency_csrf'] ?? '', $token) || empty($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر']);
    exit;
}

$docType = $_POST['doc_type'] ?? '';
$allowed = ['license', 'owner_id_front', 'owner_id_back', 'deed'];
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

// ─── ClamAV ───
$clamscan = trim((string)shell_exec('which clamscan 2>/dev/null'));
if ($clamscan !== '') {
    $output = [];
    $returnCode = 0;
    exec(escapeshellarg($clamscan) . ' --no-summary ' . escapeshellarg($_FILES['file']['tmp_name']) . ' 2>&1', $output, $returnCode);
    if ($returnCode === 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '⚠️ فایل آلوده است. لطفاً تصویر دیگری انتخاب کنید.']);
        exit;
    }
}

// ─── VirusTotal ───
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

// ─── بازپردازش تصویر ───
$image = @imagecreatefromstring(file_get_contents($_FILES['file']['tmp_name']));
if ($image === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '⚠️ فایل یک تصویر معتبر نیست.']);
    exit;
}
$ext = 'jpg';
$uploadDir = dirname(__DIR__, 3) . '/assets/uploads/agencies/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$filename = $docType . '_' . bin2hex(random_bytes(8)) . '.jpg';
$target   = $uploadDir . $filename;
imagejpeg($image, $target, 85);
imagedestroy($image);
@unlink($_FILES['file']['tmp_name']);

$url = '/assets/uploads/agencies/' . $filename;
$_SESSION['agency_docs'][$docType] = $url;

echo json_encode(['success' => true, 'url' => $url, 'doc_type' => $docType], JSON_UNESCAPED_UNICODE);

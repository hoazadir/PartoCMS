<?php
/**
 * PartoCMS — Properties Public Upload
 * آپلود امن تصویر برای کاربران غیرادمین (با محدودیت)
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF
$token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals($_SESSION['properties_public_csrf'] ?? '', $token) || empty($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر']);
    exit;
}

// Rate limit ساده: حداکثر ۲۰ آپلود در ۱۰ دقیقه
$now = time();
$bucket = 'props_upload_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$_SESSION[$bucket] = $_SESSION[$bucket] ?? [];
$_SESSION[$bucket] = array_filter($_SESSION[$bucket], fn($t) => $t > $now - 600);
if (count($_SESSION[$bucket]) >= 20) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'تعداد آپلود بیش از حد مجاز']);
    exit;
}

// بررسی فایل
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فایلی ارسال نشد']);
    exit;
}

$maxSize = 3 * 1024 * 1024; // 3MB برای عموم
if ($_FILES['file']['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حجم فایل بیش از ۳ مگابایت']);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['file']['tmp_name']);
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فرمت مجاز نیست (فقط JPG/PNG/WebP)']);
    exit;
}

$ext = $allowed[$mime];
$yearMonth = date('Y/m');
$uploadDir = dirname(__DIR__) . '/assets/uploads/properties/' . $yearMonth . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ساخت پوشه آپلود']);
    exit;
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$target   = $uploadDir . $filename;

if (!is_uploaded_file($_FILES['file']['tmp_name']) || !move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل']);
    exit;
}

$_SESSION[$bucket][] = $now;

$url = '/modules/properties/assets/uploads/properties/' . $yearMonth . '/' . $filename;
echo json_encode([
    'success'  => true,
    'url'      => $url,
    'filename' => $filename,
]);

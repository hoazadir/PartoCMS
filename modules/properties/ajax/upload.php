<?php
/**
 * PartoCMS — Properties AJAX Upload
 * آپلود تصویر برای thumbnail و gallery
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// لود auth_check (خودش security_config + config + session + 401 JSON اگر لازم)
require_once __DIR__ . '/../../../admin/auth_check.php';

// حالا isLoggedIn() و isAdmin() از config.php در دسترس هستند
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

// CSRF
if (!hash_equals($_SESSION['properties_csrf'] ?? '', $_POST['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'توکن امنیتی نامعتبر']);
    exit;
}

// بررسی فایل
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فایلی ارسال نشد']);
    exit;
}

// حداکثر ۵ مگابایت
$maxSize = 5 * 1024 * 1024;
if ($_FILES['file']['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حجم فایل بیش از ۵ مگابایت']);
    exit;
}

// MIME واقعی
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['file']['tmp_name']);
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فرمت مجاز نیست (فقط JPG/PNG/WebP/GIF)']);
    exit;
}

// مسیر ذخیره: YYYY/MM
$ext = $allowed[$mime];
$yearMonth = date('Y/m');
$uploadDir = dirname(__DIR__) . '/assets/uploads/properties/' . $yearMonth . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ساخت پوشه آپلود']);
    exit;
}

// نام فایل امن
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$target   = $uploadDir . $filename;

if (!is_uploaded_file($_FILES['file']['tmp_name']) || !move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل']);
    exit;
}

// خروجی
$url = '/modules/properties/assets/uploads/properties/' . $yearMonth . '/' . $filename;
echo json_encode([
    'success'  => true,
    'url'      => $url,
    'filename' => $filename,
    'size'     => $_FILES['file']['size'],
]);

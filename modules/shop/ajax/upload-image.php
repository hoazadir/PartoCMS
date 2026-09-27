<?php
/**
 * PartoCMS - Shop - AJAX Upload Image
 * آپلود تصویر محصول
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'روش نامعتبر']);
    exit;
}

if (empty($_FILES['image'])) {
    echo json_encode(['ok' => false, 'error' => 'فایلی ارسال نشده']);
    exit;
}

$file = $_FILES['image'];

// بررسی خطاهای آپلود
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'فایل بزرگ‌تر از حد مجاز سرور است',
        UPLOAD_ERR_FORM_SIZE => 'فایل بزرگ‌تر از حد مجاز فرم است',
        UPLOAD_ERR_PARTIAL => 'فایل ناقص آپلود شد',
        UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشد',
        UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت موجود نیست',
        UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل',
        UPLOAD_ERR_EXTENSION => 'خطای افزونه',
    ];
    echo json_encode(['ok' => false, 'error' => $errors[$file['error']] ?? 'خطای ناشناخته']);
    exit;
}

// بررسی نوع فایل
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes, true)) {
    echo json_encode(['ok' => false, 'error' => 'فرمت تصویر پشتیبانی نمی‌شود (JPG, PNG, GIF, WEBP)']);
    exit;
}

// بررسی حجم (حداکثر 5 مگابایت)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'error' => 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد']);
    exit;
}

// ساخت پوشه
$uploadDir = __DIR__ . '/../../../assets/uploads/shop/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ساخت نام فایل
$extension = match ($mimeType) {
    'image/jpeg', 'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    default => 'jpg',
};

$filename = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره فایل']);
    exit;
}

// ساخت URL کامل
$url = SITE_URL . '/assets/uploads/shop/' . $filename;

echo json_encode([
    'ok' => true,
    'url' => $url,
    'filename' => $filename,
    'message' => 'تصویر با موفقیت آپلود شد',
]);

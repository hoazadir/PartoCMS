<?php
/**
 * PartoCMS - Ads - Upload Banner
 * آپلود تصویر بنر تبلیغات
 */

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی لاگین
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

// بررسی درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر']);
    exit;
}

$file = $_FILES['file'];

// بررسی خطای آپلود
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'حجم فایل بیش از حد مجاز',
        UPLOAD_ERR_FORM_SIZE  => 'حجم فایل بیش از حد فرم',
        UPLOAD_ERR_PARTIAL    => 'آپلود ناقص انجام شد',
        UPLOAD_ERR_NO_FILE    => 'فایلی انتخاب نشد',
        UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت وجود ندارد',
        UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل',
        UPLOAD_ERR_EXTENSION  => 'پسوند فایل مجاز نیست',
    ];
    echo json_encode(['ok' => false, 'error' => $errors[$file['error']] ?? 'خطای نامشخص']);
    exit;
}

// بررسی MIME
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!isset($allowed[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'فقط تصویر مجاز است (JPEG, PNG, GIF, WebP, SVG)']);
    exit;
}

// بررسی حجم (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'حجم فایل باید کمتر از ۵ مگابایت باشد']);
    exit;
}

// پوشه مخصوص تبلیغات
$adsDir = UPLOAD_DIR . 'ads/';
if (!is_dir($adsDir)) {
    @mkdir($adsDir, 0755, true);
}

// نام فایل
$ext = $allowed[$mime];
$filename = 'ad_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$filepath = $adsDir . $filename;

// آپلود
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ذخیره فایل']);
    exit;
}

// گرفتن ابعاد
$width = $height = null;
if ($mime !== 'image/svg+xml') {
    $size = @getimagesize($filepath);
    if ($size) {
        $width = $size[0];
        $height = $size[1];
    }
}

// مسیر قابل استفاده در URL
$url = UPLOAD_URL . 'ads/' . $filename;
$relativePath = 'assets/uploads/ads/' . $filename;

// ذخیره در جدول media (اختیاری)
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO media (filename, original_name, filepath, mime_type, file_size, width, height, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $filename,
        $file['name'],
        $relativePath,
        $mime,
        $file['size'],
        $width,
        $height,
        $_SESSION['user_id'] ?? null,
    ]);
} catch (Throwable $e) {
    // نادیده بگیر — فایل ذخیره شده
}

// خروجی موفق
echo json_encode([
    'ok'       => true,
    'url'      => $url,
    'path'     => $relativePath,
    'filename' => $filename,
    'width'    => $width,
    'height'   => $height,
    'size'     => $file['size'],
    'mime'     => $mime,
]);

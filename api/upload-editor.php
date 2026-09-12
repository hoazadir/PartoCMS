<?php
/**
 * API آپلود تصویر برای ویرایشگر TinyMCE
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'دسترسی غیرمجاز']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'درخواست نامعتبر']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'خطا در آپلود فایل']);
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);

if (!in_array($mime, $allowed)) {
    echo json_encode(['error' => 'فقط تصویر مجاز است']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'حجم فایل بیشتر از ۵ مگابایت است']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'editor_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$uploadDir = __DIR__ . '/../assets/uploads/';
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['error' => 'خطا در ذخیره فایل']);
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

// ذخیره در دیتابیس
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO media (filename, original_name, filepath, mime_type, file_size, width, height, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $filename,
        $file['name'],
        'assets/uploads/' . $filename,
        $mime,
        $file['size'],
        $width,
        $height,
        $_SESSION['user_id']
    ]);
} catch (Exception $e) {
    // اگه دیتابیس مشکل داشت، فایل باقی می‌مونه
}

echo json_encode([
    'location' => SITE_URL . '/assets/uploads/' . $filename
]);

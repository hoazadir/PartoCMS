<?php
// شروع سشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// لاگ دیباگ
error_log('Upload attempt - Session user_id: ' . ($_SESSION['user_id'] ?? 'NOT SET'));

// بررسی فایل
if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$maxSize = 10 * 1024 * 1024;
$allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedVideos = ['video/mp4', 'video/webm', 'video/ogg'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);


$isImage = in_array($mime, $allowedImages);
$isVideo = in_array($mime, $allowedVideos);

if (!$isImage && !$isVideo) {
    echo json_encode(['success' => false, 'message' => 'فرمت فایل مجاز نیست: ' . $mime]);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'حجم فایل بیش از ۱۰ مگابایت است.']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: ($isImage ? 'jpg' : 'mp4');
$filename = 're_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

// مسیر آپلود - دقیقاً در پوشه assets
$uploadDir = dirname(__DIR__, 4) . '/assets/uploads/real_estate/';
$targetPath = $uploadDir . $filename;
$publicUrl = '/assets/uploads/real_estate/' . $filename;

// ساخت پوشه
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// آپلود
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل. دسترسی: ' . (is_writable($uploadDir) ? 'OK' : 'NOT WRITABLE')]);
}

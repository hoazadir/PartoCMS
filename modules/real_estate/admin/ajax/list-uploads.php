<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$uploadDir = dirname(__DIR__, 3) . '/assets/uploads/real_estate/';
$publicPath = '/assets/uploads/real_estate/';
$images = [];

if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $images[] = $publicPath . $file;
        }
    }
}

// مرتب‌سازی بر اساس تاریخ (جدیدترین اول)
usort($images, function($a, $b) use ($uploadDir) {
    $fileA = $uploadDir . basename($a);
    $fileB = $uploadDir . basename($b);
    return filemtime($fileB) - filemtime($fileA);
});

echo json_encode($images);

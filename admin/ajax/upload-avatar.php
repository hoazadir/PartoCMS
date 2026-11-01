<?php
require_once dirname(__DIR__, 2) . '/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($_FILES['avatar'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['avatar'];
$maxSize = 2 * 1024 * 1024;
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'فرمت تصویر مجاز نیست.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'avatar-' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
$uploadDir = dirname(__DIR__, 2) . '/assets/uploads/avatars/';
$targetPath = $uploadDir . $filename;
$publicUrl = '/assets/uploads/avatars/' . $filename;

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$publicUrl, $_SESSION['user_id']]);
    $_SESSION['user_avatar'] = $publicUrl;
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره فایل.']);
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // در خروجی JSON نمایش نده
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'ابتدا وارد شوید.']);
    exit;
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'خطا در آپلود فایل یا فایلی انتخاب نشده است.']);
    exit;
}

$file = $_FILES['avatar'];
$maxSize = 3 * 1024 * 1024; // 3MB
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($file['size'] > $maxSize) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'حجم عکس نباید بیشتر از ۳ مگابایت باشد.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'فرمت تصویر مجاز نیست (فقط JPG, PNG, WEBP).']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if ($mime === 'image/jpeg') $ext = 'jpg';
$filename = 'avatar-' . $_SESSION['user_id'] . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
$targetDir = __DIR__ . '/../../assets/uploads/avatars/';
$targetPath = $targetDir . $filename;
$publicUrl = '/assets/uploads/avatars/' . $filename;

if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$publicUrl, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'url' => $publicUrl]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ذخیره فایل روی سرور ممکن نبود. دسترسی پوشه را چک کنید.']);
}

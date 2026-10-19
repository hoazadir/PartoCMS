<?php
/**
 * PartoCMS — Portfolio AJAX Upload
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/../../../config.php';
    require_once __DIR__ . '/../../../admin/auth_check.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!hash_equals($_SESSION['portfolio_csrf'] ?? '', $_POST['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    exit;
}

if (empty($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$files = $_FILES['files'];
$isMulti = is_array($files['name']);

if (!$isMulti) {
    $files = [
        'name'     => [$files['name']],
        'tmp_name' => [$files['tmp_name']],
        'size'     => [$files['size']],
        'error'    => [$files['error']],
        'type'     => [$files['type']],
    ];
}

$uploadDir = __DIR__ . '/../../../assets/uploads/portfolio/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024;
$uploadedUrls = [];
$errors = [];

foreach ($files['name'] as $i => $originalName) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "$originalName: upload error #{$files['error'][$i]}";
        continue;
    }

    $tmpPath = $files['tmp_name'][$i];

    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        $errors[] = "$originalName: not an image";
        continue;
    }

    $mime = $imageInfo['mime'] ?? '';
    if (!in_array($mime, $allowedMimes, true)) {
        $errors[] = "$originalName: mime not allowed";
        continue;
    }

    if ($files['size'][$i] > $maxSize) {
        $errors[] = "$originalName: too large";
        continue;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    $filename = 'portfolio-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (move_uploaded_file($tmpPath, $dest)) {
        $uploadedUrls[] = '/assets/uploads/portfolio/' . $filename;
    } else {
        $errors[] = "$originalName: move failed";
    }
}

if (empty($uploadedUrls)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $errors[0] ?? 'Upload failed',
        'errors'  => $errors,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'urls'    => $uploadedUrls,
    'errors'  => $errors,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

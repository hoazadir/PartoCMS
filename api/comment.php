<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'متد غیرمجاز']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$contentId = (int) ($input['content_id'] ?? 0);
$parentId = !empty($input['parent_id']) ? (int) $input['parent_id'] : null;
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$website = trim($input['website'] ?? '');
$comment = trim($input['comment'] ?? '');

// اعتبارسنجی
$errors = [];
if (!$contentId) $errors[] = 'شناسه محتوا نامعتبر است';
if (empty($name)) $errors[] = 'نام الزامی است';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر الزامی است';
if (empty($comment) || mb_strlen($comment) < 3) $errors[] = 'متن دیدگاه باید حداقل ۳ کاراکتر باشد';
if (mb_strlen($comment) > 2000) $errors[] = 'متن دیدگاه طولانی است';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(' • ', $errors)]);
    exit;
}

$pdo = getDB();

// چک کن محتوا وجود داره
$stmt = $pdo->prepare("SELECT id FROM content_items WHERE id = ? AND status = 'published'");
$stmt->execute([$contentId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'محتوا یافت نشد']);
    exit;
}

// چک اسپم ساده - IP یکسان بیش از ۳ کامنت در دقیقه
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE author_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$stmt->execute([$ip]);
if ($stmt->fetchColumn() >= 3) {
    http_response_code(429);
    echo json_encode(['error' => 'لطفاً کمی صبر کنید و سپس دوباره تلاش کنید']);
    exit;
}

try {
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO comments (content_id, parent_id, author_name, author_email, author_website, author_ip, user_id, comment, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$contentId, $parentId, $name, $email, $website ?: null, $ip, $userId, $comment]);
    
    $commentId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'id' => $commentId,
        'message' => 'دیدگاه شما ثبت شد و پس از تأیید نمایش داده می‌شود'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در ثبت دیدگاه: ' . $e->getMessage()]);
}

<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'متد غیرمجاز']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$formId = (int) ($input['form_id'] ?? 0);
$data = $input['data'] ?? [];

if (!$formId || empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'اطلاعات ناقص']);
    exit;
}

$pdo = getDB();

// چک کن فرم وجود داره
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ? AND is_active = 1");
$stmt->execute([$formId]);
$form = $stmt->fetch();

if (!$form) {
    http_response_code(404);
    echo json_encode(['error' => 'فرم یافت نشد یا غیرفعال است']);
    exit;
}

// اعتبارسنجی فیلدهای اجباری
$fields = json_decode($form['fields'] ?? '[]', true) ?: [];
$errors = [];
foreach ($fields as $f) {
    if (!empty($f['required']) && empty($data[$f['name']])) {
        $errors[] = 'فیلد ' . ($f['label'] ?? $f['name']) . ' اجباری است';
    }
    if ($f['type'] === 'email' && !empty($data[$f['name']]) && !filter_var($data[$f['name']], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل معتبر نیست';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(' • ', $errors)]);
    exit;
}

// ذخیره
try {
    $stmt = $pdo->prepare("
        INSERT INTO form_submissions (form_id, data, ip_address, user_agent, status, created_at)
        VALUES (?, ?, ?, ?, 'new', NOW())
    ");
    $stmt->execute([
        $formId,
        json_encode($data, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    echo json_encode([
        'success' => true,
        'message' => $form['success_message'] ?: '✅ پیام شما با موفقیت ارسال شد.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطا در ذخیره: ' . $e->getMessage()]);
}

<?php
// api/store.php
require_once __DIR__ . '/../config.php';

jsonHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'متد غیرمجاز'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['status' => 'error', 'message' => 'داده‌های ورودی نامعتبر'], 400);
}

$templateId = $input['template_id'] ?? ($_GET['template_id'] ?? null);
$name = $input['name'] ?? 'پروژه بدون نام';
$html = $input['html'] ?? '';
$css = $input['css'] ?? '';
$components = $input['components'] ?? '[]';
$styles = $input['styles'] ?? '[]';

// اگر کامپوننت‌ها آرایه باشند، به JSON تبدیل می‌شوند
if (is_array($components)) {
    $components = json_encode($components, JSON_UNESCAPED_UNICODE);
}
if (is_array($styles)) {
    $styles = json_encode($styles, JSON_UNESCAPED_UNICODE);
}

$pdo = getDB();

try {
    if ($templateId && is_numeric($templateId)) {
        // بررسی وجود قالب
        $check = $pdo->prepare("SELECT id FROM templates WHERE id = ?");
        $check->execute([$templateId]);
        
        if ($check->fetch()) {
            // به‌روزرسانی قالب موجود
            $stmt = $pdo->prepare("UPDATE templates SET name = ?, content = ?, css = ?, components = ?, styles = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $html, $css, $components, $styles, $templateId]);
            
            jsonResponse([
                'status' => 'success',
                'id' => $templateId,
                'message' => 'قالب با موفقیت به‌روزرسانی شد'
            ]);
        }
    }
    
    // ایجاد قالب جدید
    $stmt = $pdo->prepare("INSERT INTO templates (name, description, content, css, components, styles, is_active, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())");
    $stmt->execute([
        $name,
        'ایجاد شده توسط ویرایشگر',
        $html,
        $css,
        $components,
        $styles,
        $_SESSION['user_id'] ?? 1
    ]);
    
    $newId = $pdo->lastInsertId();
    
    jsonResponse([
        'status' => 'success',
        'id' => $newId,
        'message' => 'قالب جدید با موفقیت ایجاد شد'
    ]);
    
} catch (PDOException $e) {
    error_log('Storage Error: ' . $e->getMessage());
    jsonResponse([
        'status' => 'error',
        'message' => 'خطا در ذخیره‌سازی: ' . $e->getMessage()
    ], 500);
}

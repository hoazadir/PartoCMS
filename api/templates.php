<?php
// api/templates.php
require_once __DIR__ . '/../config.php';

jsonHeader();

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $stmt = $pdo->query("SELECT id, name, description, thumbnail, is_active, is_default, use_for_posts, created_at FROM templates ORDER BY created_at DESC");
                jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            }

            if ($action === 'get' && isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $template = $stmt->fetch();

                if (!$template) {
                    jsonResponse(['status' => 'error', 'message' => 'قالب یافت نشد'], 404);
                }

                jsonResponse(['status' => 'success', 'data' => $template]);
            }

            // دریافت قالب فعال برای مقالات
            if ($action === 'get_for_posts') {
                $stmt = $pdo->query("SELECT * FROM templates WHERE use_for_posts = 1 AND is_active = 1 LIMIT 1");
                $template = $stmt->fetch();
                jsonResponse(['status' => 'success', 'data' => $template ?: null]);
            }
            break;

        case 'POST':
            requireAdminAPI();

            $input = json_decode(file_get_contents('php://input'), true);

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO templates (name, description, content, css, components, styles, is_active, author_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
                $stmt->execute([
                    $input['name'] ?? 'قالب جدید',
                    $input['description'] ?? '',
                    $input['content'] ?? '',
                    $input['css'] ?? '',
                    $input['components'] ?? '[]',
                    $input['styles'] ?? '[]',
                    $_SESSION['user_id'] ?? 1
                ]);

                jsonResponse(['status' => 'success', 'id' => $pdo->lastInsertId()]);
            }

            if ($action === 'toggle' && isset($input['id'])) {
                $stmt = $pdo->prepare("UPDATE templates SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$input['id']]);
                jsonResponse(['status' => 'success']);
            }

            if ($action === 'set_default' && isset($input['id'])) {
                $pdo->exec("UPDATE templates SET is_default = 0");
                $stmt = $pdo->prepare("UPDATE templates SET is_default = 1 WHERE id = ?");
                $stmt->execute([$input['id']]);
                jsonResponse(['status' => 'success']);
            }

            // ==================== تنظیم به‌عنوان قالب مقالات ====================
            if ($action === 'use_for_posts' && isset($input['id'])) {
                // اول همه رو صفر کن
                $pdo->exec("UPDATE templates SET use_for_posts = 0");
                // بعد این یکی رو یک کن
                $stmt = $pdo->prepare("UPDATE templates SET use_for_posts = 1 WHERE id = ?");
                $stmt->execute([$input['id']]);

                if ($stmt->rowCount() > 0) {
                    jsonResponse(['status' => 'success', 'message' => 'این قالب به‌عنوان قالب مقالات تنظیم شد']);
                } else {
                    jsonResponse(['status' => 'error', 'message' => 'قالب یافت نشد'], 404);
                }
            }

            if ($action === 'unuse_for_posts' && isset($input['id'])) {
                $stmt = $pdo->prepare("UPDATE templates SET use_for_posts = 0 WHERE id = ?");
                $stmt->execute([$input['id']]);
                jsonResponse(['status' => 'success', 'message' => 'علامت قالب مقالات برداشته شد']);
            }

            if ($action === 'update' && isset($input['id'])) {
                $stmt = $pdo->prepare("UPDATE templates SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([
                    $input['name'],
                    $input['description'] ?? '',
                    $input['id']
                ]);
                jsonResponse(['status' => 'success']);
            }
            break;

        case 'DELETE':
            requireAdminAPI();

            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? ($_GET['id'] ?? null);

            if (!$id) {
                jsonResponse(['status' => 'error', 'message' => 'شناسه قالب الزامی است'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
            $stmt->execute([$id]);
            jsonResponse(['status' => 'success']);
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'متد غیرمجاز'], 405);
    }
} catch (PDOException $e) {
    error_log('Templates API Error: ' . $e->getMessage());
    jsonResponse(['status' => 'error', 'message' => 'خطا در عملیات دیتابیس: ' . $e->getMessage()], 500);
}

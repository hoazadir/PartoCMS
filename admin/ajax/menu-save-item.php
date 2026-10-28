<?php
require_once dirname(__DIR__, 2) . '/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$action = $_POST['action'] ?? 'save';

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit;
}

// ذخیره (افزودن یا ویرایش)
$id = (int)($_POST['id'] ?? 0);
$menuId = (int)($_POST['menu_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? 'custom';
$url = trim($_POST['url'] ?? '#');
$targetId = !empty($_POST['target_id']) ? (int)$_POST['target_id'] : null;
$icon = trim($_POST['icon'] ?? '');
$targetBlank = isset($_POST['target_blank']) ? 1 : 0;
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if (!$title || !$menuId) {
    echo json_encode(['success' => false, 'message' => 'عنوان و منو الزامی است']);
    exit;
}

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE menu_items SET title=?, type=?, url=?, target_id=?, icon=?, target_blank=?, parent_id=? WHERE id=? AND menu_id=?");
        $stmt->execute([$title, $type, $url, $targetId, $icon, $targetBlank, $parentId, $id, $menuId]);
    } else {
        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items WHERE menu_id = ?");
        $maxOrder->execute([$menuId]);
        $nextOrder = $maxOrder->fetchColumn();
        
        $stmt = $pdo->prepare("INSERT INTO menu_items (menu_id, parent_id, title, url, type, target_id, icon, target_blank, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$menuId, $parentId, $title, $url, $type, $targetId, $icon, $targetBlank, $nextOrder]);
        $id = $pdo->lastInsertId();
    }
    
    $newItem = $pdo->query("SELECT * FROM menu_items WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'item' => $newItem]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

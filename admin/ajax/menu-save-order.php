<?php
require_once dirname(__DIR__, 2) . '/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$menuId = (int)($input['menu_id'] ?? 0);
$items = $input['items'] ?? [];

if (!$menuId || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$pdo = getDB();

try {
    $order = 1;
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $parentId = ($item['parent'] === '#') ? null : (int)$item['parent'];
        
        $stmt = $pdo->prepare("UPDATE menu_items SET parent_id = ?, sort_order = ? WHERE id = ? AND menu_id = ?");
        $stmt->execute([$parentId, $order, $id, $menuId]);
        $order++;
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

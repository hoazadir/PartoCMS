<?php
require_once dirname(__DIR__, 4) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$menuId = (int)($input['menu_id'] ?? 0);
$tree = $input['tree'] ?? [];
if (!$menuId) { echo json_encode(['success'=>false]); exit; }
$pdo = getDB();

function saveT($pdo, $items, $menuId, $parentId = null, &$o = 1) {
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $pdo->prepare("UPDATE menu_items SET parent_id=?, sort_order=? WHERE id=? AND menu_id=?")
            ->execute([$parentId, $o++, $id, $menuId]);
        if (!empty($item['children'])) saveT($pdo, $item['children'], $menuId, $id, $o);
    }
}

try {
    $o = 1;
    saveT($pdo, $tree, $menuId, null, $o);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

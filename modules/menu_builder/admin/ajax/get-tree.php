<?php
error_reporting(0);
require_once dirname(__DIR__, 4) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { echo '[]'; exit; }
$menuId = (int)($_GET['menu_id'] ?? 0);
if (!$menuId) { echo '[]'; exit; }

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC");
$stmt->execute([$menuId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tree = [];
$itemsById = [];
foreach ($items as $item) {
    $itemsById[$item['id']] = [
        'id' => (int)$item['id'],
        'title' => $item['title'],
        'type' => $item['type'],
        'url' => $item['url'],
        'icon' => $item['icon'],
        'target_id' => $item['target_id'],
        'target_blank' => (int)$item['target_blank'],
        'meta' => $item['meta'] ? json_decode($item['meta'], true) : null,
        'children' => []
    ];
}
foreach ($items as $item) {
    if ($item['parent_id'] && isset($itemsById[$item['parent_id']])) {
        $itemsById[$item['parent_id']]['children'][] = &$itemsById[$item['id']];
    } else {
        $tree[] = &$itemsById[$item['id']];
    }
}
echo json_encode($tree);

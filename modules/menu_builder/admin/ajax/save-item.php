<?php
require_once dirname(__DIR__, 4) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$pdo = getDB();
$id = (int)($_POST['id'] ?? 0);
$menuId = (int)($_POST['menu_id'] ?? 0);
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? 'custom';
$url = trim($_POST['url'] ?? '#');
$icon = trim($_POST['icon'] ?? '');
$targetId = !empty($_POST['target_id']) ? (int)$_POST['target_id'] : null;
$targetBlank = isset($_POST['target_blank']) ? 1 : 0;
$cssClass = trim($_POST['css_class'] ?? '');

$meta = [];
if (!empty($_POST['mega_enabled'])) {
    $meta['mega_menu'] = true;
    $meta['columns'] = (int)($_POST['mega_columns'] ?? 2);
}
if ($cssClass) $meta['css_class'] = $cssClass;
$metaJson = !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;

if (!$menuId || !$title) { echo json_encode(['success'=>false,'message'=>'عنوان الزامی است']); exit; }

try {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE menu_items SET parent_id=?, title=?, type=?, url=?, icon=?, target_id=?, target_blank=?, meta=? WHERE id=? AND menu_id=?");
        $stmt->execute([$parentId, $title, $type, $url, $icon, $targetId, $targetBlank, $metaJson, $id, $menuId]);
    } else {
        $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM menu_items WHERE menu_id=?");
        $max->execute([$menuId]);
        $order = $max->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO menu_items (menu_id, parent_id, title, type, url, icon, target_id, target_blank, meta, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
        $stmt->execute([$menuId, $parentId, $title, $type, $url, $icon, $targetId, $targetBlank, $metaJson, $order]);
        $id = $pdo->lastInsertId();
    }
    echo json_encode(['success'=>true, 'id'=>$id]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

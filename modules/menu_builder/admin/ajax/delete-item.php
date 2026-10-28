<?php
require_once dirname(__DIR__, 4) . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false]); exit; }
$pdo = getDB();

function deleteRec($pdo, $id) {
    $children = $pdo->prepare("SELECT id FROM menu_items WHERE parent_id = ?");
    $children->execute([$id]);
    foreach ($children->fetchAll(PDO::FETCH_COLUMN) as $c) deleteRec($pdo, $c);
    $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
}

try {
    deleteRec($pdo, $id);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

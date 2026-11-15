<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, name FROM provinces ORDER BY name ASC");
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'provinces' => $provinces], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

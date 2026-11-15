<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';

$provinceId = (int)($_GET['province_id'] ?? 0);
$search     = trim((string)($_GET['q'] ?? ''));

if ($provinceId <= 0) {
    echo json_encode(['success' => false, 'cities' => []]);
    exit;
}

try {
    $pdo = getDB();

    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT id, name FROM cities WHERE province_id = ? AND name LIKE ? ORDER BY name ASC LIMIT 50");
        $stmt->execute([$provinceId, '%' . $search . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM cities WHERE province_id = ? ORDER BY name ASC LIMIT 50");
        $stmt->execute([$provinceId]);
    }

    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'cities' => $cities], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

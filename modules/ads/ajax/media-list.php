<?php
/**
 * PartoCMS - Ads - Media List
 * لیست تصاویر موجود برای Media Picker
 */

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? ''; // image | all

try {
    $pdo = getDB();

    // ساخت کوئری
    $where = [];
    $params = [];

    if ($type === 'image') {
        $where[] = "mime_type LIKE 'image/%'";
    }

    if ($search !== '') {
        $where[] = "(original_name LIKE ? OR filename LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // تعداد کل
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM media $whereSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // دریافت
    $sql = "SELECT id, filename, original_name, filepath, mime_type, file_size, width, height, created_at
            FROM media
            $whereSql
            ORDER BY id DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تبدیل مسیر به URL کامل
    foreach ($items as &$item) {
        $item['url'] = SITE_URL . '/' . ltrim($item['filepath'], '/');
    }

    echo json_encode([
        'ok'     => true,
        'items'  => $items,
        'total'  => $total,
        'page'   => $page,
        'limit'  => $limit,
        'pages'  => max(1, (int) ceil($total / $limit)),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
}

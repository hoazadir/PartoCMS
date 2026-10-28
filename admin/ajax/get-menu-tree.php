<?php
// خاموش کردن نمایش خطاها تا خروجی JSON خراب نشود
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// پاسخ پیش‌فرض (برای جلوگیری از لودینگ ابدی)
$response = [];

try {
    if (!isLoggedIn()) {
        echo json_encode($response);
        exit;
    }

    // خواندن menu_id از هر دو GET و POST (jsTree گاهی POST می‌فرستد)
    $menuId = (int)($_REQUEST['menu_id'] ?? 0);
    if (!$menuId) {
        echo json_encode($response);
        exit;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$menuId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $icon = $item['icon'] ?: '📄';
        $response[] = [
            'id' => (string)$item['id'],
            'parent' => $item['parent_id'] ? (string)$item['parent_id'] : '#',
            'text' => $icon . ' ' . $item['title'],
            'data' => [
                'type' => $item['type'],
                'url' => $item['url'],
                'target_id' => $item['target_id'],
                'icon' => $item['icon']
            ]
        ];
    }
} catch (Throwable $e) {
    // اگر هر خطایی رخ داد، حداقل یک آرایه خالی برگردان تا jsTree گیر نکند
    $response = [];
}

echo json_encode($response);
exit;

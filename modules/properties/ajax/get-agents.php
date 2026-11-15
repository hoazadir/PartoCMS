<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/config.php';

$search = trim((string)($_GET['q'] ?? ''));

try {
    $pdo = getDB();

    // فقط مشاوران تأییدشده (approved) با کاربر فعال
    $sql = "SELECT u.id, u.username, u.email, u.national_code, u.phone,
                   ap.first_name, ap.last_name, ap.agency_name, ap.agency_license_no
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            INNER JOIN agent_profiles ap ON ap.user_id = u.id
            WHERE r.slug = 'agent'
              AND u.is_active = 1
              AND ap.status = 'approved'";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.national_code LIKE ?
                       OR ap.first_name LIKE ? OR ap.last_name LIKE ? OR ap.agency_name LIKE ?)";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like, $like, $like];
    }

    $sql .= " ORDER BY ap.first_name ASC, ap.last_name ASC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function($a) {
        $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        if ($name === '') $name = $a['username'];
        $text = $name;
        if (!empty($a['agency_name']))    $text .= ' — ' . $a['agency_name'];
        if (!empty($a['national_code']))  $text .= ' (کد ملی: ' . $a['national_code'] . ')';
        return ['id' => $a['id'], 'text' => $text];
    }, $agents);

    echo json_encode(['success' => true, 'agents' => $result], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

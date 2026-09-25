<?php
/**
 * PartoCMS - Table Builder AJAX v3.0
 * - auth_check.php برای احراز هویت
 * - debug mode
 * - خطاهای دقیق
 */

// ═══════════════════════════════════════════════════════════
//  Auth check (شامل config + session + redirect)
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/../../admin/auth_check.php';

require_once __DIR__ . '/includes/TableBuilder.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

// ═══════════════════════════════════════════════════════════
//  Debug mode
// ═══════════════════════════════════════════════════════════
if (isset($_GET['debug_ajax'])) {
    echo json_encode([
        'ok' => true,
        'debug' => [
            'session_status' => session_status(),
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? 'NULL',
            'role' => $_SESSION['role'] ?? 'NULL',
            'role_slug' => $_SESSION['role_slug'] ?? 'NULL',
            'username' => $_SESSION['username'] ?? 'NULL',
            'all_session_keys' => array_keys($_SESSION),
            'pdo_exists' => isset($pdo) ? 'yes' : 'no',
            'db_name' => defined('DB_NAME') ? DB_NAME : 'NOT DEFINED',
            'action' => $_GET['action'] ?? 'NULL',
            'table' => $_GET['table'] ?? 'NULL',
            'x_requested_with' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NULL',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NULL',
            'php_sapi' => php_sapi_name(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  چک نقش ادمین
// ═══════════════════════════════════════════════════════════
$role = $_SESSION['role'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

if ($role !== 'admin' || !$userId) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'unauthorized',
        'debug' => [
            'role' => $role,
            'user_id' => $userId,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  DB Connection
// ═══════════════════════════════════════════════════════════
if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}

$tb = new TableBuilder($pdo);
$action = $_GET['action'] ?? '';

try {
    // ═══════════════════════════════════════════════════════════
    //  ساختار جدول
    // ═══════════════════════════════════════════════════════════
    if ($action === 'structure') {
        $table = trim($_GET['table'] ?? '');

        if (empty($table)) {
            echo json_encode(['ok' => false, 'error' => 'نام جدول خالی است']);
            exit;
        }

        if (!$tb->tableExists($table)) {
            echo json_encode([
                'ok' => false,
                'error' => 'جدول یافت نشد',
                'table' => $table,
                'db_name' => $tb->getDbName(),
                'available_tables' => array_slice($tb->getTableNames(), 0, 15),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'table' => $table,
            'columns' => $tb->getColumns($table),
            'foreign_keys' => $tb->getForeignKeys($table),
            'composite_indexes' => $tb->getCompositeIndexes($table),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  پیش‌نمایش SQL
    // ═══════════════════════════════════════════════════════════
    if ($action === 'preview_sql') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tableName = $input['table_name'] ?? '';
        $columns = $input['columns'] ?? [];
        $compositeIndexes = $input['composite_indexes'] ?? [];

        echo json_encode([
            'ok' => true,
            'sql' => $tb->previewCreateSQL($tableName, $columns, $compositeIndexes),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  بررسی نام جدول
    // ═══════════════════════════════════════════════════════════
    if ($action === 'check_name') {
        $name = trim($_GET['name'] ?? '');
        $check = $tb->validateIdentifier($name, 'جدول');

        if (!$check['ok']) {
            echo json_encode(['ok' => false, 'error' => $check['error']]);
            exit;
        }
        if ($tb->tableExists($name)) {
            echo json_encode(['ok' => false, 'error' => 'این نام قبلاً استفاده شده']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  PK ستون‌ها (برای FK)
    // ═══════════════════════════════════════════════════════════
    if ($action === 'pk_columns') {
        $table = trim($_GET['table'] ?? '');

        if (!$tb->tableExists($table)) {
            echo json_encode(['ok' => false, 'error' => 'جدول یافت نشد', 'columns' => ['id']]);
            exit;
        }

        $pks = $tb->getPrimaryKeyColumns($table);
        echo json_encode([
            'ok' => true,
            'columns' => !empty($pks) ? $pks : ['id'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  ستون‌ها (برای Index)
    // ═══════════════════════════════════════════════════════════
    if ($action === 'columns') {
        $table = trim($_GET['table'] ?? '');

        if (!$tb->tableExists($table)) {
            echo json_encode(['ok' => false, 'error' => 'جدول یافت نشد', 'columns' => []]);
            exit;
        }

        $cols = array_column($tb->getColumns($table), 'Field');
        echo json_encode(['ok' => true, 'columns' => $cols], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  Foreign Keys
    // ═══════════════════════════════════════════════════════════
    if ($action === 'foreign_keys') {
        $table = trim($_GET['table'] ?? '');

        if (!$tb->tableExists($table)) {
            echo json_encode(['ok' => false, 'error' => 'جدول یافت نشد']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'foreign_keys' => $tb->getForeignKeys($table),
            'incoming' => $tb->getIncomingRelations($table),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  Pivot Preview
    // ═══════════════════════════════════════════════════════════
    if ($action === 'preview_pivot') {
        $input = json_decode(file_get_contents('php://input'), true);
        $tableA = $input['table_a'] ?? '';
        $colA = $input['col_a'] ?? 'id';
        $tableB = $input['table_b'] ?? '';
        $colB = $input['col_b'] ?? 'id';
        $pivotName = $input['pivot_name'] ?? '';

        $sql = "-- Pivot Table Preview\n";
        $sql .= "-- Table A: $tableA ($colA)\n";
        $sql .= "-- Table B: $tableB ($colB)\n\n";
        $sql .= "CREATE TABLE `" . ($pivotName ?: ($tableA . '_' . $tableB)) . "` (\n";
        $sql .= "    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n";
        $sql .= "    `{$tableA}_id` INT NOT NULL,\n";
        $sql .= "    `{$tableB}_id` INT NOT NULL,\n";
        $sql .= "    PRIMARY KEY (`id`),\n";
        $sql .= "    UNIQUE KEY `uk_" . ($pivotName ?: ($tableA . '_' . $tableB)) . "` (`{$tableA}_id`, `{$tableB}_id`),\n";
        $sql .= "    KEY `idx_{$tableA}` (`{$tableA}_id`),\n";
        $sql .= "    KEY `idx_{$tableB}` (`{$tableB}_id`),\n";
        $sql .= "    CONSTRAINT `fk_{$tableA}` FOREIGN KEY (`{$tableA}_id`) REFERENCES `$tableA` (`$colA`) ON DELETE CASCADE,\n";
        $sql .= "    CONSTRAINT `fk_{$tableB}` FOREIGN KEY (`{$tableB}_id`) REFERENCES `$tableB` (`$colB`) ON DELETE CASCADE\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        echo json_encode(['ok' => true, 'sql' => $sql], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    //  Action نامعتبر
    // ═══════════════════════════════════════════════════════════
    echo json_encode(['ok' => false, 'error' => 'action نامعتبر: ' . $action]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}

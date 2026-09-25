<?php
/**
 * PartoCMS - Row Save (Insert/Update)
 */
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/includes/TableBuilder.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/table_builder.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$tb = new TableBuilder($pdo);

$table = trim($_POST['table'] ?? '');
$mode = $_POST['mode'] ?? 'create';
$pkCol = trim($_POST['pk_col'] ?? '');
$pkVal = $_POST['pk_val'] ?? null;
$data = $_POST['data'] ?? [];

if (empty($table) || !$tb->tableExists($table)) {
    header('Location: /admin/table_builder.php?error=table_not_found');
    exit;
}

if (!is_array($data) || empty($data)) {
    header('Location: /admin/table_row_edit.php?table=' . urlencode($table) . '&error=no_data');
    exit;
}

$columns = $tb->getColumns($table);
$colMap = [];
foreach ($columns as $c) {
    $colMap[$c['Field']] = $c;
}

$cleanData = [];
foreach ($data as $col => $val) {
    if (!isset($colMap[$col])) continue;
    $isNullable = (($colMap[$col]['Null'] ?? '') === 'YES');

    if ($val === '' && $isNullable) {
        $cleanData[$col] = null;
    } else {
        $cleanData[$col] = $val;
    }
}

// checkbox های تیک نخورده
foreach ($colMap as $colName => $colInfo) {
    $sqlType = strtoupper($colInfo['Type']);
    if (preg_match('/^TINYINT\(1\)/', $sqlType) && !isset($data[$colName])) {
        $cleanData[$colName] = 0;
    }
}

if ($mode === 'create') {
    $result = $tb->insertRow($table, $cleanData);
} else {
    if (!$pkCol || $pkVal === null) {
        header('Location: /admin/table_data.php?table=' . urlencode($table) . '&error=no_pk');
        exit;
    }
    $result = $tb->updateRow($table, $pkCol, $pkVal, $cleanData);
}

if (!empty($result['ok'])) {
    header('Location: /admin/table_data.php?table=' . urlencode($table) . '&msg=saved');
} else {
    $err = urlencode($result['error'] ?? 'unknown');
    $url = '/admin/table_row_edit.php?table=' . urlencode($table) . '&error=' . $err;
    if ($mode === 'edit') $url .= '&pk=' . urlencode($pkVal);
    header('Location: ' . $url);
}
exit;

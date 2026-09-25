<?php
/**
 * PartoCMS - Wrapper for modules/table_builder/ajax.php
 * URL: /admin/table_ajax.php
 */
$modulePath = __DIR__ . '/../modules/table_builder/ajax.php';
if (!file_exists($modulePath)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Module file not found']);
    exit;
}
require $modulePath;

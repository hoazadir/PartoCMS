<?php
/**
 * PartoCMS - Wrapper for modules/table_builder/export.php
 * URL: /admin/table_export.php
 */
$modulePath = __DIR__ . '/../modules/table_builder/export.php';
if (!file_exists($modulePath)) {
    die('Module file not found: modules/table_builder/export.php');
    exit;
}
require $modulePath;

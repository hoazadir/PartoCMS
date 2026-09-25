<?php
/**
 * PartoCMS - Wrapper for modules/table_builder/data.php
 * URL: /admin/table_data.php
 */
$modulePath = __DIR__ . '/../modules/table_builder/data.php';
if (!file_exists($modulePath)) {
    die('Module file not found: modules/table_builder/data.php');
    exit;
}
require $modulePath;

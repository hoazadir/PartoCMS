<?php
/**
 * PartoCMS - Wrapper for modules/table_builder/admin.php
 * URL: /admin/table_builder.php
 */
$modulePath = __DIR__ . '/../modules/table_builder/admin.php';
if (!file_exists($modulePath)) {
    die('Module file not found: modules/table_builder/admin.php');
    exit;
}
require $modulePath;

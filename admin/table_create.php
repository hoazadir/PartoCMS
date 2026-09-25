<?php
/**
 * PartoCMS - Wrapper for modules/table_builder/create.php
 * URL: /admin/table_create.php
 */
$modulePath = __DIR__ . '/../modules/table_builder/create.php';
if (!file_exists($modulePath)) {
    die('Module file not found: modules/table_builder/create.php');
    exit;
}
require $modulePath;

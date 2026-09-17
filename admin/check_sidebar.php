<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══ ۱. Session ═══\n";
echo "role: " . ($_SESSION['role'] ?? 'NULL') . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "isAdmin: " . var_export(($_SESSION['role'] ?? '') === 'admin', true) . "\n";

echo "\n═══ ۲. URLs ═══\n";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'UNDEFINED') . "\n";
echo "ADMIN_URL: " . (defined('ADMIN_URL') ? ADMIN_URL : 'UNDEFINED') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NULL') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'NULL') . "\n";

echo "\n═══ ۳. Sidebar vars ═══\n";
$currentFile = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];
echo "currentFile: $currentFile\n";
echo "currentPath: $currentPath\n";

echo "\n═══ ۴. Registry test ═══\n";
require_once __DIR__ . '/includes/module_registry.php';
require_once __DIR__ . '/includes/module_registry_sidebar.php';

$pdo = getDB();
$mr = new ModuleRegistry($pdo);
$mrs = new ModuleRegistrySidebar($mr);

echo "Groups count: " . count($mr->getGroups()) . "\n";
foreach ($mr->getGroups() as $slug => $g) {
    echo "  - $slug: " . count($g['modules']) . " ماژول\n";
}

echo "\n═══ ۵. renderGroups output ═══\n";
$out = $mrs->renderGroups($currentFile, $currentPath, ADMIN_URL, null, true);
echo "طول: " . strlen($out) . " بایت\n";
echo "اول 800 کاراکتر:\n";
echo substr($out, 0, 800);

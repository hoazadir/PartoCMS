<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== URL ===\n";
echo "set_lang GET: " . ($_GET['set_lang'] ?? 'NULL') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NULL') . "\n\n";

echo "=== Session ===\n";
echo "session_id: " . session_id() . "\n";
echo "session_status: " . session_status() . "\n";
echo "\$_SESSION contents:\n";
print_r($_SESSION);
echo "\n";

echo "=== Cookies ===\n";
foreach ($_COOKIE as $k => $v) {
    echo "  $k = $v\n";
}
echo "\n";

echo "=== getCurrentLanguageInfo ===\n";
$info = getCurrentLanguageInfo();
print_r($info);
echo "\n";

echo "=== getCurrentLanguageId ===\n";
var_dump(getCurrentLanguageId());
echo "\n";

echo "=== isDefaultLanguage ===\n";
var_dump(isDefaultLanguage());

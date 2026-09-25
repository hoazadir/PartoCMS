<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══ Session ═══" . PHP_EOL;
echo "session_id: " . session_id() . PHP_EOL;
echo "session_status: " . session_status() . PHP_EOL;
echo "user_id: " . ($_SESSION['user_id'] ?? 'NULL') . PHP_EOL;
echo "role: " . ($_SESSION['role'] ?? 'NULL') . PHP_EOL;
echo "role_slug: " . ($_SESSION['role_slug'] ?? 'NULL') . PHP_EOL;
echo "username: " . ($_SESSION['username'] ?? 'NULL') . PHP_EOL;

echo PHP_EOL . "═══ isLoggedIn ═══" . PHP_EOL;
var_dump(isLoggedIn());

echo PHP_EOL . "═══ X-Requested-With ═══" . PHP_EOL;
echo "HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NULL') . PHP_EOL;

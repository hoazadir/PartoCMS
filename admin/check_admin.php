<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: text/plain');
echo "role: " . ($_SESSION['role'] ?? 'NULL') . "\n";
echo "role_slug: " . ($_SESSION['role_slug'] ?? 'NULL') . "\n";
echo "isAdmin: " . var_export(($_SESSION['role'] ?? '') === 'admin', true) . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "username: " . ($_SESSION['username'] ?? 'NULL') . "\n";

<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDB();
    echo "✅ اتصال به دیتابیس موفق بود!<br>";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "جداول: " . implode(', ', $tables) . "<br>";
    
    $stmt = $pdo->query("SELECT username, role FROM users");
    $users = $stmt->fetchAll();
    echo "کاربران:<br>";
    foreach ($users as $u) {
        echo "- " . $u['username'] . " (" . $u['role'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "❌ خطا: " . $e->getMessage();
}

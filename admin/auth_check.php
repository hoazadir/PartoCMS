<?php
/**
 * بررسی احراز هویت + لایه امنیتی
 * در ابتدای همه صفحات admin لود می‌شود
 */

// ==================== ۱. امنیت پایه ====================
require_once __DIR__ . '/includes/security_config.php';

// ==================== ۲. لود config.php از ریشه پروژه ====================
$possibleConfigs = [
    __DIR__ . '/../config.php',           // /PartoCMS/config.php ← مسیر واقعی تو
    __DIR__ . '/includes/config.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../core/config.php',
];
$configLoaded = false;
foreach ($possibleConfigs as $cfg) {
    if (file_exists($cfg)) {
        require_once $cfg;
        $configLoaded = true;
        break;
    }
}

// مقادیر پیش‌فرض اگر config پیدا نشد
if (!defined('SITE_URL'))  define('SITE_URL',  '');
if (!defined('ADMIN_URL')) define('ADMIN_URL', SITE_URL . '/admin');

// ==================== ۳. شروع سشن ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== ۴. بررسی لاگین ====================
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['username']);

if (!$isLoggedIn) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/admin/';

    // پاسخ AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthorized', 'redirect' => '/admin/login.php']);
        exit;
    }

    header('Location: ' . SITE_URL . '/admin/login.php');
    exit;
}

// ==================== ۵. انقضای سشن ====================
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/admin/login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

// ==================== ۶. لود کلاس‌های امنیتی ====================
foreach (['input_validator.php', 'fim.php'] as $f) {
    $p = __DIR__ . '/includes/' . $f;
    if (file_exists($p)) require_once $p;
}

<?php
/**
 * PartoCMS - Admin Logout
 * خروج کامل: پاک کردن session + cookie + ریدایرکت
 */
require_once __DIR__ . '/../config.php';

// ==================== ۱. پاک کردن تمام داده‌های session ====================
$_SESSION = [];

// ==================== ۲. حذف کوکی session از مرورگر ====================
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ==================== ۳. نابود کردن session در سرور ====================
session_destroy();

// ==================== ۴. ریدایرکت به صفحه لاگین ====================
$loginUrl = defined('SITE_URL') ? SITE_URL . '/admin/login.php?logout=1' : 'login.php?logout=1';

if (!headers_sent()) {
    header('Location: ' . $loginUrl);
    exit;
} else {
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' 
        . htmlspecialchars($loginUrl) . '"></head><body></body></html>';
    exit;
}

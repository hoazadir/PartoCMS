<?php
/**
 * PartoCMS - لایه امنیتی سطح ۱ (نسخه سازگار با Termux + HTTP/HTTPS)
 */

// ==================== ۱. خطاها ====================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// ==================== ۲. مسیر پروژه ====================
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));
}

// ==================== ۳. Session Hardening (قبل از session_start) ====================
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_strict_mode', 1);
    @ini_set('session.gc_maxlifetime', 7200);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', 1);
        @ini_set('session.cookie_samesite', 'Strict');
    } else {
        @ini_set('session.cookie_samesite', 'Lax');
    }
}

// ==================== ۴. Security Headers ====================
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "img-src 'self' data: https:; "
         . "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "connect-src 'self' https:; "
         . "frame-ancestors 'self';";
    header("Content-Security-Policy: " . $csp);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}
?>

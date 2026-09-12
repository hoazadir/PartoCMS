<?php
// config.php

// ==================== تنظیمات دیتابیس ====================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'grapesjs_cms');
define('DB_USER', 'root');
define('DB_PASS', '@PASS757ho@');
define('DB_CHARSET', 'utf8mb4');

// ==================== تشخیص خودکار آدرس سایت ====================
if (!defined('SITE_URL')) {
    $protocol = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    ) {
        $protocol = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = '';

    if (preg_match('#/(admin|modules|user|api)(/.*)?$#', $scriptDir)) {
        $scriptDir = preg_replace('#/(admin|modules|user|api)(/.*)?$#', '', $scriptDir);
    } elseif (strpos($scriptDir, '/modules/') !== false) {
        $scriptDir = preg_replace('#/modules/.*$#', '', $scriptDir);
    } elseif (strpos($scriptDir, '/admin/') !== false) {
        $scriptDir = preg_replace('#/admin/.*$#', '', $scriptDir);
    }

    $basePath = rtrim($scriptDir, '/');
    if ($basePath === '/' || $basePath === '.') $basePath = '';

    define('SITE_URL', $protocol . '://' . $host . $basePath);
}

define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// ==================== شروع سشن ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== اتصال به دیتابیس ====================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("خطا در اتصال به دیتابیس: " . $e->getMessage());
        }
    }
    return $pdo;
}

// ==================== بررسی احراز هویت ====================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

// ==================== دریافت تنظیمات ====================
function getSetting($key, $default = '') {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// ==================== هدرهای JSON ====================
function jsonHeader() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// ==================== پاسخ JSON ====================
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== بررسی دسترسی ادمین برای API ====================
function requireAdminAPI() {
    if (!isAdmin()) {
        jsonResponse(['status' => 'error', 'message' => 'دسترسی غیرمجاز'], 401);
    }
}

// ==================== Autoload helpers ====================
require_once __DIR__ . '/includes/ModuleManager.php';
require_once __DIR__ . '/includes/Permissions.php';
require_once __DIR__ . '/includes/MenuManager.php';
require_once __DIR__ . '/includes/TemplateRenderer.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Seo.php';
require_once __DIR__ . '/includes/Backup.php';

// ==================== Helper توابع سراسری ====================
function getModuleManager() {
    static $mm = null;
    if ($mm === null) $mm = new ModuleManager(getDB());
    return $mm;
}

function getPermissions() {
    static $p = null;
    if ($p === null) $p = new Permissions(getDB());
    return $p;
}

function getMenuManager() {
    static $mm = null;
    if ($mm === null) $mm = new MenuManager(getDB());
    return $mm;
}

function getTemplateRenderer() {
    static $tr = null;
    if ($tr === null) $tr = new TemplateRenderer(getDB());
    return $tr;
}

function getSecurity() {
    static $s = null;
    if ($s === null) $s = new Security(getDB());
    return $s;
}

function getSeo() {
    static $s = null;
    if ($s === null) $s = new Seo(getDB());
    return $s;
}

function getBackup() {
    static $b = null;
    if ($b === null) $b = new Backup(getDB());
    return $b;
}

/**
 * تابع کمکی برای رندر منو در قالب
 */
function renderMenu($slug, $class = 'main-menu', $itemClass = '') {
    try {
        return getMenuManager()->render($slug, $class, $itemClass);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * تابع کمکی برای رندر قالب با محتوای داینامیک
 */
function renderTemplate($templateId, $postId = null) {
    try {
        return getTemplateRenderer()->render($templateId, $postId);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * بررسی دسترسی کاربر به ماژول
 */
function canAccessModule($slug) {
    if (!isLoggedIn()) return false;
    try {
        return getModuleManager()->canCurrentUserAccess($slug);
    } catch (Exception $e) {
        return true; // Fallback
    }
}

/**
 * چک اجباری دسترسی ماژول
 */
function requireModule($slug) {
    if (!canAccessModule($slug)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>ماژول غیرفعال</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
            <style>body { font-family: Tahoma, sans-serif; background: #f4f6f9; }</style>
        </head>
        <body>
            <div style="text-align:center; padding:100px 20px; min-height:100vh;">
                <div style="font-size:80px;">🔒</div>
                <h1 style="color:#e74c3c;">ماژول غیرفعال</h1>
                <p style="color:#7f8c8d;">این ماژول یا غیرفعال است یا شما دسترسی ندارید.</p>
                <p style="color:#95a5a6; font-size:13px;">ماژول: <code><?= htmlspecialchars($slug) ?></code></p>
                <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-primary mt-3">بازگشت به داشبورد</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

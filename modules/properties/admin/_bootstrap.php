<?php
/**
 * PartoCMS — Properties Admin Bootstrap
 * لود سرویس، session، CSRF و توابع کمکی ادمین
 */

declare(strict_types=1);

// لود config اصلی پروژه (getDB و …)
$rootConfig = dirname(__DIR__, 3) . '/config.php';
if (is_file($rootConfig)) {
    require_once $rootConfig;
}

// لود bootstrap ماژول (autoloader + properties_service)
require_once dirname(__DIR__) . '/bootstrap.php';

// شروع session در صورت نیاز
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── CSRF ────────────────────────────────────────────────────
if (!function_exists('properties_csrf_token')) {
    function properties_csrf_token(): string {
        if (empty($_SESSION['properties_csrf'])) {
            $_SESSION['properties_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['properties_csrf'];
    }
}

if (!function_exists('properties_csrf_verify')) {
    function properties_csrf_verify(?string $token): bool {
        return is_string($token)
            && !empty($_SESSION['properties_csrf'])
            && hash_equals($_SESSION['properties_csrf'], $token);
    }
}

// ─── URL helpers ─────────────────────────────────────────────
if (!function_exists('properties_admin_url')) {
    function properties_admin_url(string $page = ''): string {
        $base = '/modules/properties/admin/';
        return $base . ltrim($page, '/');
    }
}

if (!function_exists('properties_asset_url')) {
    function properties_asset_url(string $path = ''): string {
        return '/modules/properties/assets/' . ltrim($path, '/');
    }
}

// ─── Flash messages ──────────────────────────────────────────
if (!function_exists('properties_flash')) {
    function properties_flash(string $type, string $message): void {
        $_SESSION['properties_flash'] = ['type' => $type, 'msg' => $message];
    }
}

if (!function_exists('properties_flash_pull')) {
    function properties_flash_pull(): ?array {
        $f = $_SESSION['properties_flash'] ?? null;
        unset($_SESSION['properties_flash']);
        return $f;
    }
}

// ─── Redirect ────────────────────────────────────────────────
if (!function_exists('properties_redirect')) {
    function properties_redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never {
        if ($flashType !== null && $flashMsg !== null) {
            properties_flash($flashType, $flashMsg);
        }
        header('Location: ' . $url);
        exit;
    }
}

// ─── Escape helper ───────────────────────────────────────────
if (!function_exists('properties_e')) {
    function properties_e(mixed $v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ─── اطمینان از دسترسی به سرویس ───────────────────────────────
$GLOBALS['properties_service'] = properties_service();

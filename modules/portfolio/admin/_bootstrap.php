<?php
/**
 * PartoCMS — Portfolio Admin Bootstrap
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../bootstrap.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$GLOBALS['portfolio_service'] = portfolio_service();

if (!function_exists('t_esc')) {
    function t_esc(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('portfolio_flash')) {
    function portfolio_flash(?string $type = null, ?string $message = null): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($type !== null && $message !== null) {
            $_SESSION['portfolio_flash'] = ['type' => $type, 'message' => $message];
            return [];
        }
        $flash = $_SESSION['portfolio_flash'] ?? [];
        unset($_SESSION['portfolio_flash']);
        return $flash;
    }
}

if (!function_exists('portfolio_csrf_token')) {
    function portfolio_csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['portfolio_csrf'])) {
            $_SESSION['portfolio_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['portfolio_csrf'];
    }
}

if (!function_exists('portfolio_csrf_field')) {
    function portfolio_csrf_field(): string {
        return '<input type="hidden" name="_csrf" value="' . portfolio_csrf_token() . '">';
    }
}

if (!function_exists('portfolio_csrf_verify')) {
    function portfolio_csrf_verify(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION['portfolio_csrf']) && hash_equals($_SESSION['portfolio_csrf'], $token ?? '');
    }
}

if (!function_exists('portfolio_status_badge')) {
    function portfolio_status_badge(string $status): string {
        $map = [
            'published' => ['✅', 'منتشرشده', 'success'],
            'hidden'    => ['🙈', 'مخفی', 'secondary'],
        ];
        $info = $map[$status] ?? ['❓', $status, 'secondary'];
        return sprintf('<span class="badge bg-%s">%s %s</span>', $info[2], $info[0], t_esc($info[1]));
    }
}

if (!function_exists('portfolio_redirect')) {
    function portfolio_redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never {
        if ($flashType !== null && $flashMsg !== null) {
            portfolio_flash($flashType, $flashMsg);
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('portfolio_admin_url')) {
    function portfolio_admin_url(string $page = ''): string {
        return rtrim(ADMIN_URL, '/') . '/../modules/portfolio/admin/' . ltrim($page, '/');
    }
}

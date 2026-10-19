<?php
/**
 * PartoCMS — Services Admin Bootstrap
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../bootstrap.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$GLOBALS['services_service'] = services_service();

if (!function_exists('t_esc')) {
    function t_esc(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('services_flash')) {
    function services_flash(?string $type = null, ?string $message = null): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ($type !== null && $message !== null) {
            $_SESSION['services_flash'] = ['type' => $type, 'message' => $message];
            return [];
        }
        $flash = $_SESSION['services_flash'] ?? [];
        unset($_SESSION['services_flash']);
        return $flash;
    }
}

if (!function_exists('services_csrf_token')) {
    function services_csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['services_csrf'])) {
            $_SESSION['services_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['services_csrf'];
    }
}

if (!function_exists('services_csrf_field')) {
    function services_csrf_field(): string {
        return '<input type="hidden" name="_csrf" value="' . services_csrf_token() . '">';
    }
}

if (!function_exists('services_csrf_verify')) {
    function services_csrf_verify(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION['services_csrf']) && hash_equals($_SESSION['services_csrf'], $token ?? '');
    }
}

if (!function_exists('services_status_badge')) {
    function services_status_badge(string $status): string {
        $map = [
            'published' => ['✅', 'منتشرشده', 'success'],
            'hidden'    => ['🙈', 'مخفی', 'secondary'],
        ];
        $info = $map[$status] ?? ['❓', $status, 'secondary'];
        return sprintf('<span class="badge bg-%s">%s %s</span>', $info[2], $info[0], t_esc($info[1]));
    }
}

if (!function_exists('services_redirect')) {
    function services_redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never {
        if ($flashType !== null && $flashMsg !== null) {
            services_flash($flashType, $flashMsg);
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('services_admin_url')) {
    function services_admin_url(string $page = ''): string {
        return rtrim(ADMIN_URL, '/') . '/../modules/services/admin/' . ltrim($page, '/');
    }
}

if (!function_exists('services_page_url')) {
    function services_page_url(array $params, int $page): string {
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}

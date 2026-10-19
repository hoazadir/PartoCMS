<?php
/**
 * PartoCMS — Team Admin Bootstrap
 * Auth gate + global service + helpers
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../bootstrap.php';

// Auth gate
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

// Global service accessor
$GLOBALS['team_service'] = team_service();

// ─────────────────────────────────────────────────────────────
// Helpers (self-contained, prevents conflicts)
// ─────────────────────────────────────────────────────────────

if (!function_exists('t_esc')) {
    function t_esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('team_flash')) {
    function team_flash(?string $type = null, ?string $message = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($type !== null && $message !== null) {
            $_SESSION['team_flash'] = ['type' => $type, 'message' => $message];
            return [];
        }
        $flash = $_SESSION['team_flash'] ?? [];
        unset($_SESSION['team_flash']);
        return $flash;
    }
}

if (!function_exists('team_csrf_token')) {
    function team_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['team_csrf'])) {
            $_SESSION['team_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['team_csrf'];
    }
}

if (!function_exists('team_csrf_field')) {
    function team_csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . team_csrf_token() . '">';
    }
}

if (!function_exists('team_csrf_verify')) {
    function team_csrf_verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['team_csrf'])
            && hash_equals($_SESSION['team_csrf'], $token ?? '');
    }
}

if (!function_exists('team_status_badge')) {
    function team_status_badge(string $status): string
    {
        $map = [
            'published' => ['✅', 'منتشرشده', 'success'],
            'hidden'    => ['🙈', 'مخفی', 'secondary'],
        ];
        $info = $map[$status] ?? ['❓', $status, 'secondary'];
        return sprintf(
            '<span class="badge bg-%s">%s %s</span>',
            $info[2],
            $info[0],
            t_esc($info[1])
        );
    }
}

if (!function_exists('team_redirect')) {
    function team_redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never
    {
        if ($flashType !== null && $flashMsg !== null) {
            team_flash($flashType, $flashMsg);
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('team_url')) {
    function team_url(string $page = ''): string
    {
        $base = rtrim(ADMIN_URL, '/') . '/../modules/team/admin';
        return $page === '' ? $base . '/index.php' : $base . '/' . ltrim($page, '/');
    }
}

if (!function_exists('team_admin_url')) {
    function team_admin_url(string $page = ''): string
    {
        return rtrim(ADMIN_URL, '/') . '/../modules/team/admin/' . ltrim($page, '/');
    }
}

if (!function_exists('team_page_url')) {
    function team_page_url(array $params, int $page): string
    {
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}

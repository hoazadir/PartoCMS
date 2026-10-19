<?php
/**
 * PartoCMS — Testimonials Admin Bootstrap
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

// ─────────────────────────────────────────────────────────────
// Auth gate
// ─────────────────────────────────────────────────────────────
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

// ─────────────────────────────────────────────────────────────
// Global service accessor
// ─────────────────────────────────────────────────────────────
$GLOBALS['testimonials_service'] = testimonials_service();

// ─────────────────────────────────────────────────────────────
// Helpers (self-contained — اگر از قبل وجود دارند، تعریف نمی‌شوند)
// ─────────────────────────────────────────────────────────────

// ESC — escape برای خروجی HTML
if (!function_exists('t_esc')) {
    function t_esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Flash messages
if (!function_exists('testimonials_flash')) {
    function testimonials_flash(?string $type = null, ?string $message = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($type !== null && $message !== null) {
            $_SESSION['testimonials_flash'] = ['type' => $type, 'message' => $message];
            return [];
        }

        $flash = $_SESSION['testimonials_flash'] ?? [];
        unset($_SESSION['testimonials_flash']);
        return $flash;
    }
}

// CSRF
if (!function_exists('testimonials_csrf_token')) {
    function testimonials_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['testimonials_csrf'])) {
            $_SESSION['testimonials_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['testimonials_csrf'];
    }
}

if (!function_exists('testimonials_csrf_field')) {
    function testimonials_csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . testimonials_csrf_token() . '">';
    }
}

if (!function_exists('testimonials_csrf_verify')) {
    function testimonials_csrf_verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['testimonials_csrf'])
            && hash_equals($_SESSION['testimonials_csrf'], $token ?? '');
    }
}

// Status badge
if (!function_exists('testimonials_status_badge')) {
    function testimonials_status_badge(string $status): string
    {
        $map = [
            'pending'   => ['⏳', 'در انتظار', 'warning'],
            'published' => ['✅', 'منتشرشده', 'success'],
            'rejected'  => ['❌', 'رد شده', 'danger'],
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

// Stars
if (!function_exists('testimonials_stars')) {
    function testimonials_stars(?int $rating): string
    {
        if ($rating === null) {
            return '<span class="text-muted">—</span>';
        }
        $rating = max(0, min(5, $rating));
        return '<span class="text-warning" title="' . $rating . ' از ۵">'
            . str_repeat('★', $rating)
            . str_repeat('☆', 5 - $rating)
            . '</span>';
    }
}

// Redirect helper
if (!function_exists('testimonials_redirect')) {
    function testimonials_redirect(string $url, ?string $flashType = null, ?string $flashMsg = null): never
    {
        if ($flashType !== null && $flashMsg !== null) {
            testimonials_flash($flashType, $flashMsg);
        }
        header('Location: ' . $url);
        exit;
    }
}

// Pagination URL helper
if (!function_exists('testimonials_page_url')) {
    function testimonials_page_url(array $params, int $page): string
    {
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}

// ─────────────────────────────────────────────────────────────
// URL helpers
// ─────────────────────────────────────────────────────────────
if (!function_exists('testimonials_url')) {
    /**
     * ساخت URL نسبی به admin ماژول
     *
     * @param string $page مثل 'index.php', 'create.php', 'edit.php?id=5'
     */
    function testimonials_url(string $page = ''): string
    {
        $base = rtrim(ADMIN_URL, '/') . '/../modules/testimonials/admin';
        if ($page === '') {
            return $base . '/index.php';
        }
        return $base . '/' . ltrim($page, '/');
    }
}

if (!function_exists('testimonials_admin_url')) {
    /**
     * نسخه absolute برای استفاده در header('Location: ...')
     */
    function testimonials_admin_url(string $page = ''): string
    {
        return rtrim(ADMIN_URL, '/') . '/../modules/testimonials/admin/' . ltrim($page, '/');
    }
}

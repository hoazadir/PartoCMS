<?php
/**
 * PartoCMS - FAQ Admin Bootstrap
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

use PartoCMS\Faq\Infrastructure\Persistence\PdoFaqRepository;
use PartoCMS\Faq\Domain\Service\FaqService;

// Global accessor
global $faq_service;

try {
    $pdo = getDB();
    $repo = new PdoFaqRepository($pdo);
    $faq_service = new FaqService($repo);
} catch (Throwable $e) {
    die('خطا در بارگذاری ماژول: ' . $e->getMessage());
}

// Helper for URLs
if (!function_exists('faq_url')) {
    function faq_url(string $path = ''): string {
        return SITE_URL . '/modules/faq/admin/' . ltrim($path, '/');
    }
}

// Helper for flash messages
if (!function_exists('faq_flash')) {
    function faq_flash(?string $message = null, string $type = 'success'): ?array {
        if ($message !== null) {
            $_SESSION['faq_flash'] = ['message' => $message, 'type' => $type];
            return null;
        }
        if (isset($_SESSION['faq_flash'])) {
            $flash = $_SESSION['faq_flash'];
            unset($_SESSION['faq_flash']);
            return $flash;
        }
        return null;
    }
}

$siteName = getSetting('site_name', 'PartoCMS');

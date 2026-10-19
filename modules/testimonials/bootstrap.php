<?php
/**
 * PartoCMS — Testimonials Module
 * Bootstrap: autoloader + service factory
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────
// Autoloader (PSR-4 style, minimal — بدون نیاز به Composer)
// ─────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $prefix = 'PartoCMS\\Testimonials\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
if (!function_exists('testimonials_config')) {
    /**
     * بارگذاری تنظیمات ماژول
     */
    function testimonials_config(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }
        return $config;
    }
}

if (!function_exists('testimonials_service')) {
    /**
     * ساخت TestimonialService (singleton)
     */
    function testimonials_service(): \PartoCMS\Testimonials\Domain\Service\TestimonialService
    {
        static $service = null;
        if ($service === null) {
            $pdo = getDB();
            $repo = new \PartoCMS\Testimonials\Infrastructure\Persistence\PdoTestimonialRepository($pdo);
            $service = new \PartoCMS\Testimonials\Domain\Service\TestimonialService($repo);
        }
        return $service;
    }
}

if (!function_exists('testimonials_repository')) {
    /**
     * ساخت Repository (singleton)
     */
    function testimonials_repository(): \PartoCMS\Testimonials\Domain\Repository\TestimonialRepositoryInterface
    {
        static $repo = null;
        if ($repo === null) {
            $repo = new \PartoCMS\Testimonials\Infrastructure\Persistence\PdoTestimonialRepository(getDB());
        }
        return $repo;
    }
}

if (!function_exists('testimonials_setting')) {
    /**
     * خواندن یک تنظیم از جدول settings با پیشوند testimonials_
     */
    function testimonials_setting(string $key, mixed $default = null): mixed
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $defaults = testimonials_config()['defaults'];
        $fallback = $default ?? $defaults[$key] ?? null;

        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute(['testimonials_' . $key]);
            $value = $stmt->fetchColumn();

            if ($value === false) {
                $cache[$key] = $fallback;
                return $fallback;
            }

            // تبدیل مقادیر bool و int
            if ($value === '1' || $value === '0') {
                $result = (bool) $value;
            } elseif (is_numeric($value)) {
                $result = (int) $value;
            } else {
                $result = $value;
            }

            $cache[$key] = $result;
            return $result;
        } catch (\PDOException $e) {
            $cache[$key] = $fallback;
            return $fallback;
        }
    }
}

if (!function_exists('testimonials_clear_cache')) {
    /**
     * پاک کردن کش تنظیمات (بعد از تغییر در settings)
     */
    function testimonials_clear_cache(): void
    {
        // کش static در closure قابل دسترسی نیست — ولی در هر request جدید صفر می‌شود
        // برای invalidate واقعی، از راه‌اندازی مجدد request استفاده کنید
    }
}

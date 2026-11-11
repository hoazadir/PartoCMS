<?php
/**
 * PartoCMS — Properties Module
 * Bootstrap: autoloader + service factory
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'PartoCMS\\Properties\\';
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

if (!function_exists('properties_config')) {
    function properties_config(): array {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }
        return $config;
    }
}

if (!function_exists('properties_service')) {
    function properties_service(): \PartoCMS\Properties\Domain\Service\PropertyService {
        static $service = null;
        if ($service === null) {
            $pdo = getDB();
            $repo = new \PartoCMS\Properties\Infrastructure\Persistence\PdoPropertyRepository($pdo);
            $service = new \PartoCMS\Properties\Domain\Service\PropertyService($repo);
        }
        return $service;
    }
}

if (!function_exists('properties_repository')) {
    function properties_repository(): \PartoCMS\Properties\Domain\Repository\PropertyRepositoryInterface {
        static $repo = null;
        if ($repo === null) {
            $repo = new \PartoCMS\Properties\Infrastructure\Persistence\PdoPropertyRepository(getDB());
        }
        return $repo;
    }
}

if (!function_exists('properties_setting')) {
    function properties_setting(string $key, mixed $default = null): mixed {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $defaults = properties_config()['defaults'];
        $fallback = $default ?? $defaults[$key] ?? null;
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute(['properties_' . $key]);
            $value = $stmt->fetchColumn();
            if ($value === false) {
                $cache[$key] = $fallback;
                return $fallback;
            }
            $result = $value === '1' ? true : ($value === '0' ? false : (is_numeric($value) ? (int) $value : $value));
            $cache[$key] = $result;
            return $result;
        } catch (\PDOException $e) {
            $cache[$key] = $fallback;
            return $fallback;
        }
    }
}

if (!function_exists('properties_settings_service')) {
    function properties_settings_service(): \PartoCMS\Properties\Domain\Service\PropertySettingsService {
        static $svc = null;
        if ($svc === null) {
            $svc = new \PartoCMS\Properties\Domain\Service\PropertySettingsService(getDB(), properties_config());
        }
        return $svc;
    }
}

if (!function_exists('properties_get_settings')) {
    /**
     * خواندن تنظیمات ویجت (برای index.php و ویجت)
     * @return array<string,mixed>
     */
    function properties_get_settings(string $context = 'widget'): array {
        return properties_settings_service()->all();
    }
}

<?php
/**
 * PartoCMS — Services Module
 * Bootstrap: autoloader + service factory
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'PartoCMS\\Services\\';
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

if (!function_exists('services_config')) {
    function services_config(): array {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }
        return $config;
    }
}

if (!function_exists('services_service')) {
    function services_service(): \PartoCMS\Services\Domain\Service\ServiceService {
        static $service = null;
        if ($service === null) {
            $pdo = getDB();
            $repo = new \PartoCMS\Services\Infrastructure\Persistence\PdoServiceRepository($pdo);
            $service = new \PartoCMS\Services\Domain\Service\ServiceService($repo);
        }
        return $service;
    }
}

if (!function_exists('services_repository')) {
    function services_repository(): \PartoCMS\Services\Domain\Repository\ServiceRepositoryInterface {
        static $repo = null;
        if ($repo === null) {
            $repo = new \PartoCMS\Services\Infrastructure\Persistence\PdoServiceRepository(getDB());
        }
        return $repo;
    }
}

if (!function_exists('services_setting')) {
    function services_setting(string $key, mixed $default = null): mixed {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $defaults = services_config()['defaults'];
        $fallback = $default ?? $defaults[$key] ?? null;
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute(['services_' . $key]);
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

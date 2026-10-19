<?php
/**
 * PartoCMS — Team Module
 * Bootstrap: autoloader + service factory
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

// PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'PartoCMS\\Team\\';
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

// Helpers
if (!function_exists('team_config')) {
    function team_config(): array
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/config.php';
        }
        return $config;
    }
}

if (!function_exists('team_service')) {
    function team_service(): \PartoCMS\Team\Domain\Service\TeamService
    {
        static $service = null;
        if ($service === null) {
            $pdo = getDB();
            $repo = new \PartoCMS\Team\Infrastructure\Persistence\PdoTeamMemberRepository($pdo);
            $service = new \PartoCMS\Team\Domain\Service\TeamService($repo);
        }
        return $service;
    }
}

if (!function_exists('team_repository')) {
    function team_repository(): \PartoCMS\Team\Domain\Repository\TeamMemberRepositoryInterface
    {
        static $repo = null;
        if ($repo === null) {
            $repo = new \PartoCMS\Team\Infrastructure\Persistence\PdoTeamMemberRepository(getDB());
        }
        return $repo;
    }
}

if (!function_exists('team_setting')) {
    function team_setting(string $key, mixed $default = null): mixed
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $defaults = team_config()['defaults'];
        $fallback = $default ?? $defaults[$key] ?? null;

        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute(['team_' . $key]);
            $value = $stmt->fetchColumn();

            if ($value === false) {
                $cache[$key] = $fallback;
                return $fallback;
            }

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

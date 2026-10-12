<?php
/**
 * PartoCMS - Newsletter Module Bootstrap
 *
 * - Registers a PSR-4-like autoloader for this module's src/
 * - Initializes the DI container singleton
 * - Returns module config array
 *
 * Must be included once, before any module class is used.
 *
 * @package PartoCMS\Newsletter
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter;

// ─── Path constants ───
if (!defined('NEWSLETTER_MODULE_PATH')) {
    define('NEWSLETTER_MODULE_PATH', __DIR__);
}
if (!defined('NEWSLETTER_SRC_PATH')) {
    define('NEWSLETTER_SRC_PATH', __DIR__ . '/src');
}

// ─── PSR-4-like autoloader for module classes ───
spl_autoload_register(static function (string $class): void {
    $prefix  = 'PartoCMS\\Newsletter\\';
    $baseDir = NEWSLETTER_SRC_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ─── Initialize container singleton ───
Support\Container::getInstance();

// ─── Return module config ───
return require __DIR__ . '/config.php';

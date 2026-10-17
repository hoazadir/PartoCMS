<?php
/**
 * PartoCMS - FAQ Module Bootstrap
 */
declare(strict_types=1);

namespace PartoCMS\Faq;

if (!defined('FAQ_MODULE_PATH')) {
    define('FAQ_MODULE_PATH', __DIR__);
}
if (!defined('FAQ_SRC_PATH')) {
    define('FAQ_SRC_PATH', __DIR__ . '/src');
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'PartoCMS\\Faq\\';
    $baseDir = FAQ_SRC_PATH . '/';

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

return require __DIR__ . '/config.php';

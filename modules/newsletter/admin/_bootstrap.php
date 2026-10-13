<?php
/**
 * PartoCMS - Newsletter Admin Bootstrap
 *
 * Loads config + auth, wires services via DI container.
 * Required at the top of every admin page.
 *
 * @package PartoCMS\Newsletter\Admin
 */

declare(strict_types=1);

// ─── Project bootstrap ───
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// ─── Auth gate ───
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoCampaignRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSendLogRepository;
use PartoCMS\Newsletter\Infrastructure\Mailer\MailerFactory;
use PartoCMS\Newsletter\Domain\Service\SubscriptionService;
use PartoCMS\Newsletter\Domain\Service\CampaignService;
use PartoCMS\Newsletter\Domain\Service\SendService;
use PartoCMS\Newsletter\Support\Container;

// ─── Services wiring via DI container ───
$container = Container::getInstance();

// Global accessor for admin pages
global $nl_container;
$nl_container = $container;

$container->singleton(\PDO::class, fn() => getDB());

$container->singleton(
    PdoSubscriberRepository::class,
    fn($c) => new PdoSubscriberRepository($c->get(\PDO::class))
);

$container->singleton(
    PdoCampaignRepository::class,
    fn($c) => new PdoCampaignRepository($c->get(\PDO::class))
);

$container->singleton(
    PdoSendLogRepository::class,
    fn($c) => new PdoSendLogRepository($c->get(\PDO::class))
);

$container->singleton(
    MailerFactory::class,
    fn($c) => MailerFactory::fromDatabase($c->get(\PDO::class))
);

$container->singleton(
    SubscriptionService::class,
    fn($c) => new SubscriptionService(
        $c->get(PdoSubscriberRepository::class),
        doubleOptIn: false
    )
);

$container->singleton(
    CampaignService::class,
    fn($c) => new CampaignService(
        $c->get(PdoCampaignRepository::class),
        $c->get(PdoSubscriberRepository::class),
        $c->get(PdoSendLogRepository::class)
    )
);

$container->singleton(
    SendService::class,
    fn($c) => new SendService(
        $c->get(PdoCampaignRepository::class),
        $c->get(PdoSubscriberRepository::class),
        $c->get(PdoSendLogRepository::class),
        $c->get(MailerFactory::class)->build()
    )
);

// ─── Helper: URL ───
if (!function_exists('nl_admin_url')) {
    function nl_admin_url(string $path = ''): string
    {
        return SITE_URL . '/modules/newsletter/admin/' . ltrim($path, '/');
    }
}

// ─── Helper: flash messages ───
if (!function_exists('nl_flash')) {
    function nl_flash(?string $message = null, string $type = 'success'): ?array
    {
        if ($message !== null) {
            $_SESSION['nl_flash'] = ['message' => $message, 'type' => $type];
            return null;
        }
        if (isset($_SESSION['nl_flash'])) {
            $flash = $_SESSION['nl_flash'];
            unset($_SESSION['nl_flash']);
            return $flash;
        }
        return null;
    }
}

// ─── Helper: site name ───
$siteName = getSetting('site_name', 'PartoCMS');

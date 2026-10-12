<?php
/**
 * PartoCMS - Newsletter Module Configuration
 *
 * Central configuration for the newsletter module.
 * Returns an array consumed by bootstrap.php.
 *
 * @package PartoCMS\Newsletter
 */

declare(strict_types=1);

return [
    'version' => '1.0.0',

    /**
     * Database table names (allows prefixing later)
     */
    'tables' => [
        'subscribers' => 'newsletter_subscribers',
        'campaigns'   => 'newsletter_campaigns',
        'sends'       => 'newsletter_sends',
    ],

    /**
     * Default settings (can be overridden per-site in DB)
     */
    'defaults' => [
        'double_optin'   => false,
        'auto_confirm'   => true,
        'items_per_page' => 20,
        'max_batch_size' => 50,
        'token_length'   => 32,
        'queue_timeout'  => 300,
    ],

    /**
     * Rate limiting (prevent abuse)
     */
    'rate_limit' => [
        'subscribe_per_ip_per_hour'   => 5,
        'subscribe_per_email_per_day' => 3,
    ],

    /**
     * Email defaults
     */
    'email' => [
        'from_name'  => null,
        'from_email' => null,
        'reply_to'   => null,
        'charset'    => 'UTF-8',
        'encoding'   => '8bit',
    ],
];

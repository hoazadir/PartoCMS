<?php
/**
 * PartoCMS - Ads Module Menu
 * منوی ماژول تبلیغات
 */

return [
    'title'      => 'تبلیغات',
    'icon'       => '📢',
    'url'        => SITE_URL . '/modules/ads/admin/index.php',
    'menu_group' => 'marketing',
    'sort_order' => 40,
    'submenu'    => [
        ['title' => 'داشبورد',    'url' => SITE_URL . '/modules/ads/admin/index.php',        'icon' => '📊'],
        ['title' => 'تبلیغات',     'url' => SITE_URL . '/modules/ads/admin/ads.php',          'icon' => '🖼️'],
        ['title' => 'موقعیت‌ها',   'url' => SITE_URL . '/modules/ads/admin/ad-positions.php', 'icon' => '📐'],
        ['title' => 'کمپین‌ها',    'url' => SITE_URL . '/modules/ads/admin/ad-campaigns.php', 'icon' => '🎯'],
        ['title' => 'گزارش‌ها',    'url' => SITE_URL . '/modules/ads/admin/ad-reports.php',   'icon' => '📈'],
    ],
];

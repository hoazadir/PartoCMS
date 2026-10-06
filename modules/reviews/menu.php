<?php
/**
 * PartoCMS - Reviews Module Menu
 */

return [
    'title'      => 'نظرات',
    'icon'       => '⭐',
    'url'        => SITE_URL . '/modules/reviews/admin/index.php',
    'menu_group' => 'marketing',
    'sort_order' => 45,
    'submenu'    => [
        ['title' => 'همه نظرات',     'url' => SITE_URL . '/modules/reviews/admin/index.php',   'icon' => '📋'],
        ['title' => 'در انتظار تأیید', 'url' => SITE_URL . '/modules/reviews/admin/pending.php', 'icon' => '⏳'],
        ['title' => 'تنظیمات',        'url' => SITE_URL . '/modules/reviews/admin/settings.php','icon' => '⚙️'],
    ],
];

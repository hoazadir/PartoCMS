<?php
/**
 * PartoCMS - Comments Module Menu
 */

return [
    'title'      => 'دیدگاه‌ها',
    'icon'       => '💬',
    'url'        => SITE_URL . '/modules/comments/admin/index.php',
    'menu_group' => 'content',
    'sort_order' => 30,
    'submenu'    => [
        ['title' => 'همه دیدگاه‌ها',     'url' => SITE_URL . '/modules/comments/admin/index.php',                  'icon' => '📋'],
        ['title' => 'در انتظار تأیید',    'url' => SITE_URL . '/modules/comments/admin/index.php?status=pending',   'icon' => '⏳'],
    ],
];

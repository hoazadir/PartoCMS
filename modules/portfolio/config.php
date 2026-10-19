<?php
/**
 * PartoCMS — Portfolio Module
 * Config: defaults and constants
 */

declare(strict_types=1);

return [
    'name'        => 'نمونه‌کارها',
    'slug'        => 'portfolio',
    'version'     => '1.0.0',
    'description' => 'سیستم مدیریت نمونه‌کارها با معماری DDD، گالری تصاویر، تگ‌ها و ویجت فرانت‌اند.',

    'defaults' => [
        'items_per_page'    => 20,
        'show_thumbnail'    => true,
        'show_client'       => true,
        'show_category'     => true,
        'show_tags'         => true,
        'show_date'         => true,
        'default_status'    => 'published',
    ],

    'widget' => [
        'limit'    => 6,
        'columns'  => 3,
        'style'    => 'grid',
    ],

    'permissions' => [
        'portfolio.view',
        'portfolio.create',
        'portfolio.edit',
        'portfolio.delete',
        'portfolio.settings',
    ],
];

<?php
/**
 * PartoCMS — Services Module
 * Config: defaults and constants
 */

declare(strict_types=1);

return [
    'name'        => 'خدمات',
    'slug'        => 'services',
    'version'     => '1.0.0',
    'description' => 'سیستم مدیریت خدمات با معماری DDD، قیمت، ویژگی‌ها و ویجت فرانت‌اند.',
    
    'defaults' => [
        'items_per_page'    => 20,
        'show_icon'         => true,
        'show_price'        => true,
        'show_features'     => true,
        'show_category'     => true,
        'default_status'    => 'published',
    ],
    
    'widget' => [
        'limit'    => 6,
        'columns'  => 3,
        'style'    => 'grid',
    ],
    
    'permissions' => [
        'services.view',
        'services.create',
        'services.edit',
        'services.delete',
        'services.settings',
    ],
];

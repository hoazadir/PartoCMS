<?php
/**
 * PartoCMS — Properties Module
 * Config
 */

declare(strict_types=1);

return [
    'name'        => 'املاک',
    'slug'        => 'properties',
    'version'     => '1.0.0',
    'description' => 'سیستم مدیریت املاک با DDD، TinyMCE، گالری، نقشه و فیلتر پیشرفته.',

    'defaults' => [
        'items_per_page'   => 12,
        'show_thumbnail'   => true,
        'show_price'       => true,
        'show_area'        => true,
        'show_bedrooms'    => true,
        'show_address'     => true,
        'require_approval' => true,
        'default_status'   => 'pending',
    ],

    'widget' => [
        'limit'    => 6,
        'columns'  => 3,
        'style'    => 'grid',
    ],

    'permissions' => [
        'properties.view',
        'properties.create',
        'properties.edit',
        'properties.delete',
        'properties.approve',
        'properties.settings',
    ],
];

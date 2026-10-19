<?php
/**
 * PartoCMS — Team Module
 * Config: defaults and constants
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

return [
    'name'        => 'تیم ما',
    'slug'        => 'team',
    'version'     => '1.0.0',
    'description' => 'سیستم مدیریت اعضای تیم با پشتیبانی از عکس، بیوگرافی و شبکه‌های اجتماعی.',

    'defaults' => [
        'items_per_page'    => 20,
        'show_photo'        => true,
        'show_position'     => true,
        'show_department'   => true,
        'show_bio'          => true,
        'show_social'       => true,
        'default_status'    => 'published',
    ],

    'widget' => [
        'limit'    => 6,
        'columns'  => 3,
        'style'    => 'grid',
    ],

    'permissions' => [
        'team.view',
        'team.create',
        'team.edit',
        'team.delete',
        'team.settings',
    ],

    'i18n' => [
        'fa-IR' => [
            'title' => 'تیم ما',
            'position' => 'سمت',
            'department' => 'دپارتمان',
            'bio' => 'درباره',
            'contact' => 'ارتباط',
        ],
        'en-US' => [
            'title' => 'Our Team',
            'position' => 'Position',
            'department' => 'Department',
            'bio' => 'About',
            'contact' => 'Contact',
        ],
    ],
];

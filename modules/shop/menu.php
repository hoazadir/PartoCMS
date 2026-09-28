<?php
/**
 * PartoCMS - Shop Module Menu
 * منوی ماژول فروشگاه
 */

return [
    'title'      => 'فروشگاه',
    'icon'       => '🛒',
    'url'        => SITE_URL . '/modules/shop/admin/index.php',
    'menu_group' => 'commerce',
    'sort_order' => 30,
    'submenu'    => [
        [
            'title' => 'داشبورد',
            'url'   => SITE_URL . '/modules/shop/admin/index.php',
            'icon'  => '📊',
        ],
        [
            'title' => 'محصولات',
            'url'   => SITE_URL . '/modules/shop/admin/products.php',
            'icon'  => '📦',
        ],
        [
            'title' => 'دسته‌بندی‌ها',
            'url'   => SITE_URL . '/modules/shop/admin/categories.php',
            'icon'  => '🏷️',
        ],
        [
            'title' => 'سفارشات',
            'url'   => SITE_URL . '/modules/shop/admin/orders.php',
            'icon'  => '🧾',
        ],
        [
            'title' => 'مشتریان',
            'url'   => SITE_URL . '/modules/shop/admin/customers.php',
            'icon'  => '👥',
        ],
        [
            'title' => 'کوپن‌ها',
            'url'   => SITE_URL . '/modules/shop/admin/coupons.php',
            'icon'  => '🎟️',
        ],
        [
            'title' => 'روش‌های ارسال',
            'url'   => SITE_URL . '/modules/shop/admin/shipping.php',
            'icon'  => '🚚',
        ],
        [
            'title' => 'گزارش فروش',
            'url'   => SITE_URL . '/modules/shop/admin/reports.php',
            'icon'  => '📈',
        ],
        [
            'title' => 'تنظیمات',
            'url'   => SITE_URL . '/modules/shop/admin/settings.php',
            'icon'  => '⚙️',
        ],
    ],
];

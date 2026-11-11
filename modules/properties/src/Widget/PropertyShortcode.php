<?php
declare(strict_types=1);

namespace PartoCMS\Properties\Widget;

final class PropertyShortcode
{
    /**
     * ثبت شورت‌کد [properties ...]
     * مثال: [properties limit="6" style="grid" columns="3" deal_type="sale" city="تهران" featured="1"]
     */
    public static function register(): void
    {
        if (function_exists('add_shortcode')) {
            add_shortcode('properties', [self::class, 'render']);
        } elseif (function_exists('do_shortcode')) {
            // پشتیبانی از سیستم شورت‌کدهای سفارشی
        }
    }

    public static function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit'     => '',
            'style'     => '',
            'columns'   => '',
            'city'      => '',
            'deal_type' => '',
            'featured'  => '',
        ], $atts, 'properties');

        $options = [];
        if ($atts['limit'] !== '')     $options['limit'] = (int)$atts['limit'];
        if ($atts['style'] !== '')     $options['style'] = $atts['style'];
        if ($atts['columns'] !== '')   $options['columns'] = (int)$atts['columns'];
        if ($atts['city'] !== '')      $options['city'] = $atts['city'];
        if ($atts['deal_type'] !== '') $options['deal_type'] = $atts['deal_type'];
        if ($atts['featured'] !== '')  $options['featured'] = (bool)$atts['featured'];

        return PropertyWidget::render($options);
    }
}

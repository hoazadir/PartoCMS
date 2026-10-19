<?php
/**
 * PartoCMS - Testimonials Shortcode
 *
 * Shortcodes:
 *   [testimonials]                       all published
 *   [testimonials limit="6"]             limit
 *   [testimonials style="slider"]        grid|slider
 *   [testimonials columns="2"]           columns
 *   [testimonials featured="true"]       featured only
 *   [testimonials rating="4"]            min rating
 *   [testimonials show_form="true"]      include submit form
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Widget;

final class TestimonialShortcode
{
    public static function parse(string $content): string
    {
        if ($content === '' || stripos($content, '[testimonials') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/\[testimonials([^\]]*)\]/i',
            function (array $m): string {
                $attrs = self::parseAttrs($m[1]);
                return self::render($attrs);
            },
            $content
        );
    }

    public static function hasShortcode(string $content): bool
    {
        return stripos($content, '[testimonials') !== false;
    }

    private static function parseAttrs(string $raw): array
    {
        $out = [];
        if (preg_match_all('/(\w+)\s*=\s*["\']([^"\']*)["\']/', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $out[strtolower($pair[1])] = $pair[2];
            }
        }
        return $out;
    }

    private static function render(array $attrs): string
    {
        try {
            $options = self::mapAttrs($attrs);
            return TestimonialWidget::render($options);
        } catch (\Throwable $e) {
            error_log('TestimonialShortcode error: ' . $e->getMessage());
            return '<!-- testimonials shortcode error -->';
        }
    }

    private static function mapAttrs(array $attrs): array
    {
        $options = [];

        if (isset($attrs['limit'])) {
            $options['limit'] = max(1, (int) $attrs['limit']);
        }
        if (isset($attrs['columns'])) {
            $options['columns'] = max(1, min(4, (int) $attrs['columns']));
        }
        if (isset($attrs['style'])) {
            $style = strtolower($attrs['style']);
            $options['style'] = in_array($style, ['grid', 'slider'], true) ? $style : 'grid';
        }
        if (isset($attrs['featured'])) {
            $options['featured'] = self::toBool($attrs['featured']);
        }
        if (isset($attrs['rating'])) {
            $options['rating_min'] = max(0, min(5, (int) $attrs['rating']));
        }
        if (isset($attrs['show_form'])) {
            $options['show_form'] = self::toBool($attrs['show_form']);
        }
        if (isset($attrs['avatar'])) {
            $options['show_avatar'] = self::toBool($attrs['avatar']);
        }
        if (isset($attrs['company'])) {
            $options['show_company'] = self::toBool($attrs['company']);
        }
        if (isset($attrs['stars'])) {
            $options['show_rating'] = self::toBool($attrs['stars']);
        }

        return $options;
    }

    private static function toBool(string $value): bool
    {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

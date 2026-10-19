<?php
/**
 * PartoCMS — Services Module
 * Shortcode: ServiceShortcode
 *
 * Shortcodes:
 *   [services]                          all published services
 *   [services limit="6"]                limit
 *   [services style="slider"]           grid | slider
 *   [services columns="2"]              columns
 *   [services category="توسعه وب"]      filter by category
 *   [services featured="true"]          featured only
 *   [services price="false"]            hide price
 *   [services icon="false"]             hide icon
 */

declare(strict_types=1);

namespace PartoCMS\Services\Widget;

final class ServiceShortcode
{
    public static function parse(string $content): string
    {
        if ($content === '' || stripos($content, '[services') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/\[services([^\]]*)\]/i',
            function (array $m): string {
                $attrs = self::parseAttrs($m[1]);
                return self::render($attrs);
            },
            $content
        );
    }

    public static function hasShortcode(string $content): bool
    {
        return stripos($content, '[services') !== false;
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
            return ServiceWidget::render(self::mapAttrs($attrs));
        } catch (\Throwable $e) {
            error_log('ServiceShortcode error: ' . $e->getMessage());
            return '<!-- services shortcode error -->';
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
        if (isset($attrs['category'])) {
            $options['category'] = (string) $attrs['category'];
        }
        if (isset($attrs['featured'])) {
            $options['featured'] = self::toBool($attrs['featured']);
        }
        if (isset($attrs['icon'])) {
            $options['show_icon'] = self::toBool($attrs['icon']);
        }
        if (isset($attrs['price'])) {
            $options['show_price'] = self::toBool($attrs['price']);
        }
        if (isset($attrs['features'])) {
            $options['show_features'] = self::toBool($attrs['features']);
        }

        return $options;
    }

    private static function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

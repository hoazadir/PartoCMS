<?php
/**
 * PartoCMS — Portfolio Module
 * Shortcode: PortfolioShortcode
 *
 * Shortcodes:
 *   [portfolio]                          all published items
 *   [portfolio limit="6"]                limit
 *   [portfolio style="slider"]           grid | slider
 *   [portfolio columns="2"]              columns
 *   [portfolio category="طراحی وب"]      filter by category
 *   [portfolio featured="true"]          featured only
 *   [portfolio thumbnail="false"]        hide thumbnail
 *   [portfolio tags="false"]             hide tags
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Widget;

final class PortfolioShortcode
{
    public static function parse(string $content): string
    {
        if ($content === '' || stripos($content, '[portfolio') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/\[portfolio([^\]]*)\]/i',
            function (array $m): string {
                $attrs = self::parseAttrs($m[1]);
                return self::render($attrs);
            },
            $content
        );
    }

    public static function hasShortcode(string $content): bool
    {
        return stripos($content, '[portfolio') !== false;
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
            return PortfolioWidget::render(self::mapAttrs($attrs));
        } catch (\Throwable $e) {
            error_log('PortfolioShortcode error: ' . $e->getMessage());
            return '<!-- portfolio shortcode error -->';
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
        if (isset($attrs['thumbnail'])) {
            $options['show_thumbnail'] = self::toBool($attrs['thumbnail']);
        }
        if (isset($attrs['client'])) {
            $options['show_client'] = self::toBool($attrs['client']);
        }
        if (isset($attrs['tags'])) {
            $options['show_tags'] = self::toBool($attrs['tags']);
        }
        if (isset($attrs['date'])) {
            $options['show_date'] = self::toBool($attrs['date']);
        }
        if (isset($attrs['description'])) {
            $options['show_description'] = self::toBool($attrs['description']);
        }

        return $options;
    }

    private static function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

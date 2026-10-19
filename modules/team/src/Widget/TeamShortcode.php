<?php
/**
 * PartoCMS — Team Module
 * Shortcode: TeamShortcode
 *
 * Shortcodes:
 *   [team]                          all published members
 *   [team limit="6"]                limit
 *   [team style="slider"]           grid | slider
 *   [team columns="2"]              columns
 *   [team department="فنی"]         filter by department
 *   [team featured="true"]          featured only
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Widget;

final class TeamShortcode
{
    public static function parse(string $content): string
    {
        if ($content === '' || stripos($content, '[team') === false) {
            return $content;
        }

        return preg_replace_callback(
            '/\[team([^\]]*)\]/i',
            function (array $m): string {
                $attrs = self::parseAttrs($m[1]);
                return self::render($attrs);
            },
            $content
        );
    }

    public static function hasShortcode(string $content): bool
    {
        return stripos($content, '[team') !== false;
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
            return TeamWidget::render(self::mapAttrs($attrs));
        } catch (\Throwable $e) {
            error_log('TeamShortcode error: ' . $e->getMessage());
            return '<!-- team shortcode error -->';
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
        if (isset($attrs['department'])) {
            $options['department'] = (string) $attrs['department'];
        }
        if (isset($attrs['featured'])) {
            $options['featured'] = self::toBool($attrs['featured']);
        }
        if (isset($attrs['photo'])) {
            $options['show_photo'] = self::toBool($attrs['photo']);
        }
        if (isset($attrs['bio'])) {
            $options['show_bio'] = self::toBool($attrs['bio']);
        }
        if (isset($attrs['social'])) {
            $options['show_social'] = self::toBool($attrs['social']);
        }

        return $options;
    }

    private static function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}

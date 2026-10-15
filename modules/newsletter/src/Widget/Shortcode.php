<?php
/**
 * PartoCMS - Newsletter Shortcode Parser
 *
 * پارسر shortcode برای محتوای صفحات:
 *   [newsletter]              → فرم عضویت (کامل)
 *   [newsletter_form]         → فرم عضویت
 *   [newsletter_success]      → پیام موفقیت
 *
 * @package PartoCMS\Newsletter\Widget
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Widget;

final class Shortcode
{
    /**
     * پارس همه shortcodeها در یک محتوا
     */
    public static function parse(string $content, array $context = []): string
    {
        if ($content === '' || strpos($content, '[newsletter') === false) {
            return $content;
        }

        $pattern = '/\[(newsletter|newsletter_form|newsletter_success)([^\]]*)\]/i';

        return preg_replace_callback($pattern, function ($m) use ($context) {
            $tag   = strtolower($m[1]);
            $attrs = self::parseAttrs($m[2]);

            return self::render($tag, $attrs, $context);
        }, $content);
    }

    /**
     * پارس attributeهای shortcode
     */
    private static function parseAttrs(string $s): array
    {
        $out = [];
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $out[$a[1]] = $a[2];
            }
        }
        return $out;
    }

    /**
     * رندر یک tag
     */
    private static function render(string $tag, array $attrs, array $context): string
    {
        try {
            switch ($tag) {
                case 'newsletter':
                case 'newsletter_form':
                    return NewsletterWidget::renderForm([
                        'title'       => $attrs['title']       ?? '📮 عضویت در خبرنامه',
                        'subtitle'    => $attrs['subtitle']    ?? 'جدیدترین خبرها را در ایمیل خود دریافت کنید',
                        'button_text' => $attrs['button']      ?? '🚀 عضویت',
                        'placeholder' => $attrs['placeholder'] ?? 'ایمیل شما',
                        'source'      => $attrs['source']      ?? 'shortcode',
                        'show_name'   => ($attrs['show_name']  ?? '1') !== '0',
                        'class'       => $attrs['class']       ?? 'nl-widget',
                    ]);

                case 'newsletter_success':
                    return NewsletterWidget::renderSuccess(
                        $attrs['message'] ?? '✅ با موفقیت عضو شدید!'
                    );

                default:
                    return '';
            }
        } catch (\Throwable $e) {
            error_log('Newsletter shortcode error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * بررسی وجود shortcode در محتوا
     */
    public static function hasShortcode(string $content): bool
    {
        return strpos($content, '[newsletter') !== false;
    }
}

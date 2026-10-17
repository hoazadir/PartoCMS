<?php
/**
 * PartoCMS - FAQ Shortcode Parser
 *
 * شورت‌کدها:
 * [faq]                    → نمایش همه سوالات فعال
 * [faq category="پشتیبانی"] → نمایش سوالات یک دسته خاص
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Widget;

use PartoCMS\Faq\Infrastructure\Persistence\PdoFaqRepository;
use PartoCMS\Faq\Domain\Service\FaqService;

final class FaqShortcode
{
    public static function parse(string $content): string
    {
        if ($content === '' || strpos($content, '[faq') === false) {
            return $content;
        }

        $pattern = '/\[faq([^\]]*)\]/i';

        return preg_replace_callback($pattern, function ($m) {
            $attrs = self::parseAttrs($m[1]);
            return self::render($attrs);
        }, $content);
    }

    private static function parseAttrs(string $s): array
    {
        $out = [];
        // پشتیبانی از category="..." یا category='...'
        if (preg_match_all('/(\w+)\s*=\s*["\']([^"\']*)["\']/', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $out[$a[1]] = $a[2];
            }
        }
        return $out;
    }

    private static function render(array $attrs): string
    {
        try {
            // اتصال به دیتابیس و سرویس
            $pdo  = getDB(); // تابع سراسری PartoCMS
            $repo = new PdoFaqRepository($pdo);
            $svc  = new FaqService($repo);

            $category = $attrs['category'] ?? null;
            $items    = $svc->getActiveList($category);

            return FaqWidget::renderAccordion($items);
        } catch (\Throwable $e) {
            error_log('FAQ Shortcode error: ' . $e->getMessage());
            return '<!-- خطا در بارگذاری سوالات متداول -->';
        }
    }

    public static function hasShortcode(string $content): bool
    {
        return strpos($content, '[faq') !== false;
    }
}

<?php
/**
 * 🆕 PartoCMS - Ad Obfuscator
 *
 * تغییر نام کلاس‌ها، attributeها و URLهای تبلیغاتی
 * برای جلوگیری از شناسایی توسط AdBlockها و Edge Tracking Prevention
 *
 * ⚠️ این فایل جدید است — به AdRenderer و AdWidget دست نمی‌زند (فقط import می‌شود).
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-04
 *
 * نگاشت:
 *   ad-slot      → pcms-slot
 *   ad-slider    → pcms-slider
 *   ad-banner    → pcms-banner
 *   data-ad-id   → data-pcms-id
 *   track-click  → pcms-cl
 */

class AdObfuscator
{
    /** @var bool اگر false باشد، اصلاً obfuscate نمی‌کند (برای دیباگ) */
    private static bool $enabled = true;

    /** @var string پیشوند جدید */
    private static string $prefix = 'pcms-';

    /** @var array کش برای نگاشت */
    private static array $cache = [];

    /**
     * تنظیم حالت فعال/غیرفعال
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    // ═══════════════════════════════════════════════════════════
    // ۱. کلاس‌های CSS
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل نام کلاس
     *
     * مثال:
     *   AdObfuscator::cls('ad-slot')      → 'pcms-slot'
     *   AdObfuscator::cls('ad-slider')    → 'pcms-slider'
     *   AdObfuscator::cls('ad-anim-fade') → 'pcms-anim-fade'
     */
    public static function cls(string $class): string
    {
        if (!self::$enabled) return $class;

        $key = 'cls:' . $class;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        // فقط کلاس‌هایی که با ad- شروع می‌شوند
        if (strpos($class, 'ad-') === 0) {
            $result = self::$prefix . substr($class, 3);
        } else {
            $result = $class;
        }

        return self::$cache[$key] = $result;
    }

    /**
     * تبدیل چند کلاس با هم (space-separated)
     *
     * مثال:
     *   AdObfuscator::clsMulti('ad-slot ad-slider')  → 'pcms-slot pcms-slider'
     */
    public static function clsMulti(string $classes): string
    {
        if (!self::$enabled) return $classes;

        $parts = preg_split('/\s+/', trim($classes));
        $mapped = array_map([self::class, 'cls'], $parts);
        return implode(' ', $mapped);
    }

    // ═══════════════════════════════════════════════════════════
    // ۲. Attributeهای HTML
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل نام attribute
     *
     * مثال:
     *   AdObfuscator::attr('data-ad-id')      → 'data-pcms-id'
     *   AdObfuscator::attr('data-ad-slider')  → 'data-pcms-slider'
     *   AdObfuscator::attr('data-impression-url') → 'data-pcms-track'
     */
    public static function attr(string $attr): string
    {
        if (!self::$enabled) return $attr;

        $key = 'attr:' . $attr;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $map = [
            'data-ad-id'          => 'data-pcms-id',
            'data-ad-slider'      => 'data-pcms-slider',
            'data-ad-carousel'    => 'data-pcms-carousel',
            'data-ad-click'       => 'data-pcms-click',
            'data-banner-id'      => 'data-pcms-banner',
            'data-impression-url' => 'data-pcms-track',
            'data-slide'          => 'data-pcms-slide',
        ];

        $result = $map[$attr] ?? $attr;
        return self::$cache[$key] = $result;
    }

    // ═══════════════════════════════════════════════════════════
    // ۳. URLهای AJAX
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل URL tracking
     *
     * مثال:
     *   AdObfuscator::url('ajax/track-click.php')      → 'ajax/pcms-cl.php'
     *   AdObfuscator::url('ajax/track-impression.php') → 'ajax/pcms-tr.php'
     */
    public static function url(string $url): string
    {
        if (!self::$enabled) return $url;

        $key = 'url:' . $url;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $map = [
            'track-click'      => 'pcms-cl',
            'track-impression' => 'pcms-tr',
        ];

        $result = $url;
        foreach ($map as $from => $to) {
            $result = str_replace($from, $to, $result);
        }

        return self::$cache[$key] = $result;
    }

    // ═══════════════════════════════════════════════════════════
    // ۴. helperهای خاص
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل کلاس انیمیشن
     */
    public static function anim(string $class): string
    {
        if (!self::$enabled) return $class;
        return str_replace('ad-anim-', 'pcms-anim-', $class);
    }

    /**
     * تبدیل prefix کلی
     */
    public static function prefix(string $text): string
    {
        if (!self::$enabled) return $text;
        return str_replace('ad-', self::$prefix, $text);
    }

    /**
     * پاک کردن cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    // ═══════════════════════════════════════════════════════════
    // ۵. تزریق پویا (Dynamic Injection)
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل HTML به Base64 برای تزریق پویا
     *
     * @param string $html محتوای HTML تبلیغ
     * @return string placeholder برای تزریق
     */
    public static function obfuscateHtml(string $html): string
    {
        if (!self::$enabled || empty($html)) {
            return $html;
        }

        // تولید ID یکتا
        $slotId = 's' . substr(md5(uniqid('', true)), 0, 12);

        // رمزنگاری Base64
        $encoded = base64_encode($html);

        // placeholder
        return '<div data-pcms-slot="' . $slotId . '" data-pcms-content="' . $encoded . '"></div>';
    }

    /**
     * لیست نگاشت‌ها (برای دیباگ)
     */
    public static function getMap(): array
    {
        return [
            'class_prefix' => self::$prefix,
            'attrs' => [
                'data-ad-id'          => 'data-pcms-id',
                'data-ad-slider'      => 'data-pcms-slider',
                'data-ad-carousel'    => 'data-pcms-carousel',
                'data-ad-click'       => 'data-pcms-click',
                'data-banner-id'      => 'data-pcms-banner',
                'data-impression-url' => 'data-pcms-track',
                'data-slide'          => 'data-pcms-slide',
            ],
            'urls' => [
                'track-click'      => 'pcms-cl',
                'track-impression' => 'pcms-tr',
            ],
        ];
    }
}

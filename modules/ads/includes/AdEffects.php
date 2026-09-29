<?php
/**
 * PartoCMS - Ads Module - AdEffects
 * ثابت‌ها و تعاریف افکت‌ها، حالت‌های نمایش، easing
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class AdEffects
{
    /**
     * ═══════════════════════════════════════════════════════════
     * حالت‌های نمایش (Display Modes)
     * ═══════════════════════════════════════════════════════════
     */
    public const DISPLAY_MODES = [
        'single' => [
            'label' => 'تک بنر',
            'icon'  => '🖼️',
            'desc'  => 'فقط یک بنر نمایش داده می‌شود',
        ],
        'slider' => [
            'label' => 'اسلایدر',
            'icon'  => '🎞️',
            'desc'  => 'بنرها یکی‌یکی با انیمیشن',
        ],
        'carousel' => [
            'label' => 'کاروسل',
            'icon'  => '🎠',
            'desc'  => 'اسلایدر بزرگ با پیش‌نمایش',
        ],
        'rotation' => [
            'label' => 'چرخشی',
            'icon'  => '🔄',
            'desc'  => 'تصادفی یا نوبتی بین بنرها',
        ],
        'grid' => [
            'label' => 'شبکه‌ای',
            'icon'  => '▦',
            'desc'  => 'چند بنر کنار هم',
        ],
        'stack' => [
            'label' => 'عمودی',
            'icon'  => '☰',
            'desc'  => 'بنرها زیر هم',
        ],
        'marquee' => [
            'label' => 'متحرک',
            'icon'  => '➡️',
            'desc'  => 'حرکت افقی مداوم',
        ],
    ];

    /**
     * ═══════════════════════════════════════════════════════════
     * افکت‌های انیمیشن — ۴۵ افکت
     * ═══════════════════════════════════════════════════════════
     */
    public const ANIMATION_TYPES = [
        // ═══ Basic (6) ═══
        'none'          => ['label' => 'بدون انیمیشن',       'group' => 'پایه'],
        'fade'          => ['label' => 'محو شدن',            'group' => 'پایه'],
        'slide-left'    => ['label' => 'اسلاید از چپ',        'group' => 'پایه'],
        'slide-right'   => ['label' => 'اسلاید از راست',      'group' => 'پایه'],
        'slide-up'      => ['label' => 'اسلاید از بالا',      'group' => 'پایه'],
        'slide-down'    => ['label' => 'اسلاید از پایین',     'group' => 'پایه'],

        // ═══ Zoom & Scale (4) ═══
        'zoom-in'       => ['label' => 'زوم داخل',            'group' => 'بزرگ‌نمایی'],
        'zoom-out'      => ['label' => 'زوم خارج',            'group' => 'بزرگ‌نمایی'],
        'zoom-rotate'   => ['label' => 'زوم + چرخش',          'group' => 'بزرگ‌نمایی'],
        'ken-burns'     => ['label' => 'کن برنز (سینمایی)',   'group' => 'بزرگ‌نمایی'],

        // ═══ 3D (5) ═══
        'flip-x'        => ['label' => 'فلیپ افقی',            'group' => 'سه‌بعدی'],
        'flip-y'        => ['label' => 'فلیپ عمودی',           'group' => 'سه‌بعدی'],
        'flip-3d'       => ['label' => 'فلیپ سه‌بعدی',         'group' => 'سه‌بعدی'],
        'cube'          => ['label' => 'مکعبی',                'group' => 'سه‌بعدی'],
        'fold'          => ['label' => 'تاشو',                 'group' => 'سه‌بعدی'],

        // ═══ Rotation (4) ═══
        'rotate-cw'     => ['label' => 'چرخش ساعتگرد',         'group' => 'چرخش'],
        'rotate-ccw'    => ['label' => 'چرخش پادساعتگرد',      'group' => 'چرخش'],
        'rotate-spin'   => ['label' => 'چرخش کامل',            'group' => 'چرخش'],
        'rotate-swing'  => ['label' => 'تاب خوردن',            'group' => 'چرخش'],

        // ═══ Bounce & Elastic (4) ═══
        'bounce'        => ['label' => 'پرش',                  'group' => 'پرش'],
        'bounce-in'     => ['label' => 'ورود با پرش',          'group' => 'پرش'],
        'elastic'       => ['label' => 'کشسانی',               'group' => 'پرش'],
        'rubber-band'   => ['label' => 'کشیده شدن',            'group' => 'پرش'],

        // ═══ Slide Variants (6) ═══
        'slide-diagonal'=> ['label' => 'اسلاید مورب',          'group' => 'اسلاید پیشرفته'],
        'slide-rotate'  => ['label' => 'اسلاید + چرخش',        'group' => 'اسلاید پیشرفته'],
        'slide-blur'    => ['label' => 'اسلاید + محو',         'group' => 'اسلاید پیشرفته'],
        'slide-reveal'  => ['label' => 'آشکارسازی',            'group' => 'اسلاید پیشرفته'],
        'slide-curtain' => ['label' => 'پرده‌ای',              'group' => 'اسلاید پیشرفته'],
        'slide-split'   => ['label' => 'شکاف',                 'group' => 'اسلاید پیشرفته'],

        // ═══ Fade Variants (5) ═══
        'fade-blur'     => ['label' => 'محو + تاری',           'group' => 'محو پیشرفته'],
        'fade-scale'    => ['label' => 'محو + مقیاس',          'group' => 'محو پیشرفته'],
        'fade-rotate'   => ['label' => 'محو + چرخش',           'group' => 'محو پیشرفته'],
        'fade-up'       => ['label' => 'محو + بالا',           'group' => 'محو پیشرفته'],
        'fade-down'     => ['label' => 'محو + پایین',          'group' => 'محو پیشرفته'],

        // ═══ Creative (6) ═══
        'glitch'        => ['label' => 'گلیچ',                 'group' => 'خلاقانه'],
        'pixelate'      => ['label' => 'پیکسلی',               'group' => 'خلاقانه'],
        'wipe-horizontal' => ['label' => 'پاک‌کردن افقی',       'group' => 'خلاقانه'],
        'wipe-vertical' => ['label' => 'پاک‌کردن عمودی',       'group' => 'خلاقانه'],
        'iris-open'     => ['label' => 'باز شدن دایره‌ای',     'group' => 'خلاقانه'],
        'iris-close'    => ['label' => 'بستن دایره‌ای',        'group' => 'خلاقانه'],

        // ═══ Morph (3) ═══
        'morph'         => ['label' => 'مورف',                 'group' => 'مورف'],
        'liquid'        => ['label' => 'مایع',                 'group' => 'مورف'],
        'wave'          => ['label' => 'موج',                  'group' => 'مورف'],

        // ═══ Special (2) ═══
        'random'        => ['label' => 'تصادفی',               'group' => 'ویژه'],
        'typewriter'    => ['label' => 'ماشین تحریر',          'group' => 'ویژه'],
    ];

    /**
     * ═══════════════════════════════════════════════════════════
     * Easing Functions
     * ═══════════════════════════════════════════════════════════
     */
    public const EASING_TYPES = [
        'linear'        => 'خطی',
        'ease'          => 'نرم',
        'ease-in'       => 'ورود نرم',
        'ease-out'      => 'خروج نرم',
        'ease-in-out'   => 'ورود و خروج نرم',
        'cubic-in'      => 'مکعبی (ورود)',
        'cubic-out'     => 'مکعبی (خروج)',
        'cubic-in-out'  => 'مکعبی (ورود/خروج)',
        'back-in'       => 'بازگشتی (ورود)',
        'back-out'      => 'بازگشتی (خروج)',
        'bounce-in'     => 'پرشی (ورود)',
        'bounce-out'    => 'پرشی (خروج)',
    ];

    /**
     * ═══════════════════════════════════════════════════════════
     * مدت زمان انیمیشن
     * ═══════════════════════════════════════════════════════════
     */
    public const SPEED_PRESETS = [
        'ultra-fast' => ['label' => 'فوق سریع', 'duration' => 200],
        'fast'       => ['label' => 'سریع',      'duration' => 400],
        'normal'     => ['label' => 'معمولی',    'duration' => 600],
        'slow'       => ['label' => 'کند',       'duration' => 900],
        'ultra-slow' => ['label' => 'فوق کند',   'duration' => 1500],
    ];

    // ═══════════════════════════════════════════════════════════
    // Helper Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * گروه‌بندی افکت‌ها بر اساس گروه
     */
    public static function getAnimationGroups(): array
    {
        $groups = [];
        foreach (self::ANIMATION_TYPES as $slug => $info) {
            $groups[$info['group']][$slug] = $info;
        }
        return $groups;
    }

    /**
     * بررسی معتبر بودن افکت
     */
    public static function isValidAnimation(string $type): bool
    {
        return isset(self::ANIMATION_TYPES[$type]);
    }

    /**
     * بررسی معتبر بودن حالت نمایش
     */
    public static function isValidDisplayMode(string $mode): bool
    {
        return isset(self::DISPLAY_MODES[$mode]);
    }

    /**
     * بررسی معتبر بودن easing
     */
    public static function isValidEasing(string $easing): bool
    {
        return isset(self::EASING_TYPES[$easing]);
    }

    /**
     * تبدیل easing به CSS cubic-bezier
     */
    public static function easingToCss(string $easing): string
    {
        $map = [
            'linear'        => 'linear',
            'ease'          => 'ease',
            'ease-in'       => 'ease-in',
            'ease-out'      => 'ease-out',
            'ease-in-out'   => 'ease-in-out',
            'cubic-in'      => 'cubic-bezier(0.55, 0.055, 0.675, 0.19)',
            'cubic-out'     => 'cubic-bezier(0.215, 0.61, 0.355, 1)',
            'cubic-in-out'  => 'cubic-bezier(0.645, 0.045, 0.355, 1)',
            'back-in'       => 'cubic-bezier(0.6, -0.28, 0.735, 0.045)',
            'back-out'      => 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
            'bounce-in'     => 'cubic-bezier(0.6, 0, 0.7, 0.2)',
            'bounce-out'    => 'cubic-bezier(0.175, 0.885, 0.32, 1.475)',
        ];

        return $map[$easing] ?? 'ease-in-out';
    }

    /**
     * دریافت همه داده‌ها برای فرم
     */
    public static function getAllForForm(): array
    {
        return [
            'display_modes'    => self::DISPLAY_MODES,
            'animation_types'  => self::ANIMATION_TYPES,
            'animation_groups' => self::getAnimationGroups(),
            'easing_types'     => self::EASING_TYPES,
            'speed_presets'    => self::SPEED_PRESETS,
        ];
    }
}

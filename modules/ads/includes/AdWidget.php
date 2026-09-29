<?php
/**
 * PartoCMS - Ads Module - AdWidget
 * رابط ساده برای نمایش تبلیغات در قالب‌ها
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 *
 * استفاده در قالب:
 *   <?= AdWidget::show('header') ?>
 *   <?= AdWidget::sidebar() ?>
 *   <?= AdWidget::inContent() ?>
 *   <?= AdWidget::footer() ?>
 *   <?= AdWidget::slider('slider-home') ?>
 *   <?= AdWidget::sticky() ?>
 *   <?= AdWidget::popup() ?>
 *   <?= AdWidget::css() ?>
 *   <?= AdWidget::js() ?>
 */

require_once __DIR__ . '/AdManager.php';
require_once __DIR__ . '/AdRenderer.php';

class AdWidget
{
    private static ?AdRenderer $renderer = null;
    private static bool $cssRendered = false;
    private static bool $jsRendered = false;
    private static bool $contextDetected = false;

    /**
     * راه‌اندازی renderer
     */
    private static function init(): ?AdRenderer
    {
        if (self::$renderer !== null) {
            return self::$renderer;
        }

        try {
            $pdo = getDB();
            $manager = new AdManager($pdo);
            self::$renderer = new AdRenderer($manager, true);

            // تشخیص خودکار context صفحه فعلی (بدون تغییر در index/post/category)
            self::detectContext(self::$renderer, $pdo);

            return self::$renderer;
        } catch (Throwable $e) {
            error_log('AdWidget init error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * تشخیص خودکار context صفحه فعلی
     *
     * بر اساس SCRIPT_NAME و پارامترهای URL، context مناسب را
     * روی AdRenderer ست می‌کند. این متد ساختار index/post/category
     * را دست‌نخورده نگه می‌دارد و فقط AdWidget را مسئول تشخیص می‌کند.
     *
     * @param AdRenderer $renderer
     * @param PDO        $pdo
     */
    private static function detectContext(AdRenderer $renderer, PDO $pdo): void
    {
        if (self::$contextDetected) {
            return;
        }
        self::$contextDetected = true;

        try {
            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

            // ─── post.php → context از محتوا ───
            if ($script === 'post.php') {
                $id   = (int) ($_GET['id']   ?? 0);
                $slug = trim($_GET['slug'] ?? '');

                if ($id < 1 && $slug !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM content_items WHERE slug = ? LIMIT 1");
                    $stmt->execute([$slug]);
                    $id = (int) ($stmt->fetchColumn() ?: 0);
                }

                if ($id > 0) {
                    $renderer->setContextFromContent($id, 'post');
                }
                return;
            }

            // ─── category.php → context از دسته ───
            if ($script === 'category.php') {
                $slug = trim($_GET['slug'] ?? '');

                if ($slug !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$slug]);
                    $catId = (int) ($stmt->fetchColumn() ?: 0);

                    if ($catId > 0) {
                        $renderer->setContextFromCategory($catId);
                    }
                }
                return;
            }

            // ─── index.php و بقیه → context خالی (پیش‌فرض) ───
            // AdRenderer از قبل empty context دارد، کاری لازم نیست
        } catch (Throwable $e) {
            error_log('AdWidget detectContext error: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // متدهای اصلی
    // ═══════════════════════════════════════════════════════════

    /**
     * نمایش تبلیغ در یک موقعیت مشخص
     */
    public static function show(string $position, array $options = []): string
    {
        $renderer = self::init();
        if (!$renderer) return '';
        return $renderer->renderPosition($position, $options);
    }

    /**
     * تبلیغ هدر
     */
    public static function header(): string
    {
        return self::show('header', ['class' => 'ad-slot ad-slot-header']);
    }

    /**
     * تبلیغ فوتر
     */
    public static function footer(): string
    {
        return self::show('footer', ['class' => 'ad-slot ad-slot-footer']);
    }

    /**
     * تبلیغ سایدبار
     */
    public static function sidebar(): string
    {
        return self::show('sidebar', ['class' => 'ad-slot ad-slot-sidebar']);
    }

    /**
     * تبلیغ داخل محتوا
     */
    public static function inContent(): string
    {
        return self::show('in-content', ['class' => 'ad-slot ad-slot-in-content', 'slider' => false]);
    }

    /**
     * تبلیغ اسلایدر
     */
    public static function slider(string $position = 'slider-home', array $options = []): string
    {
        $defaults = [
            'class'    => 'ad-slot ad-slot-slider',
            'slider'   => true,
            'autoplay' => true,
            'interval' => 5000,
        ];
        return self::show($position, array_merge($defaults, $options));
    }

    /**
     * تبلیغ چسبان پایین
     */
    public static function sticky(): string
    {
        return self::show('sticky-footer', [
            'class'  => 'ad-slot ad-slot-sticky',
            'slider' => false,
        ]);
    }

    /**
     * تبلیغ پاپ‌آپ
     */
    public static function popup(): string
    {
        $html = self::show('popup', ['class' => 'ad-popup-content', 'slider' => false]);
        if (empty($html)) return '';

        return '<div class="ad-popup-overlay" id="adPopupOverlay" onclick="adPopupClose(event)">'
             . '<div class="ad-popup-inner" onclick="event.stopPropagation()">'
             . '<button class="ad-popup-close" onclick="adPopupClose(event)" aria-label="بستن">×</button>'
             . $html
             . '</div></div>';
    }

    // ═══════════════════════════════════════════════════════════
    // CSS و JS (یک بار در قالب)
    // ═══════════════════════════════════════════════════════════

    /**
     * CSS پیش‌فرض تبلیغات (یک بار در <head>)
     */
    public static function css(): string
    {
        if (self::$cssRendered) return '';
        self::$cssRendered = true;

        $base = AdRenderer::getDefaultCss();

        return "<style>\n" . $base . "\n" . self::getWidgetCss() . "\n</style>";
    }

    /**
     * JS تبلیغات (یک بار در پایان <body>)
     */
    public static function js(): string
    {
        if (self::$jsRendered) return '';
        self::$jsRendered = true;

        $base = AdRenderer::getDefaultJs();

        return "<script>\n" . $base . "\n" . self::getWidgetJs() . "\n</script>";
    }

    // ═══════════════════════════════════════════════════════════
    // CSS اضافی (popup, sticky)
    // ═══════════════════════════════════════════════════════════

    private static function getWidgetCss(): string
    {
        return <<<CSS
/* ─── Popup ─── */
.ad-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: adPopupFade .3s ease;
}
.ad-popup-overlay.active { display: flex; }
@keyframes adPopupFade { from { opacity: 0; } to { opacity: 1; } }

.ad-popup-inner {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    max-width: 90vw;
    max-height: 90vh;
    overflow: auto;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: adPopupScale .3s ease;
}
@keyframes adPopupScale {
    from { transform: scale(0.9); }
    to   { transform: scale(1); }
}

.ad-popup-close {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 0;
    background: #ef4444;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition: transform 0.2s;
}
.ad-popup-close:hover { transform: scale(1.1); }
html[dir="ltr"] .ad-popup-close { left: auto; right: 8px; }

/* ─── Sticky ─── */
.ad-slot-sticky {
    position: fixed;
    bottom: 0;
    right: 0;
    left: 0;
    background: #fff;
    z-index: 9998;
    padding: 8px;
    margin: 0;
    box-shadow: 0 -2px 20px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    animation: adStickyUp .4s ease;
}
@keyframes adStickyUp {
    from { transform: translateY(100%); }
    to   { transform: translateY(0); }
}
.ad-slot-sticky .ad-item {
    margin: 0;
}
.ad-sticky-close {
    background: #f1f5f9;
    border: 0;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    color: #64748b;
    font-size: 16px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ad-sticky-close:hover { background: #e2e8f0; color: #334155; }

@media (max-width: 768px) {
    .ad-slot-sticky { padding: 6px; }
    .ad-popup-inner { padding: 15px; }
}
CSS;
    }

    // ═══════════════════════════════════════════════════════════
    // JS اضافی (popup, sticky close)
    // ═══════════════════════════════════════════════════════════

    private static function getWidgetJs(): string
    {
        return <<<JS
// ─── Popup ───
function adPopupShow() {
    const overlay = document.getElementById('adPopupOverlay');
    if (overlay && !sessionStorage.getItem('ad_popup_closed')) {
        overlay.classList.add('active');
    }
}
function adPopupClose(e) {
    if (e) e.stopPropagation();
    const overlay = document.getElementById('adPopupOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        sessionStorage.setItem('ad_popup_closed', '1');
    }
}

// نمایش پاپ‌آپ پس از ۳ ثانیه
if (document.getElementById('adPopupOverlay')) {
    setTimeout(adPopupShow, 3000);
}

// ─── Sticky Close ───
document.querySelectorAll('.ad-slot-sticky').forEach(sticky => {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ad-sticky-close';
    closeBtn.innerHTML = '×';
    closeBtn.setAttribute('aria-label', 'بستن');
    closeBtn.addEventListener('click', () => {
        sticky.style.display = 'none';
        sessionStorage.setItem('ad_sticky_closed', '1');
    });
    sticky.appendChild(closeBtn);
});

// ─── اگر کاربر قبلاً بسته بود ───
if (sessionStorage.getItem('ad_sticky_closed') === '1') {
    document.querySelectorAll('.ad-slot-sticky').forEach(s => s.style.display = 'none');
}

// ─── Esc برای بستن popup ───
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') adPopupClose();
});
JS;
    }

    // ═══════════════════════════════════════════════════════════
    // ابزارهای کمکی
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی وجود تبلیغ در یک موقعیت
     */
    public static function has(string $position): bool
    {
        $renderer = self::init();
        if (!$renderer) return false;

        try {
            $pdo = getDB();
            $manager = new AdManager($pdo);
            return count($manager->getActiveAdsForPosition($position)) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * ریست کردن renderer (برای تست)
     */
    public static function reset(): void
    {
        self::$renderer = null;
        self::$cssRendered = false;
        self::$jsRendered = false;
    }
}

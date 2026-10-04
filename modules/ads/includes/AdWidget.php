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
        return self::show('header', ['class' => 'pcms-slot pcms-slot-header']);
    }

    /**
     * تبلیغ فوتر
     */
    public static function footer(): string
    {
        return self::show('footer', ['class' => 'pcms-slot pcms-slot-footer']);
    }

    /**
     * تبلیغ سایدبار
     */
    public static function sidebar(): string
    {
        return self::show('sidebar', ['class' => 'pcms-slot pcms-slot-sidebar']);
    }

    /**
     * تبلیغ داخل محتوا
     */
    public static function inContent(): string
    {
        return self::show('in-content', ['class' => 'pcms-slot pcms-slot-in-content', 'slider' => false]);
    }

    /**
     * تبلیغ اسلایدر
     */
    public static function slider(string $position = 'slider-home', array $options = []): string
    {
        $defaults = [
            'class'    => 'pcms-slot pcms-slot-slider',
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
            'class'  => 'pcms-slot pcms-slot-sticky',
            'slider' => false,
        ]);
    }

    /**
     * تبلیغ پاپ‌آپ
     */
    public static function popup(): string
    {
        $html = self::show('popup', ['class' => 'pcms-popup-content', 'slider' => false]);
        if (empty($html)) return '';

        return '<div class="pcms-popup-overlay" id="pcmsPopupOverlay" onclick="pcmsPopupClose(event)">'
             . '<div class="pcms-popup-inner" onclick="event.stopPropagation()">'
             . '<button class="pcms-popup-close" onclick="pcmsPopupClose(event)" aria-label="بستن">×</button>'
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

        // 🆕 Version برای cache busting
        $version = 'v2.' . date('YmdHis');

        return "<!-- AdWidget {$version} -->\n<script>\n" . $base . "\n" . self::getWidgetJs() . "\n</script>";
    }

    // ═══════════════════════════════════════════════════════════
    // CSS اضافی (popup, sticky)
    // ═══════════════════════════════════════════════════════════

    private static function getWidgetCss(): string
    {
        return <<<CSS
/* ─── Popup ─── */
.pcms-popup-overlay {
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
.pcms-popup-overlay.active { display: flex; }
@keyframes adPopupFade { from { opacity: 0; } to { opacity: 1; } }

.pcms-popup-inner {
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

.pcms-popup-close {
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
.pcms-popup-close:hover { transform: scale(1.1); }
html[dir="ltr"] .pcms-popup-close { left: auto; right: 8px; }

/* ─── Sticky ─── */
.pcms-slot-sticky {
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
.pcms-slot-sticky .pcms-item {
    margin: 0;
}
.pcms-sticky-close {
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
.pcms-sticky-close:hover { background: #e2e8f0; color: #334155; }

@media (max-width: 768px) {
    .pcms-slot-sticky { padding: 6px; }
    .pcms-popup-inner { padding: 15px; }
}
CSS;
    }

    // ═══════════════════════════════════════════════════════════
    // JS اضافی (popup, sticky close)
    // ═══════════════════════════════════════════════════════════

    private static function getWidgetJs(): string
    {
        return <<<JS
// ═══════════════════════════════════════════════════════════
// 🆕 Anti-AdBlock: تزریق پویای محتوای Base64
// ═══════════════════════════════════════════════════════════

function pcmsInjectAds() {
    const slots = document.querySelectorAll('[data-pcms-slot]');
    console.log('[Anti-AdBlock] Found', slots.length, 'slots to inject');

    slots.forEach(function(slot) {
        // چک: قبلاً تزریق شده؟
        if (slot.dataset.pcmsInjected === '1') return;

        const encoded = slot.dataset.pcmsContent;
        if (!encoded) {
            console.warn('[Anti-AdBlock] No content for slot:', slot.dataset.pcmsSlot);
            return;
        }

        try {
            // decode Base64 (با پشتیبانی از UTF-8)
            let html;
            try {
                // روش استاندارد
                html = decodeURIComponent(escape(atob(encoded)));
            } catch (e) {
                // روش جایگزین
                html = atob(encoded);
            }

            // تزریق
            slot.innerHTML = html;
            slot.dataset.pcmsInjected = '1';

            console.log('[Anti-AdBlock] Injected slot:', slot.dataset.pcmsSlot);
        } catch (err) {
            console.error('[Anti-AdBlock] Injection error:', err, slot.dataset.pcmsSlot);
        }
    });

    // بعد از تزریق، اسلایدرها و tracking را فعال کن
    if (typeof initSlider === 'function') {
        document.querySelectorAll('.pcms-slider').forEach(initSlider);
    }
    if (typeof initCarousel === 'function') {
        document.querySelectorAll('.pcms-carousel').forEach(initCarousel);
    }
    if (typeof trackImpressions === 'function') {
        trackImpressions();
    }
}

// اجرا در DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pcmsInjectAds);
} else {
    pcmsInjectAds();
}

// Fallback: بعد از window.load
window.addEventListener('load', pcmsInjectAds);

// Fallback: setTimeout
setTimeout(pcmsInjectAds, 500);

// ─── Popup ───
function adPopupShow() {
    const overlay = document.getElementById('adPopupOverlay');
    if (overlay && !sessionStorage.getItem('ad_popup_closed')) {
        overlay.classList.add('active');
    }
}
function pcmsPopupClose(e) {
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
document.querySelectorAll('.pcms-slot-sticky').forEach(sticky => {
    const closeBtn = document.createElement('button');
    closeBtn.className = 'pcms-sticky-close';
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
    document.querySelectorAll('.pcms-slot-sticky').forEach(s => s.style.display = 'none');
}

// ─── Esc برای بستن popup ───
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') pcmsPopupClose();
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

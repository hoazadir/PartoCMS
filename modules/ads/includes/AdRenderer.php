<?php
/**
 * PartoCMS - Ads Module - AdRenderer v2.0
 * رندر تبلیغات برای نمایش در قالب‌ها
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 */

class AdRenderer
{
    private AdManager $manager;
    private string $baseUrl;
    private bool $trackingEnabled = true;

    public function __construct(AdManager $manager, bool $tracking = true)
    {
        $this->manager = $manager;
        $this->baseUrl = SITE_URL;
        $this->trackingEnabled = $tracking;
    }

    // ═══════════════════════════════════════════════════════════
    // API اصلی — برای استفاده در قالب‌ها
    // ═══════════════════════════════════════════════════════════

    /**
     * رندر همه تبلیغات یک موقعیت
     * اگر بیش از یک تبلیغ باشد → اسلایدر خودکار
     */
    public function renderPosition(string $positionSlug, array $options = []): string
    {
        $ads = $this->manager->getActiveAdsForPosition($positionSlug);
        if (empty($ads)) return '';

        // تنظیمات
        $asSlider = $options['slider'] ?? (count($ads) > 1);
        $wrapper = $options['wrapper'] ?? 'div';
        $className = $options['class'] ?? 'ad-slot ad-slot-' . $positionSlug;
        $autoplay = $options['autoplay'] ?? true;
        $interval = $options['interval'] ?? 5000;
        $single = $options['single'] ?? false;

        if ($single) {
            $ads = [$ads[0]];
            $asSlider = false;
        }

        // اگر یک تبلیغ → ساده
        if (count($ads) === 1 || !$asSlider) {
            $html = '<' . $wrapper . ' class="' . htmlspecialchars($className) . '">';
            foreach ($ads as $ad) {
                $html .= $this->renderAd($ad);
            }
            $html .= '</' . $wrapper . '>';
            return $html;
        }

        // چند تبلیغ → اسلایدر
        return $this->renderSlider($ads, [
            'wrapper'  => $wrapper,
            'class'    => $className,
            'autoplay' => $autoplay,
            'interval' => $interval,
            'position' => $positionSlug,
        ]);
    }

    /**
     * رندر یک تبلیغ
     */
    public function renderAd(array $ad): string
    {
        if (!$this->manager->isValidAd($ad)) {
            return '';
        }

        $type = $ad['type'] ?? 'image';
        $adId = (int) $ad['id'];

        $impressionUrl = $this->trackingEnabled
            ? $this->baseUrl . '/modules/ads/ajax/track-impression.php?id=' . $adId
            : '';

        $clickUrl = $this->trackingEnabled
            ? $this->baseUrl . '/modules/ads/ajax/track-click.php?id=' . $adId
            : '';

        // رندر محتوا
        $content = '';
        switch ($type) {
            case 'image':
            case 'slider':
                $content = $this->renderImage($ad, $clickUrl);
                break;
            case 'text':
                $content = $this->renderText($ad, $clickUrl);
                break;
            case 'html':
                $content = $ad['html_content'] ?? '';
                break;
            case 'adsense':
                $content = $ad['adsense_code'] ?? '';
                break;
        }

        if (empty(trim($content))) return '';

        // اندازه
        $style = $this->buildSizeStyle($ad);

        // wrapper
        $attrs = [
            'class' => 'ad-item ad-item-' . $type,
            'data-ad-id' => $adId,
        ];
        if ($impressionUrl) {
            $attrs['data-impression-url'] = $impressionUrl;
        }
        if ($style) {
            $attrs['style'] = $style;
        }

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . $k . '="' . htmlspecialchars($v) . '"';
        }

        return '<div' . $attrStr . '>' . $content . '</div>';
    }

    /**
     * رندر اسلایدر حرفه‌ای
     */
    public function renderSlider(array $ads, array $options = []): string
    {
        $ads = array_values(array_filter($ads, [$this->manager, 'isValidAd']));
        if (empty($ads)) return '';

        $sliderId = 'ads-' . uniqid();
        $autoplay = !empty($options['autoplay']);
        $interval = (int) ($options['interval'] ?? 5000);
        $wrapper = $options['wrapper'] ?? 'div';
        $className = $options['class'] ?? 'ad-slot ad-slider-wrapper';
        $position = $options['position'] ?? 'slider';

        $count = count($ads);
        if ($count === 1) {
            return $this->renderAd($ads[0]);
        }

        $html = '<' . $wrapper . ' class="' . htmlspecialchars($className) . '">';
        $html .= '<div id="' . $sliderId . '" class="ad-slider" data-autoplay="' . ($autoplay ? 'true' : 'false') . '" data-interval="' . $interval . '">';

        $html .= '<div class="ad-slider-track">';
        foreach ($ads as $i => $ad) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="ad-slide' . $active . '" data-slide="' . $i . '">';
            $html .= $this->renderAd($ad);
            $html .= '</div>';
        }
        $html .= '</div>';

        // ناوبری
        $html .= '<button class="ad-slider-nav ad-slider-prev" aria-label="قبلی">';
        $html .= '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>';
        $html .= '</button>';
        $html .= '<button class="ad-slider-nav ad-slider-next" aria-label="بعدی">';
        $html .= '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>';
        $html .= '</button>';

        // نقطه‌ها
        $html .= '<div class="ad-slider-dots">';
        foreach ($ads as $i => $ad) {
            $html .= '<button class="ad-dot' . ($i === 0 ? ' active' : '') . '" data-slide="' . $i . '" aria-label="اسلاید ' . ($i + 1) . '"></button>';
        }
        $html .= '</div>';

        $html .= '</div>'; // .ad-slider
        $html .= '</' . $wrapper . '>';

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // رندر انواع محتوا
    // ═══════════════════════════════════════════════════════════

    private function renderImage(array $ad, string $clickUrl): string
    {
        $img = htmlspecialchars($ad['image_url'] ?? '');
        if (empty($img)) return '';

        $alt = htmlspecialchars($ad['alt_text'] ?: ($ad['title'] ?? ''));
        $w = !empty($ad['width']) ? (int) $ad['width'] : 0;
        $h = !empty($ad['height']) ? (int) $ad['height'] : 0;

        $imgStyle = 'display:block;';
        if ($w > 0) $imgStyle .= 'width:' . $w . 'px;';
        else $imgStyle .= 'max-width:100%;';
        if ($h > 0) $imgStyle .= 'height:' . $h . 'px;';
        else $imgStyle .= 'height:auto;';

        $imgTag = '<img src="' . $img . '" alt="' . $alt . '" loading="lazy" style="' . $imgStyle . '">';

        if (!empty($ad['target_url'])) {
            $target = !empty($ad['target_blank']) ? ' target="_blank" rel="noopener"' : '';
            $link = $clickUrl
                ? htmlspecialchars($clickUrl . '&to=' . urlencode($ad['target_url']))
                : htmlspecialchars($ad['target_url']);
            return '<a href="' . $link . '"' . $target . ' data-ad-click="' . (int) $ad['id'] . '" style="display:inline-block;">' . $imgTag . '</a>';
        }

        return $imgTag;
    }

    private function renderText(array $ad, string $clickUrl): string
    {
        $title = htmlspecialchars($ad['title'] ?? '');
        $desc = htmlspecialchars($ad['description'] ?? '');

        $inner = '';
        if ($title) $inner .= '<strong style="display:block;font-size:15px;margin-bottom:4px;">' . $title . '</strong>';
        if ($desc) $inner .= '<span style="font-size:13px;opacity:.8;">' . $desc . '</span>';

        if (empty($inner)) return '';

        $content = '<div class="ad-text-content" style="padding:10px 15px;background:#f8fafc;border-radius:8px;">' . $inner . '</div>';

        if (!empty($ad['target_url'])) {
            $target = !empty($ad['target_blank']) ? ' target="_blank" rel="noopener"' : '';
            $link = $clickUrl
                ? htmlspecialchars($clickUrl . '&to=' . urlencode($ad['target_url']))
                : htmlspecialchars($ad['target_url']);
            return '<a href="' . $link . '"' . $target . ' data-ad-click="' . (int) $ad['id'] . '" style="text-decoration:none;color:inherit;">' . $content . '</a>';
        }

        return $content;
    }

    // ═══════════════════════════════════════════════════════════
    // اندازه
    // ═══════════════════════════════════════════════════════════

    private function buildSizeStyle(array $ad): string
    {
        $styles = [];
        $w = (int) ($ad['width'] ?? 0);
        $h = (int) ($ad['height'] ?? 0);

        if ($w > 0) $styles[] = 'max-width:' . $w . 'px';
        if ($h > 0) $styles[] = 'max-height:' . $h . 'px';

        return implode(';', $styles);
    }

    // ═══════════════════════════════════════════════════════════
    // CSS پیش‌فرض
    // ═══════════════════════════════════════════════════════════

    public static function getDefaultCss(): string
    {
        return <<<CSS
/* ═══════════ Ad Slots ═══════════ */
.ad-slot {
    margin: 20px auto;
    text-align: center;
    position: relative;
    z-index: 10;
}
.ad-slot img {
    border-radius: 6px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}
.ad-slot a:hover img { transform: scale(1.01); }

/* اندازه‌های خاص */
.ad-slot-header     { max-width: 970px; margin: 20px auto; }
.ad-slot-footer     { max-width: 970px; margin: 40px auto; }
.ad-slot-sidebar    { max-width: 320px; margin: 20px auto; }
.ad-slot-in-content { max-width: 468px; margin: 24px auto; }

/* ═══════════ Ad Slider ═══════════ */
.ad-slider-wrapper { margin: 20px auto; max-width: 100%; }

.ad-slider {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    background: #f1f5f9;
    user-select: none;
}

.ad-slider-track {
    position: relative;
    width: 100%;
}

.ad-slide {
    display: none;
    width: 100%;
    animation: adFadeIn 0.4s ease;
}
.ad-slide.active { display: block; }

@keyframes adFadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.ad-slide img { display: block; margin: 0 auto; max-width: 100%; }

/* ناوبری */
.ad-slider-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,.5);
    color: #fff;
    border: 0;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s, transform 0.2s;
    z-index: 5;
    opacity: 0;
}
.ad-slider:hover .ad-slider-nav { opacity: 1; }
.ad-slider-nav:hover { background: rgba(0,0,0,.8); }
.ad-slider-prev { right: 10px; }
.ad-slider-next { left: 10px; }
.ad-slider-nav svg { width: 20px; height: 20px; }

/* نقطه‌ها */
.ad-slider-dots {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 6px;
    z-index: 5;
    background: rgba(0,0,0,.3);
    padding: 5px 10px;
    border-radius: 12px;
}
.ad-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 0;
    background: rgba(255,255,255,.5);
    cursor: pointer;
    transition: all 0.2s;
    padding: 0;
}
.ad-dot.active {
    background: #fff;
    transform: scale(1.3);
}
.ad-dot:hover { background: #fff; }

/* در RTL */
html[dir="rtl"] .ad-slider-prev { right: auto; left: 10px; }
html[dir="rtl"] .ad-slider-next { left: auto; right: 10px; }

/* موبایل */
@media (max-width: 768px) {
    .ad-slider-nav {
        opacity: 1;
        width: 32px;
        height: 32px;
    }
    .ad-slider-prev { right: 5px; }
    .ad-slider-next { left: 5px; }
    .ad-slot { margin: 15px auto; }
}
CSS;
    }

    /**
     * JS پیش‌فرض (اسلایدر + tracking)
     */
    public static function getDefaultJs(): string
    {
        return <<<JS
// ═══════════════════════════════════════════════════════════
// Ad Slider + Tracking
// ═══════════════════════════════════════════════════════════
(function() {
    'use strict';

    // ─── اسلایدر ───
    function initSlider(slider) {
        const slides = slider.querySelectorAll('.ad-slide');
        const dots   = slider.querySelectorAll('.ad-dot');
        const prev   = slider.querySelector('.ad-slider-prev');
        const next   = slider.querySelector('.ad-slider-next');
        const auto   = slider.dataset.autoplay === 'true';
        const interval = parseInt(slider.dataset.interval) || 5000;

        if (slides.length < 2) return;

        let current = 0;
        let timer = null;

        function show(index) {
            slides.forEach((s, i) => s.classList.toggle('active', i === index));
            dots.forEach((d, i) => d.classList.toggle('active', i === index));
            current = index;
        }

        function next_() { show((current + 1) % slides.length); }
        function prev_() { show((current - 1 + slides.length) % slides.length); }

        if (prev) prev.addEventListener('click', () => { prev_(); restart(); });
        if (next) next.addEventListener('click', () => { next_(); restart(); });

        dots.forEach((d, i) => {
            d.addEventListener('click', () => { show(i); restart(); });
        });

        // swipe (موبایل)
        let startX = 0, endX = 0;
        slider.addEventListener('touchstart', (e) => {
            startX = e.changedTouches[0].screenX;
        }, { passive: true });
        slider.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].screenX;
            const diff = startX - endX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) next_();
                else prev_();
                restart();
            }
        }, { passive: true });

        // auto play
        function start() {
            if (!auto) return;
            timer = setInterval(next_, interval);
        }
        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }
        function restart() { stop(); start(); }

        // توقف در hover
        slider.addEventListener('mouseenter', stop);
        slider.addEventListener('mouseleave', start);

        start();
    }

    document.querySelectorAll('.ad-slider').forEach(initSlider);

    // ─── Tracking Impression ───
    function trackImpressions() {
        const items = document.querySelectorAll('[data-impression-url]');
        if (items.length === 0) return;

        // استفاده از IntersectionObserver برای lazy tracking
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const url = entry.target.dataset.impressionUrl;
                        if (url && !entry.target.dataset.tracked) {
                            entry.target.dataset.tracked = '1';
                            fetch(url, { method: 'GET', credentials: 'same-origin', keepalive: true }).catch(() => {});
                        }
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            items.forEach(item => observer.observe(item));
        } else {
            // fallback: tracking فوری
            items.forEach(item => {
                const url = item.dataset.impressionUrl;
                if (url) {
                    fetch(url, { method: 'GET', credentials: 'same-origin', keepalive: true }).catch(() => {});
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackImpressions);
    } else {
        trackImpressions();
    }
})();
JS;
    }
}

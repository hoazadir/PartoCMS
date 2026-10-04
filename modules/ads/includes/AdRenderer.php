<?php
/**
 * PartoCMS - Ads Module - AdRenderer v3.0
 * رندر حرفه‌ای تبلیغات — ۷ حالت + ۴۵ افکت
 *
 * @author Hooman Oliaei
 * @version 3.0.0
 */

require_once __DIR__ . '/AdEffects.php';
require_once __DIR__ . '/AdObfuscator.php';

class AdRenderer
{
    private AdManager $manager;
    private string $baseUrl;
    private bool $trackingEnabled;
    private array $context = [];

    public function __construct(AdManager $manager, bool $tracking = true)
    {
        $this->manager = $manager;
        $this->baseUrl = SITE_URL;
        $this->trackingEnabled = $tracking;
        $this->context = $this->manager->getTargeting()->buildEmptyContext();
    }

    /**
     * تعیین context صفحه فعلی
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * تعیین context از یک محتوا (post/page)
     */
    public function setContextFromContent(int $contentId, string $type = 'post'): void
    {
        $this->context = $this->manager->getTargeting()->buildContextFromContent($contentId, $type);
    }

    /**
     * تعیین context از یک دسته‌بندی
     */
    public function setContextFromCategory(int $categoryId): void
    {
        $this->context = $this->manager->getTargeting()->buildContextFromCategory($categoryId);
    }

    /**
     * دریافت context فعلی
     */
    public function getContext(): array
    {
        return $this->context;
    }

    // ═══════════════════════════════════════════════════════════
    // API اصلی
    // ═══════════════════════════════════════════════════════════

    public function renderPosition(string $positionSlug, array $options = []): string
    {
        $ads = $this->manager->getActiveAdsForPosition($positionSlug);
        if (empty($ads)) return '';

        $html = '';
        foreach ($ads as $ad) {
            // فیلتر هدف‌گیری محتوا
            if (!$this->manager->getTargeting()->shouldShow($ad, $this->context)) {
                continue;
            }
            $html .= $this->renderAd($ad, $options);
        }
        return $html;
    }

    public function renderAd(array $ad, array $options = []): string
    {
        if (!$this->manager->isValidAd($ad)) return '';
        if (!$this->shouldDisplayOnDevice($ad)) return '';

        // فیلتر هدف‌گیری محتوا
        if (!$this->manager->getTargeting()->shouldShow($ad, $this->context)) return '';

        // ═══ تولید HTML اصلی ═══
        $html = $this->buildAdHtml($ad, $options);

        // 🆕 Anti-AdBlock: تزریق پویا با Base64
        if (AdObfuscator::isEnabled() && $html !== '') {
            return AdObfuscator::obfuscateHtml($html);
        }

        return $html;
    }

    /**
     * 🆕 ساخت HTML اصلی تبلیغ (بدون obfuscation)
     */
    private function buildAdHtml(array $ad, array $options = []): string
    {
        // ═══ چک type قبل از display_mode ═══
        $type = $ad['type'] ?? 'image';

        switch ($type) {
            case 'adsense':
                return $this->renderAdsense($ad, $options);
            case 'html':
                return $this->renderHtmlAd($ad, $options);
            case 'text':
                return $this->renderTextAd($ad, $options);
            // 'image' و 'slider' → برو به display_mode
        }

        // ═══ display_mode برای image/slider ═══
        $displayMode = $ad['display_mode'] ?? 'single';

        switch ($displayMode) {
            case 'slider':   return $this->renderSlider($ad, $options);
            case 'carousel': return $this->renderCarousel($ad, $options);
            case 'rotation': return $this->renderRotation($ad, $options);
            case 'grid':     return $this->renderGrid($ad, $options);
            case 'stack':    return $this->renderStack($ad, $options);
            case 'marquee':  return $this->renderMarquee($ad, $options);
            default:         return $this->renderSingle($ad, $options);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // حالت‌های نمایش
    // ═══════════════════════════════════════════════════════════

    private function renderSingle(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        return '<div class="pcms-slot pcms-slot-single ' . $animClass . '"' . $attrs . '>'
             . $this->renderBanner($banners[0], $ad)
             . '</div>';
    }

    /**
     * رندر تبلیغ AdSense
     */
    private function renderAdsense(array $ad, array $options = []): string
    {
        $code = trim($ad['adsense_code'] ?? '');
        if ($code === '') return '';

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        return '<div class="pcms-slot pcms-slot-adsense ' . $animClass . '"' . $attrs . '>'
             . $code
             . '</div>';
    }

    /**
     * رندر تبلیغ HTML خام
     */
    private function renderHtmlAd(array $ad, array $options = []): string
    {
        $html = $ad['html_content'] ?? '';
        if (trim($html) === '') return '';

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        return '<div class="pcms-slot pcms-slot-html ' . $animClass . '"' . $attrs . '>'
             . $html
             . '</div>';
    }

    /**
     * رندر تبلیغ متنی
     */
    private function renderTextAd(array $ad, array $options = []): string
    {
        $title = trim($ad['title'] ?? '');
        $text  = trim($ad['description'] ?? '');
        $link  = trim($ad['target_url'] ?? '');

        if ($title === '' && $text === '') return '';

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $inner = '';
        if ($title !== '') {
            $inner .= '<strong class="pcms-text-title">' . htmlspecialchars($title) . '</strong>';
        }
        if ($text !== '') {
            $inner .= '<p class="pcms-text-desc">' . htmlspecialchars($text) . '</p>';
        }

        if ($link !== '') {
            $inner = '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener">' . $inner . '</a>';
        }

        return '<div class="pcms-slot pcms-slot-text ' . $animClass . '"' . $attrs . '>'
             . $inner
             . '</div>';
    }

    private function renderSlider(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';
        if (count($banners) === 1) return $this->renderSingle($ad, $options);

        $sliderId = 'pcms-slider-' . (int) $ad['id'];
        $settings = [
            'autoplay'     => !empty($ad['autoplay']),
            'interval'     => (int) ($ad['autoplay_interval'] ?? 5000),
            'pauseOnHover' => !empty($ad['pause_on_hover']),
            'loop'         => !empty($ad['loop']),
            'showNav'      => !empty($ad['show_nav']),
            'showDots'     => !empty($ad['show_dots']),
            'showProgress' => !empty($ad['show_progress']),
            'animation'    => $ad['animation_type'] ?? 'fade',
            'duration'     => (int) ($ad['animation_duration'] ?? 600),
            'easing'       => AdEffects::easingToCss($ad['animation_easing'] ?? 'ease-in-out'),
        ];

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $html = '<div class="pcms-slot pcms-slot-slider ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="pcms-slider" id="' . $sliderId . '" data-pcms-slider=\'' 
               . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>';
        
        $html .= '<div class="pcms-slider-track">';
        foreach ($banners as $i => $banner) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="pcms-slide' . $active . '" data-slide="' . $i . '">';
            $html .= $this->renderBanner($banner, $ad);
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($settings['showNav']) {
            $html .= '<button class="pcms-nav pcms-nav-prev" type="button" aria-label="قبلی">‹</button>';
            $html .= '<button class="pcms-nav pcms-nav-next" type="button" aria-label="بعدی">›</button>';
        }

        if ($settings['showDots']) {
            $html .= '<div class="pcms-dots">';
            foreach ($banners as $i => $banner) {
                $active = $i === 0 ? ' active' : '';
                $html .= '<button type="button" class="pcms-dot' . $active . '" data-slide="' . $i . '"></button>';
            }
            $html .= '</div>';
        }

        if ($settings['showProgress'] && $settings['autoplay']) {
            $html .= '<div class="pcms-progress"><div class="pcms-progress-bar"></div></div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    private function renderCarousel(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $carouselId = 'pcms-carousel-' . (int) $ad['id'];
        $settings = [
            'autoplay'     => !empty($ad['autoplay']),
            'interval'     => (int) ($ad['autoplay_interval'] ?? 5000),
            'pauseOnHover' => !empty($ad['pause_on_hover']),
            'loop'         => !empty($ad['loop']),
            'showNav'      => !empty($ad['show_nav']),
            'animation'    => $ad['animation_type'] ?? 'fade',
            'duration'     => (int) ($ad['animation_duration'] ?? 600),
            'easing'       => AdEffects::easingToCss($ad['animation_easing'] ?? 'ease-in-out'),
        ];

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $html = '<div class="pcms-slot pcms-slot-carousel ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="pcms-carousel" id="' . $carouselId . '" data-pcms-carousel=\'' 
               . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>';

        $html .= '<div class="pcms-carousel-main">';
        foreach ($banners as $i => $banner) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="pcms-carousel-slide' . $active . '" data-slide="' . $i . '">';
            $html .= $this->renderBanner($banner, $ad);
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($settings['showNav']) {
            $html .= '<button class="pcms-nav pcms-nav-prev" type="button" aria-label="قبلی">‹</button>';
            $html .= '<button class="pcms-nav pcms-nav-next" type="button" aria-label="بعدی">›</button>';
        }

        if (count($banners) > 1) {
            $html .= '<div class="pcms-carousel-thumbs">';
            foreach ($banners as $i => $banner) {
                $active = $i === 0 ? ' active' : '';
                $img = htmlspecialchars($banner['image_url'] ?? '');
                $html .= '<button type="button" class="pcms-thumb' . $active . '" data-slide="' . $i . '">';
                $html .= '<img src="' . $img . '" alt="" loading="lazy">';
                $html .= '</button>';
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    private function renderRotation(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $selected = $banners[array_rand($banners)];
        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        return '<div class="pcms-slot pcms-slot-rotation ' . $animClass . '"' . $attrs . '>'
             . $this->renderBanner($selected, $ad)
             . '</div>';
    }

    private function renderGrid(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $columns = max(1, min(6, (int) ($ad['grid_columns'] ?? 2)));
        $gap = max(0, (int) ($ad['grid_gap'] ?? 10));

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $html = '<div class="pcms-slot pcms-slot-grid ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="pcms-grid" style="display:grid;grid-template-columns:repeat(' 
               . $columns . ',1fr);gap:' . $gap . 'px;">';
        foreach ($banners as $banner) {
            $html .= '<div class="pcms-grid-item">' . $this->renderBanner($banner, $ad) . '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }

    private function renderStack(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $gap = max(0, (int) ($ad['grid_gap'] ?? 10));
        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $html = '<div class="pcms-slot pcms-slot-stack ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="pcms-stack" style="display:flex;flex-direction:column;gap:' . $gap . 'px;">';
        foreach ($banners as $banner) {
            $html .= '<div class="pcms-stack-item">' . $this->renderBanner($banner, $ad) . '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }

    private function renderMarquee(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $interval = (int) ($ad['autoplay_interval'] ?? 5000);
        $duration = max(10, (int) ($interval / 100));

        $animClass = $this->getAnimationClass($ad);
        $attrs = $this->getContainerAttributes($ad);

        $html = '<div class="pcms-slot pcms-slot-marquee ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="pcms-marquee" data-duration="' . $duration . 's">';
        $html .= '<div class="pcms-marquee-track" style="animation-duration:' . $duration . 's;">';

        foreach ([1, 2] as $repeat) {
            foreach ($banners as $banner) {
                $html .= '<div class="pcms-marquee-item">' . $this->renderBanner($banner, $ad) . '</div>';
            }
        }

        $html .= '</div></div></div>';
        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // رندر بنر
    // ═══════════════════════════════════════════════════════════

    private function renderBanner(array $banner, array $ad): string
    {
        $imgUrl = htmlspecialchars($banner['image_url'] ?? '');
        if (empty($imgUrl)) return '';

        $adId = (int) $ad['id'];
        $bannerId = (int) ($banner['id'] ?? 0);
        $targetUrl = $banner['target_url'] ?? $ad['target_url'] ?? '';
        $targetBlank = !empty($ad['target_blank']);

        $imgStyle = 'display:block;max-width:100%;';
        $w = (int) ($ad['width'] ?? 0);
        $h = (int) ($ad['height'] ?? 0);
        if ($w > 0) $imgStyle .= 'width:' . $w . 'px;';
        if ($h > 0) {
            $imgStyle .= 'height:' . $h . 'px;object-fit:cover;';
        } else {
            $imgStyle .= 'height:auto;';
        }

        $alt = htmlspecialchars($banner['alt_text'] ?? ($banner['title'] ?? $ad['title'] ?? ''));

        $impUrl = $this->trackingEnabled
            ? $this->baseUrl . '/modules/ads/ajax/pcms-tr.php?id=' . $adId
            : '';

        $clickUrl = $this->trackingEnabled
            ? $this->baseUrl . '/modules/ads/ajax/pcms-cl.php?id=' . $adId
            : '';

        $imgTag = '<img src="' . $imgUrl . '" alt="' . $alt . '" style="' . $imgStyle . '">';

        if (!empty($targetUrl)) {
            $target = $targetBlank ? ' target="_blank" rel="noopener"' : '';
            $href = $clickUrl
                ? htmlspecialchars($clickUrl . '&to=' . urlencode($targetUrl))
                : htmlspecialchars($targetUrl);

            $imgTag = '<a href="' . $href . '"' . $target 
                    . ' data-pcms-click="' . $adId . '"'
                    . ' style="display:inline-block;max-width:100%;">' 
                    . $imgTag . '</a>';
        }

        $wrapperAttrs = 'class="pcms-banner" data-pcms-id="' . $adId . '"';
        if ($bannerId > 0) $wrapperAttrs .= ' data-pcms-banner="' . $bannerId . '"';
        if ($impUrl) $wrapperAttrs .= ' data-pcms-track="' . htmlspecialchars($impUrl) . '"';

        return '<div ' . $wrapperAttrs . '>' . $imgTag . '</div>';
    }

    // ═══════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════

    private function getBanners(array $ad): array
    {
        if (!empty($ad['banners'])) return $ad['banners'];

        if (!empty($ad['image_url'])) {
            return [[
                'id'         => 0,
                'image_url'  => $ad['image_url'],
                'target_url' => $ad['target_url'] ?? null,
                'alt_text'   => $ad['alt_text'] ?? null,
                'title'      => $ad['title'] ?? null,
            ]];
        }
        return [];
    }

    private function shouldDisplayOnDevice(array $ad): bool
    {
        $isMobile = $this->detectMobile();
        if ($isMobile && !empty($ad['hide_on_mobile'])) return false;
        if (!$isMobile && !empty($ad['hide_on_desktop'])) return false;
        return true;
    }

    private function detectMobile(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $ua);
    }

    private function getAnimationClass(array $ad): string
    {
        $anim = $ad['animation_type'] ?? 'fade';
        return 'pcms-anim-' . $anim;
    }

    private function getContainerAttributes(array $ad): string
    {
        $attrs = [];

        $w = (int) ($ad['width'] ?? 0);
        $h = (int) ($ad['height'] ?? 0);
        if ($w > 0 || $h > 0) {
            $style = '';
            if ($w > 0) $style .= 'max-width:' . $w . 'px;';
            if ($h > 0) $style .= 'max-height:' . $h . 'px;';
            $attrs[] = 'style="' . $style . '"';
        }

        $duration = (int) ($ad['animation_duration'] ?? 600);
        $delay = (int) ($ad['animation_delay'] ?? 0);
        $easing = AdEffects::easingToCss($ad['animation_easing'] ?? 'ease-in-out');
        $loop = !empty($ad['animation_loop']) ? '1' : '0';

        $attrs[] = 'data-anim-duration="' . $duration . '"';
        $attrs[] = 'data-anim-delay="' . $delay . '"';
        $attrs[] = 'data-anim-easing="' . htmlspecialchars($easing) . '"';
        $attrs[] = 'data-anim-loop="' . $loop . '"';

        return ' ' . implode(' ', $attrs);
    }

    // ═══════════════════════════════════════════════════════════
    // CSS پیش‌فرض
    // ═══════════════════════════════════════════════════════════

    public static function getDefaultCss(): string
    {
        return <<<'CSS'
/* Ad Slot Base */
.pcms-slot { margin: 20px auto; text-align: center; position: relative; }
.pcms-slot img { border-radius: 6px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); display: block; max-width: 100%; }

.pcms-slot-header     { max-width: 970px; }
.pcms-slot-footer     { max-width: 970px; margin: 40px auto; }
.pcms-slot-sidebar    { max-width: 320px; }
.pcms-slot-in-content { max-width: 468px; margin: 24px auto; }
.pcms-slot-popup      { max-width: 600px; }
.pcms-slot-sticky-footer { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 8px; box-shadow: 0 -2px 20px rgba(0,0,0,.1); z-index: 9998; margin: 0; }

/* Slider */
.pcms-slider { position: relative; overflow: hidden; border-radius: 8px; background: #f1f5f9; }
.pcms-slider-track { position: relative; }
.pcms-slide { display: none; width: 100%; }
.pcms-slide.active { display: block; }

.pcms-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,.5); color: #fff; border: 0; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; z-index: 5; opacity: 0; transition: opacity .2s, background .2s; }
.pcms-slider:hover .pcms-nav { opacity: 1; }
.pcms-nav:hover { background: rgba(0,0,0,.8); }
.pcms-nav-prev { right: 10px; }
.pcms-nav-next { left: 10px; }
html[dir="rtl"] .pcms-nav-prev { right: auto; left: 10px; }
html[dir="rtl"] .pcms-nav-next { left: auto; right: 10px; }

.pcms-dots { position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px; z-index: 5; background: rgba(0,0,0,.3); padding: 5px 10px; border-radius: 12px; }
.pcms-dot { width: 8px; height: 8px; border-radius: 50%; border: 0; background: rgba(255,255,255,.5); cursor: pointer; padding: 0; transition: all .2s; }
.pcms-dot.active { background: #fff; transform: scale(1.3); }

.pcms-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: rgba(0,0,0,.1); z-index: 5; }
.pcms-progress-bar { height: 100%; background: #06b6d4; width: 0; }

/* Carousel */
.pcms-carousel { position: relative; }
.pcms-carousel-main { position: relative; overflow: hidden; border-radius: 8px; }
.pcms-carousel-slide { display: none; }
.pcms-carousel-slide.active { display: block; }
.pcms-carousel-thumbs { display: flex; gap: 8px; margin-top: 10px; justify-content: center; flex-wrap: wrap; }
.pcms-thumb { width: 80px; height: 50px; border: 2px solid #e2e8f0; border-radius: 6px; overflow: hidden; cursor: pointer; padding: 0; background: #fff; transition: all .2s; }
.pcms-thumb.active { border-color: #06b6d4; transform: scale(1.05); }
.pcms-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Marquee */
.pcms-marquee { overflow: hidden; position: relative; }
.pcms-marquee-track { display: flex; gap: 20px; animation: adMarqueeScroll linear infinite; width: max-content; }
.pcms-marquee-item { flex-shrink: 0; }
@keyframes adMarqueeScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
html[dir="rtl"] .pcms-marquee-track { animation-name: adMarqueeScrollRtl; }
@keyframes adMarqueeScrollRtl { from { transform: translateX(0); } to { transform: translateX(50%); } }

/* Animation Base */
.pcms-slot[data-anim-duration] {
    animation-duration: var(--anim-duration, 600ms);
    animation-delay: var(--anim-delay, 0ms);
    animation-timing-function: var(--anim-easing, ease-in-out);
    animation-fill-mode: both;
}

/* 45 Effects */
.pcms-anim-none { animation: none !important; }

.pcms-anim-fade { animation-name: adFadeIn; }
@keyframes adFadeIn { from { opacity: 0; } to { opacity: 1; } }

.pcms-anim-slide-left { animation-name: adSlideLeft; }
@keyframes adSlideLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.pcms-anim-slide-right { animation-name: adSlideRight; }
@keyframes adSlideRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.pcms-anim-slide-up { animation-name: adSlideUp; }
@keyframes adSlideUp { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.pcms-anim-slide-down { animation-name: adSlideDown; }
@keyframes adSlideDown { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.pcms-anim-zoom-in { animation-name: adZoomIn; }
@keyframes adZoomIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.pcms-anim-zoom-out { animation-name: adZoomOut; }
@keyframes adZoomOut { from { transform: scale(2); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.pcms-anim-zoom-rotate { animation-name: adZoomRotate; }
@keyframes adZoomRotate { from { transform: scale(0) rotate(-180deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }

.pcms-anim-ken-burns { animation-name: adKenBurns; animation-duration: 10s !important; }
@keyframes adKenBurns { 0% { transform: scale(1); } 100% { transform: scale(1.15); } }

.pcms-anim-flip-x { animation-name: adFlipX; backface-visibility: hidden; }
@keyframes adFlipX { from { transform: perspective(800px) rotateY(90deg); opacity: 0; } to { transform: perspective(800px) rotateY(0); opacity: 1; } }

.pcms-anim-flip-y { animation-name: adFlipY; backface-visibility: hidden; }
@keyframes adFlipY { from { transform: perspective(800px) rotateX(90deg); opacity: 0; } to { transform: perspective(800px) rotateX(0); opacity: 1; } }

.pcms-anim-flip-3d { animation-name: adFlip3D; backface-visibility: hidden; }
@keyframes adFlip3D { from { transform: perspective(1000px) rotate3d(1,1,0,180deg); opacity: 0; } to { transform: perspective(1000px) rotate3d(0,0,0,0); opacity: 1; } }

.pcms-anim-cube { animation-name: adCube; backface-visibility: hidden; }
@keyframes adCube { from { transform: perspective(1000px) rotateY(-90deg) translateZ(-200px); opacity: 0; } to { transform: perspective(1000px) rotateY(0) translateZ(0); opacity: 1; } }

.pcms-anim-fold { animation-name: adFold; }
@keyframes adFold { from { transform: perspective(1000px) rotateX(-90deg); transform-origin: top; opacity: 0; } to { transform: perspective(1000px) rotateX(0); transform-origin: top; opacity: 1; } }

.pcms-anim-rotate-cw { animation-name: adRotateCW; }
@keyframes adRotateCW { from { transform: rotate(-360deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.pcms-anim-rotate-ccw { animation-name: adRotateCCW; }
@keyframes adRotateCCW { from { transform: rotate(360deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.pcms-anim-rotate-spin { animation-name: adRotateSpin; animation-duration: 1.5s !important; }
@keyframes adRotateSpin { from { transform: scale(0) rotate(0); } to { transform: scale(1) rotate(720deg); } }

.pcms-anim-rotate-swing { animation-name: adRotateSwing; transform-origin: top; }
@keyframes adRotateSwing { from { transform: rotate(-90deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.pcms-anim-bounce { animation-name: adBounce; animation-duration: 1s !important; }
@keyframes adBounce { 0%, 20%, 53%, 80%, 100% { transform: translateY(0); } 40%, 43% { transform: translateY(-30px); } 70% { transform: translateY(-15px); } 90% { transform: translateY(-4px); } }

.pcms-anim-bounce-in { animation-name: adBounceIn; }
@keyframes adBounceIn { 0% { transform: scale(0); opacity: 0; } 50% { transform: scale(1.2); opacity: 1; } 100% { transform: scale(1); } }

.pcms-anim-elastic { animation-name: adElastic; animation-duration: 1.2s !important; }
@keyframes adElastic { 0% { transform: scale(0); } 55% { transform: scale(1.15); } 70% { transform: scale(0.95); } 100% { transform: scale(1); } }

.pcms-anim-rubber-band { animation-name: adRubberBand; animation-duration: 1s !important; }
@keyframes adRubberBand { 0% { transform: scale(1,1); } 30% { transform: scale(1.25,0.75); } 40% { transform: scale(0.75,1.25); } 50% { transform: scale(1.15,0.85); } 65% { transform: scale(0.95,1.05); } 100% { transform: scale(1,1); } }

.pcms-anim-slide-diagonal { animation-name: adSlideDiagonal; }
@keyframes adSlideDiagonal { from { transform: translate(-100%, -100%); opacity: 0; } to { transform: translate(0,0); opacity: 1; } }

.pcms-anim-slide-rotate { animation-name: adSlideRotate; }
@keyframes adSlideRotate { from { transform: translateX(-100%) rotate(-30deg); opacity: 0; } to { transform: translateX(0) rotate(0); opacity: 1; } }

.pcms-anim-slide-blur { animation-name: adSlideBlur; }
@keyframes adSlideBlur { from { filter: blur(20px); transform: translateX(-50%); opacity: 0; } to { filter: blur(0); transform: translateX(0); opacity: 1; } }

.pcms-anim-slide-reveal { animation-name: adSlideReveal; }
@keyframes adSlideReveal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }

.pcms-anim-slide-curtain { animation-name: adSlideCurtain; }
@keyframes adSlideCurtain { from { clip-path: inset(0 50% 0 50%); } to { clip-path: inset(0 0 0 0); } }

.pcms-anim-slide-split { animation-name: adSlideSplit; }
@keyframes adSlideSplit { from { clip-path: polygon(50% 0, 50% 0, 50% 100%, 50% 100%); } to { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%); } }

.pcms-anim-fade-blur { animation-name: adFadeBlur; }
@keyframes adFadeBlur { from { filter: blur(20px); opacity: 0; } to { filter: blur(0); opacity: 1; } }

.pcms-anim-fade-scale { animation-name: adFadeScale; }
@keyframes adFadeScale { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.pcms-anim-fade-rotate { animation-name: adFadeRotate; }
@keyframes adFadeRotate { from { transform: rotate(45deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.pcms-anim-fade-up { animation-name: adFadeUp; }
@keyframes adFadeUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.pcms-anim-fade-down { animation-name: adFadeDown; }
@keyframes adFadeDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.pcms-anim-glitch { animation-name: adGlitch; animation-duration: 800ms !important; }
@keyframes adGlitch { 0% { transform: translate(0); } 20% { transform: translate(-5px, 5px); } 40% { transform: translate(-5px, -5px); } 60% { transform: translate(5px, 5px); } 80% { transform: translate(5px, -5px); } 100% { transform: translate(0); } }

.pcms-anim-pixelate { animation-name: adPixelate; }
@keyframes adPixelate { 0% { filter: blur(20px) contrast(20); opacity: 0; } 100% { filter: blur(0) contrast(1); opacity: 1; } }

.pcms-anim-wipe-horizontal { animation-name: adWipeHorizontal; }
@keyframes adWipeHorizontal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0); } }

.pcms-anim-wipe-vertical { animation-name: adWipeVertical; }
@keyframes adWipeVertical { from { clip-path: inset(100% 0 0 0); } to { clip-path: inset(0); } }

.pcms-anim-iris-open { animation-name: adIrisOpen; }
@keyframes adIrisOpen { from { clip-path: circle(0% at 50% 50%); } to { clip-path: circle(150% at 50% 50%); } }

.pcms-anim-iris-close { animation-name: adIrisClose; }
@keyframes adIrisClose { from { clip-path: circle(150% at 50% 50%); } to { clip-path: circle(0% at 50% 50%); } }

.pcms-anim-morph { animation-name: adMorph; }
@keyframes adMorph { 0% { border-radius: 50% 50% 50% 50%; transform: scale(0); } 50% { border-radius: 0% 50% 0% 50%; transform: scale(1.1); } 100% { border-radius: 0%; transform: scale(1); } }

.pcms-anim-liquid { animation-name: adLiquid; }
@keyframes adLiquid { 0% { border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%; transform: scale(0.8); opacity: 0; } 100% { border-radius: 0%; transform: scale(1); opacity: 1; } }

.pcms-anim-wave { animation-name: adWave; }
@keyframes adWave { 0% { transform: translateY(-20px); opacity: 0; } 50% { transform: translateY(10px); opacity: 1; } 100% { transform: translateY(0); } }

.pcms-anim-random { animation-name: adRandom; }
@keyframes adRandom { 0% { transform: rotate(0) scale(0); opacity: 0; } 50% { transform: rotate(180deg) scale(1.2); opacity: 0.7; } 100% { transform: rotate(360deg) scale(1); opacity: 1; } }

.pcms-anim-typewriter { animation-name: adTypewriter; overflow: hidden; white-space: nowrap; }
@keyframes adTypewriter { from { width: 0; } to { width: 100%; } }

@media (max-width: 768px) {
    .pcms-nav { opacity: 1; width: 32px; height: 32px; font-size: 18px; }
    .pcms-slot { margin: 15px auto; }
    .pcms-thumb { width: 60px; height: 40px; }
}
CSS;
    }

    // ═══════════════════════════════════════════════════════════
    // JS پیش‌فرض
    // ═══════════════════════════════════════════════════════════

    public static function getDefaultJs(): string
    {
        return <<<'JS'
(function() {
    'use strict';

    // ═══ Ad Slider ═══
    function initSlider(slider) {
        // 🆕 چک duplicate init
        if (slider.dataset.sliderInit === '1') return;
        slider.dataset.sliderInit = '1';

        const settings = JSON.parse(slider.dataset.adSlider || '{}');
        const slides = slider.querySelectorAll('.pcms-slide');
        const dots = slider.querySelectorAll('.pcms-dot');
        const prev = slider.querySelector('.pcms-nav-prev');
        const next = slider.querySelector('.pcms-nav-next');

        if (slides.length < 2) return;

        let current = 0;
        let timer = null;

        function show(i) {
            slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
            dots.forEach((d, idx) => d.classList.toggle('active', idx === i));
            current = i;
        }

        function nextSlide() { show((current + 1) % slides.length); }
        function prevSlide() { show((current - 1 + slides.length) % slides.length); }

        if (prev) prev.addEventListener('click', () => { prevSlide(); restart(); });
        if (next) next.addEventListener('click', () => { nextSlide(); restart(); });

        dots.forEach((d, i) => {
            d.addEventListener('click', () => { show(i); restart(); });
        });

        // Swipe
        let startX = 0;
        slider.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', e => {
            const diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) nextSlide(); else prevSlide();
                restart();
            }
        }, { passive: true });

        function start() {
            if (settings.autoplay) {
                timer = setInterval(nextSlide, settings.interval || 5000);
            }
        }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function restart() { stop(); start(); }

        if (settings.pauseOnHover) {
            slider.addEventListener('mouseenter', stop);
            slider.addEventListener('mouseleave', start);
        }

        start();
    }

    // ═══ Ad Carousel ═══
    function initCarousel(carousel) {
        const settings = JSON.parse(carousel.dataset.adCarousel || '{}');
        const slides = carousel.querySelectorAll('.pcms-carousel-slide');
        const thumbs = carousel.querySelectorAll('.pcms-thumb');
        const prev = carousel.querySelector('.pcms-nav-prev');
        const next = carousel.querySelector('.pcms-nav-next');

        if (slides.length < 2) return;

        let current = 0;
        let timer = null;

        function show(i) {
            slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
            thumbs.forEach((t, idx) => t.classList.toggle('active', idx === i));
            current = i;
        }

        function nextSlide() { show((current + 1) % slides.length); }
        function prevSlide() { show((current - 1 + slides.length) % slides.length); }

        if (prev) prev.addEventListener('click', () => { prevSlide(); restart(); });
        if (next) next.addEventListener('click', () => { nextSlide(); restart(); });

        thumbs.forEach((t, i) => {
            t.addEventListener('click', () => { show(i); restart(); });
        });

        function start() {
            if (settings.autoplay) {
                timer = setInterval(nextSlide, settings.interval || 5000);
            }
        }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function restart() { stop(); start(); }

        if (settings.pauseOnHover) {
            carousel.addEventListener('mouseenter', stop);
            carousel.addEventListener('mouseleave', start);
        }

        start();
    }

    // ═══ Tracking Impressions ═══
    function trackImpressions() {
        const items = document.querySelectorAll('[data-pcms-track]');
        if (!items.length) return;

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !entry.target.dataset.tracked) {
                        entry.target.dataset.tracked = '1';
                        const url = entry.target.dataset.impressionUrl;
                        if (url) fetch(url, { credentials: 'same-origin' }).catch(() => {});
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            items.forEach(item => observer.observe(item));
        }
    }

    // ═══ Init ═══
    function init() {
        document.querySelectorAll('.pcms-slider').forEach(initSlider);
        document.querySelectorAll('.pcms-carousel').forEach(initCarousel);
        trackImpressions();

        // اعمال CSS Variable برای انیمیشن‌ها
        document.querySelectorAll('.pcms-slot[data-anim-duration]').forEach(el => {
            el.style.setProperty('--anim-duration', el.dataset.animDuration + 'ms');
            el.style.setProperty('--anim-delay', el.dataset.animDelay + 'ms');
            el.style.setProperty('--anim-easing', el.dataset.animEasing);
        });
    }

    // 🆕 Robust init for all browsers (Chrome, Edge, Firefox, Safari)
    function safeInit() {
        try {
            const sliderCount = document.querySelectorAll('.pcms-slider').length;
            console.log('[AdWidget] safeInit — sliders found:', sliderCount);

            init();

            // علامت‌گذاری به‌عنوان initialized
            document.querySelectorAll('.pcms-slider').forEach(function(s) {
                s.dataset.initialized = '1';
            });
        } catch (e) {
            console.error('[AdWidget] Init error:', e);
        }
    }

    // ۱. اگر DOM آماده نیست
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInit);
    } else {
        safeInit();
    }

    // ۲. Fallback: بعد از window.load (اطمینان کامل)
    window.addEventListener('load', function() {
        if (!document.querySelector('.pcms-slider[data-initialized="1"]')) {
            console.log('[AdWidget] load fallback triggered');
            safeInit();
        }
    });

    // ۳. Fallback: setTimeout برای موارد نادر
    setTimeout(function() {
        if (!document.querySelector('.pcms-slider[data-initialized="1"]')) {
            console.log('[AdWidget] setTimeout fallback triggered');
            safeInit();
        }
    }, 300);

    // ۴. Fallback: MutationObserver برای محتوای پویا
    if (typeof MutationObserver !== 'undefined') {
        try {
            const observer = new MutationObserver(function() {
                if (document.querySelector('.pcms-slider:not([data-initialized="1"])')) {
                    console.log('[AdWidget] MutationObserver triggered');
                    safeInit();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        } catch (e) {}
    }
})();
JS;
    }
}

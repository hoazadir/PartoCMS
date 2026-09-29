<?php
/**
 * PartoCMS - Ads Module - AdRenderer v3.0
 * رندر حرفه‌ای تبلیغات — ۷ حالت + ۴۵ افکت
 *
 * @author Hooman Oliaei
 * @version 3.0.0
 */

require_once __DIR__ . '/AdEffects.php';

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

        return '<div class="ad-slot ad-slot-single ' . $animClass . '"' . $attrs . '>'
             . $this->renderBanner($banners[0], $ad)
             . '</div>';
    }

    private function renderSlider(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';
        if (count($banners) === 1) return $this->renderSingle($ad, $options);

        $sliderId = 'ad-slider-' . (int) $ad['id'];
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

        $html = '<div class="ad-slot ad-slot-slider ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="ad-slider" id="' . $sliderId . '" data-ad-slider=\'' 
               . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>';
        
        $html .= '<div class="ad-slider-track">';
        foreach ($banners as $i => $banner) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="ad-slide' . $active . '" data-slide="' . $i . '">';
            $html .= $this->renderBanner($banner, $ad);
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($settings['showNav']) {
            $html .= '<button class="ad-nav ad-nav-prev" type="button" aria-label="قبلی">‹</button>';
            $html .= '<button class="ad-nav ad-nav-next" type="button" aria-label="بعدی">›</button>';
        }

        if ($settings['showDots']) {
            $html .= '<div class="ad-dots">';
            foreach ($banners as $i => $banner) {
                $active = $i === 0 ? ' active' : '';
                $html .= '<button type="button" class="ad-dot' . $active . '" data-slide="' . $i . '"></button>';
            }
            $html .= '</div>';
        }

        if ($settings['showProgress'] && $settings['autoplay']) {
            $html .= '<div class="ad-progress"><div class="ad-progress-bar"></div></div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    private function renderCarousel(array $ad, array $options = []): string
    {
        $banners = $this->getBanners($ad);
        if (empty($banners)) return '';

        $carouselId = 'ad-carousel-' . (int) $ad['id'];
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

        $html = '<div class="ad-slot ad-slot-carousel ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="ad-carousel" id="' . $carouselId . '" data-ad-carousel=\'' 
               . htmlspecialchars(json_encode($settings, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>';

        $html .= '<div class="ad-carousel-main">';
        foreach ($banners as $i => $banner) {
            $active = $i === 0 ? ' active' : '';
            $html .= '<div class="ad-carousel-slide' . $active . '" data-slide="' . $i . '">';
            $html .= $this->renderBanner($banner, $ad);
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($settings['showNav']) {
            $html .= '<button class="ad-nav ad-nav-prev" type="button" aria-label="قبلی">‹</button>';
            $html .= '<button class="ad-nav ad-nav-next" type="button" aria-label="بعدی">›</button>';
        }

        if (count($banners) > 1) {
            $html .= '<div class="ad-carousel-thumbs">';
            foreach ($banners as $i => $banner) {
                $active = $i === 0 ? ' active' : '';
                $img = htmlspecialchars($banner['image_url'] ?? '');
                $html .= '<button type="button" class="ad-thumb' . $active . '" data-slide="' . $i . '">';
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

        return '<div class="ad-slot ad-slot-rotation ' . $animClass . '"' . $attrs . '>'
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

        $html = '<div class="ad-slot ad-slot-grid ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="ad-grid" style="display:grid;grid-template-columns:repeat(' 
               . $columns . ',1fr);gap:' . $gap . 'px;">';
        foreach ($banners as $banner) {
            $html .= '<div class="ad-grid-item">' . $this->renderBanner($banner, $ad) . '</div>';
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

        $html = '<div class="ad-slot ad-slot-stack ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="ad-stack" style="display:flex;flex-direction:column;gap:' . $gap . 'px;">';
        foreach ($banners as $banner) {
            $html .= '<div class="ad-stack-item">' . $this->renderBanner($banner, $ad) . '</div>';
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

        $html = '<div class="ad-slot ad-slot-marquee ' . $animClass . '"' . $attrs . '>';
        $html .= '<div class="ad-marquee" data-duration="' . $duration . 's">';
        $html .= '<div class="ad-marquee-track" style="animation-duration:' . $duration . 's;">';

        foreach ([1, 2] as $repeat) {
            foreach ($banners as $banner) {
                $html .= '<div class="ad-marquee-item">' . $this->renderBanner($banner, $ad) . '</div>';
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
            ? $this->baseUrl . '/modules/ads/ajax/track-impression.php?id=' . $adId
            : '';

        $clickUrl = $this->trackingEnabled
            ? $this->baseUrl . '/modules/ads/ajax/track-click.php?id=' . $adId
            : '';

        $imgTag = '<img src="' . $imgUrl . '" alt="' . $alt . '" style="' . $imgStyle . '">';

        if (!empty($targetUrl)) {
            $target = $targetBlank ? ' target="_blank" rel="noopener"' : '';
            $href = $clickUrl
                ? htmlspecialchars($clickUrl . '&to=' . urlencode($targetUrl))
                : htmlspecialchars($targetUrl);

            $imgTag = '<a href="' . $href . '"' . $target 
                    . ' data-ad-click="' . $adId . '"'
                    . ' style="display:inline-block;max-width:100%;">' 
                    . $imgTag . '</a>';
        }

        $wrapperAttrs = 'class="ad-banner" data-ad-id="' . $adId . '"';
        if ($bannerId > 0) $wrapperAttrs .= ' data-banner-id="' . $bannerId . '"';
        if ($impUrl) $wrapperAttrs .= ' data-impression-url="' . htmlspecialchars($impUrl) . '"';

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
        return 'ad-anim-' . $anim;
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
.ad-slot { margin: 20px auto; text-align: center; position: relative; }
.ad-slot img { border-radius: 6px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); display: block; max-width: 100%; }

.ad-slot-header     { max-width: 970px; }
.ad-slot-footer     { max-width: 970px; margin: 40px auto; }
.ad-slot-sidebar    { max-width: 320px; }
.ad-slot-in-content { max-width: 468px; margin: 24px auto; }
.ad-slot-popup      { max-width: 600px; }
.ad-slot-sticky-footer { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; padding: 8px; box-shadow: 0 -2px 20px rgba(0,0,0,.1); z-index: 9998; margin: 0; }

/* Slider */
.ad-slider { position: relative; overflow: hidden; border-radius: 8px; background: #f1f5f9; }
.ad-slider-track { position: relative; }
.ad-slide { display: none; width: 100%; }
.ad-slide.active { display: block; }

.ad-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,.5); color: #fff; border: 0; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; z-index: 5; opacity: 0; transition: opacity .2s, background .2s; }
.ad-slider:hover .ad-nav { opacity: 1; }
.ad-nav:hover { background: rgba(0,0,0,.8); }
.ad-nav-prev { right: 10px; }
.ad-nav-next { left: 10px; }
html[dir="rtl"] .ad-nav-prev { right: auto; left: 10px; }
html[dir="rtl"] .ad-nav-next { left: auto; right: 10px; }

.ad-dots { position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 6px; z-index: 5; background: rgba(0,0,0,.3); padding: 5px 10px; border-radius: 12px; }
.ad-dot { width: 8px; height: 8px; border-radius: 50%; border: 0; background: rgba(255,255,255,.5); cursor: pointer; padding: 0; transition: all .2s; }
.ad-dot.active { background: #fff; transform: scale(1.3); }

.ad-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: rgba(0,0,0,.1); z-index: 5; }
.ad-progress-bar { height: 100%; background: #06b6d4; width: 0; }

/* Carousel */
.ad-carousel { position: relative; }
.ad-carousel-main { position: relative; overflow: hidden; border-radius: 8px; }
.ad-carousel-slide { display: none; }
.ad-carousel-slide.active { display: block; }
.ad-carousel-thumbs { display: flex; gap: 8px; margin-top: 10px; justify-content: center; flex-wrap: wrap; }
.ad-thumb { width: 80px; height: 50px; border: 2px solid #e2e8f0; border-radius: 6px; overflow: hidden; cursor: pointer; padding: 0; background: #fff; transition: all .2s; }
.ad-thumb.active { border-color: #06b6d4; transform: scale(1.05); }
.ad-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Marquee */
.ad-marquee { overflow: hidden; position: relative; }
.ad-marquee-track { display: flex; gap: 20px; animation: adMarqueeScroll linear infinite; width: max-content; }
.ad-marquee-item { flex-shrink: 0; }
@keyframes adMarqueeScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
html[dir="rtl"] .ad-marquee-track { animation-name: adMarqueeScrollRtl; }
@keyframes adMarqueeScrollRtl { from { transform: translateX(0); } to { transform: translateX(50%); } }

/* Animation Base */
.ad-slot[data-anim-duration] {
    animation-duration: var(--anim-duration, 600ms);
    animation-delay: var(--anim-delay, 0ms);
    animation-timing-function: var(--anim-easing, ease-in-out);
    animation-fill-mode: both;
}

/* 45 Effects */
.ad-anim-none { animation: none !important; }

.ad-anim-fade { animation-name: adFadeIn; }
@keyframes adFadeIn { from { opacity: 0; } to { opacity: 1; } }

.ad-anim-slide-left { animation-name: adSlideLeft; }
@keyframes adSlideLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.ad-anim-slide-right { animation-name: adSlideRight; }
@keyframes adSlideRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.ad-anim-slide-up { animation-name: adSlideUp; }
@keyframes adSlideUp { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.ad-anim-slide-down { animation-name: adSlideDown; }
@keyframes adSlideDown { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.ad-anim-zoom-in { animation-name: adZoomIn; }
@keyframes adZoomIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.ad-anim-zoom-out { animation-name: adZoomOut; }
@keyframes adZoomOut { from { transform: scale(2); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.ad-anim-zoom-rotate { animation-name: adZoomRotate; }
@keyframes adZoomRotate { from { transform: scale(0) rotate(-180deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }

.ad-anim-ken-burns { animation-name: adKenBurns; animation-duration: 10s !important; }
@keyframes adKenBurns { 0% { transform: scale(1); } 100% { transform: scale(1.15); } }

.ad-anim-flip-x { animation-name: adFlipX; backface-visibility: hidden; }
@keyframes adFlipX { from { transform: perspective(800px) rotateY(90deg); opacity: 0; } to { transform: perspective(800px) rotateY(0); opacity: 1; } }

.ad-anim-flip-y { animation-name: adFlipY; backface-visibility: hidden; }
@keyframes adFlipY { from { transform: perspective(800px) rotateX(90deg); opacity: 0; } to { transform: perspective(800px) rotateX(0); opacity: 1; } }

.ad-anim-flip-3d { animation-name: adFlip3D; backface-visibility: hidden; }
@keyframes adFlip3D { from { transform: perspective(1000px) rotate3d(1,1,0,180deg); opacity: 0; } to { transform: perspective(1000px) rotate3d(0,0,0,0); opacity: 1; } }

.ad-anim-cube { animation-name: adCube; backface-visibility: hidden; }
@keyframes adCube { from { transform: perspective(1000px) rotateY(-90deg) translateZ(-200px); opacity: 0; } to { transform: perspective(1000px) rotateY(0) translateZ(0); opacity: 1; } }

.ad-anim-fold { animation-name: adFold; }
@keyframes adFold { from { transform: perspective(1000px) rotateX(-90deg); transform-origin: top; opacity: 0; } to { transform: perspective(1000px) rotateX(0); transform-origin: top; opacity: 1; } }

.ad-anim-rotate-cw { animation-name: adRotateCW; }
@keyframes adRotateCW { from { transform: rotate(-360deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.ad-anim-rotate-ccw { animation-name: adRotateCCW; }
@keyframes adRotateCCW { from { transform: rotate(360deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.ad-anim-rotate-spin { animation-name: adRotateSpin; animation-duration: 1.5s !important; }
@keyframes adRotateSpin { from { transform: scale(0) rotate(0); } to { transform: scale(1) rotate(720deg); } }

.ad-anim-rotate-swing { animation-name: adRotateSwing; transform-origin: top; }
@keyframes adRotateSwing { from { transform: rotate(-90deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.ad-anim-bounce { animation-name: adBounce; animation-duration: 1s !important; }
@keyframes adBounce { 0%, 20%, 53%, 80%, 100% { transform: translateY(0); } 40%, 43% { transform: translateY(-30px); } 70% { transform: translateY(-15px); } 90% { transform: translateY(-4px); } }

.ad-anim-bounce-in { animation-name: adBounceIn; }
@keyframes adBounceIn { 0% { transform: scale(0); opacity: 0; } 50% { transform: scale(1.2); opacity: 1; } 100% { transform: scale(1); } }

.ad-anim-elastic { animation-name: adElastic; animation-duration: 1.2s !important; }
@keyframes adElastic { 0% { transform: scale(0); } 55% { transform: scale(1.15); } 70% { transform: scale(0.95); } 100% { transform: scale(1); } }

.ad-anim-rubber-band { animation-name: adRubberBand; animation-duration: 1s !important; }
@keyframes adRubberBand { 0% { transform: scale(1,1); } 30% { transform: scale(1.25,0.75); } 40% { transform: scale(0.75,1.25); } 50% { transform: scale(1.15,0.85); } 65% { transform: scale(0.95,1.05); } 100% { transform: scale(1,1); } }

.ad-anim-slide-diagonal { animation-name: adSlideDiagonal; }
@keyframes adSlideDiagonal { from { transform: translate(-100%, -100%); opacity: 0; } to { transform: translate(0,0); opacity: 1; } }

.ad-anim-slide-rotate { animation-name: adSlideRotate; }
@keyframes adSlideRotate { from { transform: translateX(-100%) rotate(-30deg); opacity: 0; } to { transform: translateX(0) rotate(0); opacity: 1; } }

.ad-anim-slide-blur { animation-name: adSlideBlur; }
@keyframes adSlideBlur { from { filter: blur(20px); transform: translateX(-50%); opacity: 0; } to { filter: blur(0); transform: translateX(0); opacity: 1; } }

.ad-anim-slide-reveal { animation-name: adSlideReveal; }
@keyframes adSlideReveal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }

.ad-anim-slide-curtain { animation-name: adSlideCurtain; }
@keyframes adSlideCurtain { from { clip-path: inset(0 50% 0 50%); } to { clip-path: inset(0 0 0 0); } }

.ad-anim-slide-split { animation-name: adSlideSplit; }
@keyframes adSlideSplit { from { clip-path: polygon(50% 0, 50% 0, 50% 100%, 50% 100%); } to { clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%); } }

.ad-anim-fade-blur { animation-name: adFadeBlur; }
@keyframes adFadeBlur { from { filter: blur(20px); opacity: 0; } to { filter: blur(0); opacity: 1; } }

.ad-anim-fade-scale { animation-name: adFadeScale; }
@keyframes adFadeScale { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.ad-anim-fade-rotate { animation-name: adFadeRotate; }
@keyframes adFadeRotate { from { transform: rotate(45deg); opacity: 0; } to { transform: rotate(0); opacity: 1; } }

.ad-anim-fade-up { animation-name: adFadeUp; }
@keyframes adFadeUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.ad-anim-fade-down { animation-name: adFadeDown; }
@keyframes adFadeDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.ad-anim-glitch { animation-name: adGlitch; animation-duration: 800ms !important; }
@keyframes adGlitch { 0% { transform: translate(0); } 20% { transform: translate(-5px, 5px); } 40% { transform: translate(-5px, -5px); } 60% { transform: translate(5px, 5px); } 80% { transform: translate(5px, -5px); } 100% { transform: translate(0); } }

.ad-anim-pixelate { animation-name: adPixelate; }
@keyframes adPixelate { 0% { filter: blur(20px) contrast(20); opacity: 0; } 100% { filter: blur(0) contrast(1); opacity: 1; } }

.ad-anim-wipe-horizontal { animation-name: adWipeHorizontal; }
@keyframes adWipeHorizontal { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0); } }

.ad-anim-wipe-vertical { animation-name: adWipeVertical; }
@keyframes adWipeVertical { from { clip-path: inset(100% 0 0 0); } to { clip-path: inset(0); } }

.ad-anim-iris-open { animation-name: adIrisOpen; }
@keyframes adIrisOpen { from { clip-path: circle(0% at 50% 50%); } to { clip-path: circle(150% at 50% 50%); } }

.ad-anim-iris-close { animation-name: adIrisClose; }
@keyframes adIrisClose { from { clip-path: circle(150% at 50% 50%); } to { clip-path: circle(0% at 50% 50%); } }

.ad-anim-morph { animation-name: adMorph; }
@keyframes adMorph { 0% { border-radius: 50% 50% 50% 50%; transform: scale(0); } 50% { border-radius: 0% 50% 0% 50%; transform: scale(1.1); } 100% { border-radius: 0%; transform: scale(1); } }

.ad-anim-liquid { animation-name: adLiquid; }
@keyframes adLiquid { 0% { border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%; transform: scale(0.8); opacity: 0; } 100% { border-radius: 0%; transform: scale(1); opacity: 1; } }

.ad-anim-wave { animation-name: adWave; }
@keyframes adWave { 0% { transform: translateY(-20px); opacity: 0; } 50% { transform: translateY(10px); opacity: 1; } 100% { transform: translateY(0); } }

.ad-anim-random { animation-name: adRandom; }
@keyframes adRandom { 0% { transform: rotate(0) scale(0); opacity: 0; } 50% { transform: rotate(180deg) scale(1.2); opacity: 0.7; } 100% { transform: rotate(360deg) scale(1); opacity: 1; } }

.ad-anim-typewriter { animation-name: adTypewriter; overflow: hidden; white-space: nowrap; }
@keyframes adTypewriter { from { width: 0; } to { width: 100%; } }

@media (max-width: 768px) {
    .ad-nav { opacity: 1; width: 32px; height: 32px; font-size: 18px; }
    .ad-slot { margin: 15px auto; }
    .ad-thumb { width: 60px; height: 40px; }
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
        const settings = JSON.parse(slider.dataset.adSlider || '{}');
        const slides = slider.querySelectorAll('.ad-slide');
        const dots = slider.querySelectorAll('.ad-dot');
        const prev = slider.querySelector('.ad-nav-prev');
        const next = slider.querySelector('.ad-nav-next');

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
        const slides = carousel.querySelectorAll('.ad-carousel-slide');
        const thumbs = carousel.querySelectorAll('.ad-thumb');
        const prev = carousel.querySelector('.ad-nav-prev');
        const next = carousel.querySelector('.ad-nav-next');

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
        const items = document.querySelectorAll('[data-impression-url]');
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
        document.querySelectorAll('.ad-slider').forEach(initSlider);
        document.querySelectorAll('.ad-carousel').forEach(initCarousel);
        trackImpressions();

        // اعمال CSS Variable برای انیمیشن‌ها
        document.querySelectorAll('.ad-slot[data-anim-duration]').forEach(el => {
            el.style.setProperty('--anim-duration', el.dataset.animDuration + 'ms');
            el.style.setProperty('--anim-delay', el.dataset.animDelay + 'ms');
            el.style.setProperty('--anim-easing', el.dataset.animEasing);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;
    }
}

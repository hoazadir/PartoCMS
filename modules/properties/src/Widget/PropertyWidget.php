<?php
declare(strict_types=1);

namespace PartoCMS\Properties\Widget;

final class PropertyWidget
{
    private static bool $assetsInjected = false;

    public static function render(array $options = []): string
    {
        if (!function_exists('properties_service')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $defaults = [
            'style'            => 'grid',
            'limit'            => 6,
            'columns'          => 3,
            'city'             => '',
            'featured'         => false,
            'deal_type'        => '',
            'slider_effect'    => 'slide',
            'slider_autoplay'  => true,
            'slider_delay'     => 4000,
            'slider_speed'     => 600,
            'slider_loop'      => true,
            'slider_navigation'=> true,
        ];

        $dbSettings = function_exists('properties_get_settings') ? properties_get_settings('widget') : [];
        $opts = array_merge($defaults, $dbSettings, $options);

        // نگاشت کلیدهای DB به کلیدهای داخلی
        if (isset($dbSettings['widget_limit']))      $opts['limit']     = (int) $dbSettings['widget_limit'];
        if (isset($dbSettings['widget_columns']))    $opts['columns']   = (int) $dbSettings['widget_columns'];
        if (isset($dbSettings['widget_style']))      $opts['style']     = $dbSettings['widget_style'];
        if (isset($dbSettings['widget_featured']))   $opts['featured']  = (bool) $dbSettings['widget_featured'];
        if (isset($dbSettings['widget_deal_type']))  $opts['deal_type'] = $dbSettings['widget_deal_type'];

        try {
            $service = properties_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) $filters['is_featured'] = true;
            if ($opts['city'] !== '') $filters['city'] = $opts['city'];
            if ($opts['deal_type'] !== '') $filters['deal_type'] = $opts['deal_type'];

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $style = (string)$opts['style'];

            if (empty($items)) {
                return '<div class="alert alert-info text-center my-4">هیچ ملکی برای نمایش وجود ندارد.</div>';
            }

            if ($style === 'slider') {
                $html .= self::renderSlider($items, $opts);
            } else {
                $html .= self::renderGrid($items, $opts);
            }
            return $html;
        } catch (\Throwable $e) {
            return '<div class="alert alert-warning">خطا در بارگذاری املاک: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private static function renderGrid(array $items, array $opts): string
    {
        $cols = max(1, (int)$opts['columns']);
        $html = '<div class="properties-container" data-style="' . htmlspecialchars((string)$opts['style']) . '">';
        $html .= '<div class="properties-grid" style="--cols:' . $cols . ';">';
        foreach ($items as $prop) {
            $html .= self::renderCard($prop, $opts);
        }
        $html .= '</div></div>';
        return $html;
    }

    private static function renderSlider(array $items, array $opts): string
    {
        $id = 'props-swiper-' . bin2hex(random_bytes(4));
        $sliderConfig = [
            'effect'     => $opts['slider_effect'] ?? 'slide',
            'autoplay'   => !empty($opts['slider_autoplay']),
            'delay'      => (int)($opts['slider_delay'] ?? 4000),
            'speed'      => (int)($opts['slider_speed'] ?? 600),
            'loop'       => !empty($opts['slider_loop']),
            'navigation' => !empty($opts['slider_navigation']),
        ];

        $html  = '<div class="properties-container" data-style="slider">';
        $html .= '<div class="swiper ' . $id . '" data-swiper-config=\'' . json_encode($sliderConfig, JSON_UNESCAPED_UNICODE) . '\'>';
        $html .= '<div class="swiper-wrapper">';
        foreach ($items as $prop) {
            $html .= '<div class="swiper-slide">' . self::renderCard($prop, $opts, true) . '</div>';
        }
        $html .= '</div>';
        if ($sliderConfig['navigation']) {
            $html .= '<div class="swiper-button-prev"></div><div class="swiper-button-next"></div>';
        }
        $html .= '<div class="swiper-pagination"></div>';
        $html .= '</div></div>';
        return $html;
    }

    private static function renderCard($prop, array $opts, bool $inSlider = false): string
    {
        $dealLabel = $prop->getDealTypeLabel();
        $typeLabel = $prop->getPropertyTypeLabel();
        $city = $prop->getCity() ?? '—';
        $area = $prop->getArea() ? $prop->getArea() . ' متر' : null;
        $bedrooms = $prop->getBedrooms() ? $prop->getBedrooms() . ' خواب' : null;

        $price = 'تماس بگیرید';
        if ($prop->getDealType() === 'sale' && $prop->getPrice()) {
            $price = number_format((float)$prop->getPrice()) . ' تومان';
        } elseif ($prop->getRentPrice()) {
            $price = 'اجاره: ' . number_format((float)$prop->getRentPrice()) . ' تومان';
        } elseif ($prop->getDeposit()) {
            $price = 'رهن: ' . number_format((float)$prop->getDeposit()) . ' تومان';
        }

        $imgHtml = $prop->getThumbnailUrl()
            ? '<img src="' . htmlspecialchars($prop->getThumbnailUrl()) . '" class="property-card-img" alt="' . htmlspecialchars($prop->getTitle()) . '" loading="lazy">'
            : '<div class="property-card-img-placeholder">🏠</div>';

        $dealClass = match($prop->getDealType()) {
            'sale' => 'sale', 'rent' => 'rent', 'mortgage' => 'mortgage', default => 'sale',
        };

        $metaHtml = '';
        if ($city !== '—') $metaHtml .= '<span>📍 ' . htmlspecialchars($city) . '</span>';
        if ($area) $metaHtml .= '<span>📐 ' . htmlspecialchars($area) . '</span>';
        if ($bedrooms) $metaHtml .= '<span>🛏 ' . htmlspecialchars($bedrooms) . '</span>';

        return '
        <a href="/property.php?id=' . $prop->getId() . '" class="property-card">
            ' . $imgHtml . '
            <div class="property-card-body">
                <div class="property-card-badges">
                    <span class="badge-deal ' . $dealClass . '">' . htmlspecialchars($dealLabel) . '</span>
                    <span class="badge-type">' . htmlspecialchars($typeLabel) . '</span>
                </div>
                <h5 class="property-card-title">' . htmlspecialchars($prop->getTitle()) . '</h5>
                <div class="property-card-meta">' . $metaHtml . '</div>
                <div class="property-card-price">' . htmlspecialchars($price) . '</div>
            </div>
        </a>';
    }

    private static function injectAssets(): string
    {
        if (self::$assetsInjected) return '';
        self::$assetsInjected = true;

        $cssUrl = '/modules/properties/assets/css/properties.css';
        $cssVersion = file_exists(__DIR__ . '/../../assets/css/properties.css')
            ? filemtime(__DIR__ . '/../../assets/css/properties.css') : '1.0';

        $html = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">';
        $html .= '<link rel="stylesheet" href="' . $cssUrl . '?v=' . $cssVersion . '">';
        $html .= '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>';
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".swiper").forEach(function(el) {
                    const config = JSON.parse(el.dataset.swiperConfig || "{}");
                    new Swiper(el, {
                        effect: config.effect || "slide",
                        loop: config.loop !== false,
                        speed: config.speed || 600,
                        autoplay: config.autoplay ? { delay: config.delay || 4000, disableOnInteraction: false } : false,
                        navigation: config.navigation ? { nextEl: el.querySelector(".swiper-button-next"), prevEl: el.querySelector(".swiper-button-prev") } : false,
                        pagination: { el: el.querySelector(".swiper-pagination"), clickable: true },
                        slidesPerView: 1,
                        spaceBetween: 20,
                        breakpoints: {
                            768: { slidesPerView: 2, spaceBetween: 20 },
                            1024: { slidesPerView: 3, spaceBetween: 24 }
                        }
                    });
                });
            });
        </script>';
        return $html;
    }
}

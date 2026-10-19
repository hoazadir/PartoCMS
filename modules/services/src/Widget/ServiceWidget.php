<?php
/**
 * PartoCMS — Services Module
 * Widget: ServiceWidget
 */

declare(strict_types=1);

namespace PartoCMS\Services\Widget;

use PartoCMS\Services\Domain\Entity\ServiceItem;

final class ServiceWidget
{
    private static bool $cssInjected = false;

    public static function render(array $options = []): string
    {
        if (!function_exists('services_service')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $opts = array_merge([
            'style'          => 'grid',
            'limit'          => 6,
            'columns'        => 3,
            'category'       => '',
            'featured'       => false,
            'show_icon'      => true,
            'show_price'     => true,
            'show_features'  => true,
            'show_category'  => true,
        ], $options);

        try {
            $service = services_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) {
                $filters['is_featured'] = true;
            }
            if ($opts['category'] !== '') {
                $filters['category'] = $opts['category'];
            }

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $html .= '<div class="services-container" data-style="' . htmlspecialchars($opts['style']) . '">';

            if (empty($items)) {
                $html .= '<div class="services-empty">هنوز خدمتی اضافه نشده است.</div>';
            } else {
                $html .= $opts['style'] === 'slider'
                    ? self::renderSlider($items, $opts)
                    : self::renderGrid($items, $opts);
            }

            $html .= '</div>';
            return $html;
        } catch (\Throwable $e) {
            error_log('ServiceWidget error: ' . $e->getMessage());
            return '<!-- services widget error -->';
        }
    }

    private static function renderGrid(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="services-grid" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderSlider(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="services-slider" data-slider>';
        $html .= '<div class="services-slider-track" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        if (count($items) > $cols) {
            $html .= '<button type="button" class="services-nav prev" data-nav="prev" aria-label="قبلی">‹</button>';
            $html .= '<button type="button" class="services-nav next" data-nav="next" aria-label="بعدی">›</button>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderCard(ServiceItem $s, array $opts): string
    {
        $classes = 'service-card';
        if ($s->isFeatured()) {
            $classes .= ' is-featured';
        }

        $html = '<article class="' . $classes . '">';

        // Icon / Image
        if ($opts['show_icon']) {
            $html .= self::renderIcon($s);
        }

        // Category
        if ($opts['show_category'] && $s->getCategory()) {
            $html .= '<p class="service-category">' . htmlspecialchars($s->getCategory()) . '</p>';
        }

        // Title
        $html .= '<h3 class="service-title">' . htmlspecialchars($s->getTitle()) . '</h3>';

        // Description
        if ($s->getDescription()) {
            $html .= '<p class="service-description">' . nl2br(htmlspecialchars($s->getDescription())) . '</p>';
        }

        // Features
        if ($opts['show_features'] && !empty($s->getFeatures())) {
            $html .= '<ul class="service-features">';
            foreach ($s->getFeatures() as $feature) {
                $html .= '<li>' . htmlspecialchars($feature) . '</li>';
            }
            $html .= '</ul>';
        }

        // Price
        if ($opts['show_price'] && $s->hasPrice()) {
            $html .= '<div class="service-price">';
            $html .= '<span class="amount">' . number_format((float) $s->getPrice()) . '</span>';
            if ($s->getPriceUnit()) {
                $html .= '<span class="unit">' . htmlspecialchars($s->getPriceUnit()) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '</article>';
        return $html;
    }

    private static function renderIcon(ServiceItem $s): string
    {
        $image = $s->getImageUrl();
        if ($image) {
            return '<img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($s->getTitle()) . '" class="service-image" loading="lazy">';
        }
        $icon = $s->getIcon() ?: '🛠️';
        return '<span class="service-icon">' . htmlspecialchars($icon) . '</span>';
    }

    private static function injectAssets(): string
    {
        if (self::$cssInjected) {
            return '';
        }
        self::$cssInjected = true;
        $cssUrl = '/modules/services/assets/services.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">';
    }
}

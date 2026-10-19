<?php
/**
 * PartoCMS — Portfolio Module
 * Widget: PortfolioWidget
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Widget;

use PartoCMS\Portfolio\Domain\Entity\PortfolioItem;

final class PortfolioWidget
{
    private static bool $cssInjected = false;

    public static function render(array $options = []): string
    {
        if (!function_exists('portfolio_service')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $opts = array_merge([
            'style'          => 'grid',
            'limit'          => 6,
            'columns'        => 3,
            'category'       => '',
            'featured'       => false,
            'show_thumbnail' => true,
            'show_client'    => true,
            'show_category'  => true,
            'show_tags'      => true,
            'show_date'      => true,
            'show_description' => true,
        ], $options);

        try {
            $service = portfolio_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) {
                $filters['is_featured'] = true;
            }
            if ($opts['category'] !== '') {
                $filters['category'] = $opts['category'];
            }

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $html .= '<div class="portfolio-container" data-style="' . htmlspecialchars($opts['style']) . '">';

            if (empty($items)) {
                $html .= '<div class="portfolio-empty">هنوز نمونه‌کاری اضافه نشده است.</div>';
            } else {
                $html .= $opts['style'] === 'slider'
                    ? self::renderSlider($items, $opts)
                    : self::renderGrid($items, $opts);
            }

            $html .= '</div>';
            return $html;
        } catch (\Throwable $e) {
            error_log('PortfolioWidget error: ' . $e->getMessage());
            return '<!-- portfolio widget error -->';
        }
    }

    private static function renderGrid(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="portfolio-grid" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderSlider(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="portfolio-slider" data-slider>';
        $html .= '<div class="portfolio-slider-track" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        if (count($items) > $cols) {
            $html .= '<button type="button" class="portfolio-nav prev" data-nav="prev" aria-label="قبلی">‹</button>';
            $html .= '<button type="button" class="portfolio-nav next" data-nav="next" aria-label="بعدی">›</button>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderCard(PortfolioItem $p, array $opts): string
    {
        $classes = 'portfolio-card';
        if ($p->isFeatured()) {
            $classes .= ' is-featured';
        }

        $html = '<article class="' . $classes . '">';

        // Thumbnail
        if ($opts['show_thumbnail']) {
            $html .= '<div class="portfolio-thumb-wrap">';
            if ($p->getThumbnailUrl()) {
                $html .= '<img src="' . htmlspecialchars($p->getThumbnailUrl()) . '" alt="' . htmlspecialchars($p->getTitle()) . '" class="portfolio-thumb" loading="lazy">';
            } else {
                $html .= '<div class="portfolio-thumb-placeholder">🎨</div>';
            }
            $html .= '</div>';
        }

        // Body
        $html .= '<div class="portfolio-body">';

        if ($opts['show_category'] && $p->getCategory()) {
            $html .= '<span class="portfolio-category">' . htmlspecialchars($p->getCategory()) . '</span>';
        }

        // Title
        $title = htmlspecialchars($p->getTitle());
        if ($p->getProjectUrl()) {
            $title = '<a href="' . htmlspecialchars($p->getProjectUrl()) . '" target="_blank" rel="noopener">' . $title . '</a>';
        }
        $html .= '<h3 class="portfolio-title">' . $title . '</h3>';

        // Client
        if ($opts['show_client'] && $p->getClientName()) {
            $html .= '<p class="portfolio-client">👤 ' . htmlspecialchars($p->getClientName()) . '</p>';
        }

        // Description
        if ($opts['show_description'] && $p->getDescription()) {
            $desc = strip_tags($p->getDescription());
            $html .= '<p class="portfolio-description">' . htmlspecialchars($desc) . '</p>';
        }

        $html .= '</div>';

        // Tags
        if ($opts['show_tags'] && !empty($p->getTags())) {
            $html .= '<div class="portfolio-tags">';
            foreach ($p->getTags() as $tag) {
                $html .= '<span class="portfolio-tag">#' . htmlspecialchars($tag) . '</span>';
            }
            $html .= '</div>';
        }

        // Footer
        $hasFooter = ($opts['show_date'] && $p->getProjectDate()) || $p->getProjectUrl();
        if ($hasFooter) {
            $html .= '<div class="portfolio-footer">';
            if ($opts['show_date'] && $p->getProjectDate()) {
                $html .= '<span class="portfolio-date">📅 ' . htmlspecialchars($p->getProjectDate()) . '</span>';
            } else {
                $html .= '<span></span>';
            }
            if ($p->getProjectUrl()) {
                $html .= '<a href="' . htmlspecialchars($p->getProjectUrl()) . '" target="_blank" rel="noopener" class="portfolio-link">مشاهده پروژه ←</a>';
            }
            $html .= '</div>';
        }

        $html .= '</article>';
        return $html;
    }

    private static function injectAssets(): string
    {
        if (self::$cssInjected) {
            return '';
        }
        self::$cssInjected = true;
        $cssUrl = '/modules/portfolio/assets/portfolio.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">';
    }
}

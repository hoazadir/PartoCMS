<?php
/**
 * PartoCMS — Team Module
 * Widget: TeamWidget
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Widget;

use PartoCMS\Team\Domain\Entity\TeamMember;

final class TeamWidget
{
    private static bool $cssInjected = false;

    public static function render(array $options = []): string
    {
        if (!function_exists('team_service')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $opts = array_merge([
            'style'         => 'grid',
            'limit'         => 6,
            'columns'       => 3,
            'department'    => '',
            'featured'      => false,
            'show_photo'    => true,
            'show_position' => true,
            'show_department' => true,
            'show_bio'      => true,
            'show_social'   => true,
        ], $options);

        try {
            $service = team_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) {
                $filters['is_featured'] = true;
            }
            if ($opts['department'] !== '') {
                $filters['department'] = $opts['department'];
            }

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $html .= '<div class="team-container" data-style="' . htmlspecialchars($opts['style']) . '">';

            if (empty($items)) {
                $html .= '<div class="team-empty">هنوز عضوی به تیم اضافه نشده است.</div>';
            } else {
                $html .= $opts['style'] === 'slider'
                    ? self::renderSlider($items, $opts)
                    : self::renderGrid($items, $opts);
            }

            $html .= '</div>';
            return $html;
        } catch (\Throwable $e) {
            error_log('TeamWidget error: ' . $e->getMessage());
            return '<!-- team widget error -->';
        }
    }

    private static function renderGrid(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="team-grid" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderSlider(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="team-slider" data-slider>';
        $html .= '<div class="team-slider-track" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        if (count($items) > $cols) {
            $html .= '<button type="button" class="team-nav prev" data-nav="prev" aria-label="قبلی">‹</button>';
            $html .= '<button type="button" class="team-nav next" data-nav="next" aria-label="بعدی">›</button>';
        }
        $html .= '</div>';
        return $html;
    }

    private static function renderCard(TeamMember $t, array $opts): string
    {
        $classes = 'team-card';
        if ($t->isFeatured()) {
            $classes .= ' is-featured';
        }

        $html = '<article class="' . $classes . '">';

        if ($opts['show_photo']) {
            $html .= self::renderPhoto($t);
        }

        $html .= '<h3 class="team-name">' . htmlspecialchars($t->getName()) . '</h3>';

        if ($opts['show_position'] && $t->getPosition()) {
            $html .= '<div class="team-position">' . htmlspecialchars($t->getPosition()) . '</div>';
        }

        if ($opts['show_department'] && $t->getDepartment()) {
            $html .= '<div class="team-department">' . htmlspecialchars($t->getDepartment()) . '</div>';
        }

        if ($opts['show_bio'] && $t->getBio()) {
            $html .= '<p class="team-bio">' . nl2br(htmlspecialchars($t->getBio())) . '</p>';
        }

        if ($opts['show_social']) {
            $html .= self::renderSocial($t);
        }

        $html .= '</article>';
        return $html;
    }

    private static function renderPhoto(TeamMember $t): string
    {
        $url = $t->getPhotoUrl();
        if ($url) {
            return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($t->getName()) . '" class="team-photo" loading="lazy">';
        }
        $initial = mb_substr($t->getName(), 0, 1);
        return '<div class="team-photo-placeholder">' . htmlspecialchars($initial) . '</div>';
    }

    private static function renderSocial(TeamMember $t): string
    {
        $links = [];
        if ($t->getLinkedin()) {
            $links[] = '<a href="' . htmlspecialchars($t->getLinkedin()) . '" target="_blank" rel="noopener" title="LinkedIn">in</a>';
        }
        if ($t->getTwitter()) {
            $links[] = '<a href="' . htmlspecialchars($t->getTwitter()) . '" target="_blank" rel="noopener" title="Twitter">𝕏</a>';
        }
        if ($t->getInstagram()) {
            $links[] = '<a href="' . htmlspecialchars($t->getInstagram()) . '" target="_blank" rel="noopener" title="Instagram">📷</a>';
        }
        if ($t->getEmail()) {
            $links[] = '<a href="mailto:' . htmlspecialchars($t->getEmail()) . '" title="Email">✉</a>';
        }

        if (empty($links)) {
            return '';
        }

        return '<div class="team-social">' . implode('', $links) . '</div>';
    }

    private static function injectAssets(): string
    {
        if (self::$cssInjected) {
            return '';
        }
        self::$cssInjected = true;
        $cssUrl = '/modules/team/assets/team.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">';
    }
}

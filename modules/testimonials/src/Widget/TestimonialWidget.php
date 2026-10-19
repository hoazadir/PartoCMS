<?php
/**
 * PartoCMS — Testimonials Module
 * Widget: TestimonialWidget
 *
 * رندر نظرات مشتریان در فرانت‌اند
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Widget;

use PartoCMS\Testimonials\Domain\Entity\Testimonial;

final class TestimonialWidget
{
    private static bool $cssInjected = false;

    // ─────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────

    /**
     * رندر نظرات با پارامترهای داده‌شده
     *
     * @param array{
     *     style?: string,
     *     limit?: int,
     *     columns?: int,
     *     featured?: bool,
     *     rating_min?: int,
     *     show_form?: bool,
     *     show_avatar?: bool,
     *     show_company?: bool,
     *     show_position?: bool,
     *     show_rating?: bool
     * } $options
     */
    public static function render(array $options = []): string
    {
        if (!function_exists('testimonials_service')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $opts = array_merge([
            'style'         => 'grid',
            'limit'         => 6,
            'columns'       => 3,
            'featured'      => false,
            'rating_min'    => 0,
            'show_form'     => false,
            'show_avatar'   => true,
            'show_company'  => true,
            'show_position' => true,
            'show_rating'   => true,
        ], $options);

        try {
            $service = testimonials_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) {
                $filters['is_featured'] = true;
            }
            if ((int) $opts['rating_min'] > 0) {
                $filters['min_rating'] = (int) $opts['rating_min'];
            }

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $html .= '<div class="testimonials-container" data-style="' . htmlspecialchars($opts['style']) . '">';

            if (empty($items)) {
                $html .= '<div class="testimonials-empty">هنوز نظری ثبت نشده است.</div>';
            } else {
                switch ($opts['style']) {
                    case 'slider':
                        $html .= self::renderSlider($items, $opts);
                        break;
                    case 'grid':
                    default:
                        $html .= self::renderGrid($items, $opts);
                        break;
                }
            }

            if ($opts['show_form']) {
                $html .= self::renderForm();
            }

            $html .= '</div>';

            return $html;
        } catch (\Throwable $e) {
            error_log('TestimonialWidget error: ' . $e->getMessage());
            return '<!-- testimonials widget error -->';
        }
    }

    // ─────────────────────────────────────────────────────────
    // Layouts
    // ─────────────────────────────────────────────────────────

    /**
     * @param Testimonial[] $items
     */
    private static function renderGrid(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="testimonials-grid" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * @param Testimonial[] $items
     */
    private static function renderSlider(array $items, array $opts): string
    {
        $cols = max(1, min(4, (int) $opts['columns']));
        $html = '<div class="testimonials-slider" data-slider>';
        $html .= '<div class="testimonials-slider-track" style="--cols: ' . $cols . ';">';
        foreach ($items as $item) {
            $html .= self::renderCard($item, $opts);
        }
        $html .= '</div>';
        if (count($items) > $cols) {
            $html .= '<button type="button" class="testimonials-nav prev" data-nav="prev" aria-label="قبلی">‹</button>';
            $html .= '<button type="button" class="testimonials-nav next" data-nav="next" aria-label="بعدی">›</button>';
        }
        $html .= '</div>';
        return $html;
    }

    // ─────────────────────────────────────────────────────────
    // Card
    // ─────────────────────────────────────────────────────────

    private static function renderCard(Testimonial $t, array $opts): string
    {
        $classes = 'testimonial-card';
        if ($t->isFeatured()) {
            $classes .= ' is-featured';
        }

        $html = '<article class="' . $classes . '">';

        // Header: avatar + name
        $html .= '<div class="testimonial-header">';

        if ($opts['show_avatar']) {
            $html .= self::renderAvatar($t);
        }

        $html .= '<div class="testimonial-info">';
        $html .= '<h4 class="testimonial-name">' . htmlspecialchars($t->getName()) . '</h4>';

        $role = [];
        if ($opts['show_position'] && $t->getPosition()) {
            $role[] = htmlspecialchars($t->getPosition());
        }
        if ($opts['show_company'] && $t->getCompany()) {
            $role[] = '<span class="company">' . htmlspecialchars($t->getCompany()) . '</span>';
        }
        if (!empty($role)) {
            $html .= '<p class="testimonial-role">' . implode(' — ', $role) . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Rating
        if ($opts['show_rating'] && $t->hasRating()) {
            $html .= '<div class="testimonial-stars" title="' . (int) $t->getRating() . ' از ۵">';
            $rating = (int) $t->getRating();
            $html .= str_repeat('★', $rating);
            $html .= '<span class="empty">' . str_repeat('★', 5 - $rating) . '</span>';
            $html .= '</div>';
        }

        // Content
        $html .= '<p class="testimonial-content">' . nl2br(htmlspecialchars($t->getContent())) . '</p>';

        // Date
        if ($t->getPublishedAt()) {
            $date = substr($t->getPublishedAt(), 0, 10);
            $html .= '<p class="testimonial-date">' . htmlspecialchars($date) . '</p>';
        }

        $html .= '</article>';

        return $html;
    }

    private static function renderAvatar(Testimonial $t): string
    {
        $url = $t->getAvatarUrl();
        if ($url) {
            return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($t->getName()) . '" class="testimonial-avatar" loading="lazy">';
        }
        $initial = mb_substr($t->getName(), 0, 1);
        return '<div class="testimonial-avatar placeholder">' . htmlspecialchars($initial) . '</div>';
    }

    // ─────────────────────────────────────────────────────────
    // Form
    // ─────────────────────────────────────────────────────────

    public static function renderForm(): string
    {
        if (!function_exists('testimonials_setting')) {
            require_once __DIR__ . '/../../bootstrap.php';
        }

        $lang = function_exists('getI18n') ? getI18n()->getCurrent() : 'fa-IR';
        $config = testimonials_config();
        $i18n = $config['i18n'][$lang] ?? $config['i18n']['fa-IR'];

        $csrf = '';
        if (function_exists('testimonials_csrf_field')) {
            // admin helper — نیازی نیست
        }

        ob_start();
        ?>
        <form class="testimonial-form" data-testimonial-form method="post" action="">
            <h3>✍️ <?= htmlspecialchars($i18n['submit'] ?? 'ثبت نظر') ?></h3>

            <div class="form-row">
                <div class="field">
                    <label for="tf-name"><?= htmlspecialchars($i18n['name'] ?? 'نام') ?> <span class="req">*</span></label>
                    <input type="text" id="tf-name" name="name" required maxlength="100">
                </div>
                <div class="field">
                    <label for="tf-email"><?= htmlspecialchars($i18n['email'] ?? 'ایمیل') ?></label>
                    <input type="email" id="tf-email" name="email" maxlength="150">
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="tf-company"><?= htmlspecialchars($i18n['company'] ?? 'شرکت') ?></label>
                    <input type="text" id="tf-company" name="company" maxlength="100">
                </div>
                <div class="field">
                    <label for="tf-position"><?= htmlspecialchars($i18n['position'] ?? 'سمت') ?></label>
                    <input type="text" id="tf-position" name="position" maxlength="100">
                </div>
            </div>

            <div class="form-row full">
                <div class="field">
                    <label><?= htmlspecialchars($i18n['rating'] ?? 'امتیاز') ?></label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="tf-star-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                            <label for="tf-star-<?= $i ?>" title="<?= $i ?> ستاره">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="form-row full">
                <div class="field">
                    <label for="tf-content"><?= htmlspecialchars($i18n['content'] ?? 'متن نظر') ?> <span class="req">*</span></label>
                    <textarea id="tf-content" name="content" required maxlength="5000" placeholder="نظر خود را بنویسید..."></textarea>
                </div>
            </div>

            <input type="hidden" name="language" value="<?= htmlspecialchars($lang) ?>">
            <input type="hidden" name="_csrf" value="">

            <button type="submit" class="submit-btn"><?= htmlspecialchars($i18n['submit_button'] ?? 'ارسال نظر') ?></button>

            <div class="testimonial-msg" data-msg></div>
        </form>

        <script>
        (function() {
            document.querySelectorAll('[data-testimonial-form]').forEach(function(form) {
                if (form.dataset.bound) return;
                form.dataset.bound = '1';

                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = form.querySelector('.submit-btn');
                    const msg = form.querySelector('[data-msg]');
                    const data = new FormData(form);

                    btn.disabled = true;
                    btn.textContent = 'در حال ارسال...';
                    msg.className = 'testimonial-msg';
                    msg.textContent = '';

                    try {
                        const res = await fetch('/modules/testimonials/ajax/submit.php', {
                            method: 'POST',
                            body: data,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const json = await res.json();

                        if (json.success) {
                            msg.className = 'testimonial-msg show success';
                            msg.textContent = json.message || '✅ نظر شما ثبت شد. پس از تأیید نمایش داده می‌شود.';
                            form.reset();
                        } else {
                            msg.className = 'testimonial-msg show error';
                            msg.textContent = json.message || 'خطا در ارسال نظر';
                        }
                    } catch (err) {
                        msg.className = 'testimonial-msg show error';
                        msg.textContent = 'خطای شبکه — لطفاً دوباره تلاش کنید';
                    } finally {
                        btn.disabled = false;
                        btn.textContent = <?= json_encode($i18n['submit_button'] ?? 'ارسال نظر', JSON_UNESCAPED_UNICODE) ?>;
                    }
                });
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────
    // Assets injection
    // ─────────────────────────────────────────────────────────

    private static function injectAssets(): string
    {
        if (self::$cssInjected) {
            return '';
        }
        self::$cssInjected = true;

        $cssUrl = '/modules/testimonials/assets/testimonials.css';
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">';
    }
}

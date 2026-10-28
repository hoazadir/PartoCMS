<?php
declare(strict_types=1);

namespace PartoCMS\Reviews\Widget;

class ReviewWidget
{
    public static function render(array $options = []): string
    {
        $legacyPath = dirname(__DIR__, 2) . '/includes/ReviewWidget.php';
        
        if (file_exists($legacyPath)) {
            require_once $legacyPath;
            if (class_exists('\ReviewWidget')) {
                return \ReviewWidget::render();
            }
        }
        return '<div class="alert alert-secondary">ویجت نظرات در دسترس نیست.</div>';
    }
}

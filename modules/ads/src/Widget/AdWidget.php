<?php
declare(strict_types=1);

namespace PartoCMS\Ads\Widget;

class AdWidget
{
    public static function render(array $options = []): string
    {
        $position = $options['position'] ?? 'in-content';
        $legacyPath = dirname(__DIR__, 2) . '/includes/AdWidget.php';
        
        if (file_exists($legacyPath)) {
            require_once $legacyPath;
            // استفاده از ویجت اصلی تبلیغات در صورت وجود
            if (class_exists('\AdWidget')) {
                return \AdWidget::render($position);
            }
        }
        return '<div class="alert alert-secondary">تبلیغات برای این موقعیت تعریف نشده است.</div>';
    }
}

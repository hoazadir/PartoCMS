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

        // ادغام تنظیمات (اولویت: تنظیمات ذخیره‌شده در دیتابیس < آرگومان‌های ورودی)
        $dbSettings = function_exists('properties_get_settings') ? properties_get_settings('widget') : [];
        $opts = array_merge([
            'style'      => $dbSettings['widget_style'] ?? 'grid',
            'limit'      => $dbSettings['widget_limit'] ?? 6,
            'columns'    => $dbSettings['widget_columns'] ?? 3,
            'city'       => '',
            'featured'   => $dbSettings['widget_featured'] ?? false,
            'deal_type'  => $dbSettings['widget_deal_type'] ?? '',
            'show_price' => true,
        ], $dbSettings, $options);

        try {
            $service = properties_service();
            $filters = ['limit' => max(1, (int) $opts['limit'])];
            if ($opts['featured']) $filters['is_featured'] = true;
            if ($opts['city'] !== '') $filters['city'] = $opts['city'];
            if ($opts['deal_type'] !== '') $filters['deal_type'] = $opts['deal_type'];

            $items = $service->getPublished($filters);

            $html = self::injectAssets();
            $html .= '<div class="properties-container" data-style="' . htmlspecialchars((string)$opts['style']) . '">';

            if (empty($items)) {
                $html .= '<div class="alert alert-info text-center my-4">هیچ ملکی برای نمایش وجود ندارد.</div>';
            } else {
                $cols = max(1, (int)$opts['columns']);
                $html .= '<div class="properties-grid" style="--cols:' . $cols . ';">';
                foreach ($items as $prop) {
                    $html .= self::renderCard($prop, $opts);
                }
                $html .= '</div>';
            }

            $html .= '</div>';
            return $html;
        } catch (\Throwable $e) {
            return '<div class="alert alert-warning">خطا در بارگذاری املاک: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    private static function renderCard($prop, array $opts): string
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
            'sale' => 'sale',
            'rent' => 'rent',
            'mortgage' => 'mortgage',
            default => 'sale',
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
        $version = file_exists(__DIR__ . '/../../assets/css/properties.css') 
            ? filemtime(__DIR__ . '/../../assets/css/properties.css') 
            : '1.0';
            
        return '<link rel="stylesheet" href="' . $cssUrl . '?v=' . $version . '">';
    }
}

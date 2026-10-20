<?php
declare(strict_types=1);

namespace PartoCMS\PageBuilder\Widget;

class PageWidget
{
    public static function render(array $page): string
    {
        $layout = json_decode($page['layout_json'] ?? '[]', true);
        $html = '';
        
        if (empty($layout)) {
            return '<div class="container py-5"><div class="alert alert-info text-center">هیچ ماژولی برای این صفحه تعریف نشده است.</div></div>';
        }

        foreach ($layout as $block) {
            $modSlug = $block['slug'] ?? '';
            
            // تلاش برای رندر ماژول‌های شناخته‌شده
            switch ($modSlug) {
                case 'services':
                    $widgetPath = dirname(__DIR__, 3) . '/services/src/Widget/ServiceWidget.php';
                    if (file_exists($widgetPath)) {
                        require_once $widgetPath;
                        $html .= '<div class="container my-5">' . \PartoCMS\Services\Widget\ServiceWidget::render() . '</div>';
                    } else {
                        $html .= self::errorBlock($modSlug);
                    }
                    break;

                case 'shop':
                    $widgetPath = dirname(__DIR__, 3) . '/shop/src/Widget/ShopWidget.php';
                    if (file_exists($widgetPath)) {
                        require_once $widgetPath;
                        $html .= \PartoCMS\Shop\Widget\ShopWidget::render();
                    } else {
                        $html .= self::errorBlock($modSlug);
                    }
                    break;

                case 'portfolio':
                    $widgetPath = dirname(__DIR__, 3) . '/portfolio/src/Widget/PortfolioWidget.php';
                    if (file_exists($widgetPath)) {
                        require_once $widgetPath;
                        $html .= '<div class="container my-5">' . \PartoCMS\Portfolio\Widget\PortfolioWidget::render() . '</div>';
                    } else {
                        $html .= self::errorBlock($modSlug);
                    }
                    break;

                case 'text':
                    $html .= '<div class="container py-4"><div class="alert alert-secondary">اینجا یک ماژول متنی تستی است.</div></div>';
                    break;

                case 'image':
                    $html .= '<div class="container py-4 text-center"><img src="https://via.placeholder.com/800x400" class="img-fluid rounded" alt="تصویر تستی"></div>';
                    break;

                default:
                    // لود داینامیک سایر ماژول‌ها
                    $widgetPath = dirname(__DIR__, 3) . '/' . $modSlug . '/src/Widget/' . ucfirst($modSlug) . 'Widget.php';
                    if (file_exists($widgetPath)) {
                        require_once $widgetPath;
                        $className = '\PartoCMS\\' . ucfirst($modSlug) . '\Widget\\' . ucfirst($modSlug) . 'Widget';
                        if (class_exists($className)) {
                            $html .= '<div class="container my-5">' . $className::render() . '</div>';
                        } else {
                            $html .= self::errorBlock($modSlug);
                        }
                    } else {
                        $html .= self::errorBlock($modSlug);
                    }
                    break;
            }
        }
        return $html;
    }

    private static function errorBlock(string $slug): string
    {
        return '<div class="container my-4"><div class="alert alert-warning text-center">
            ⚠️ ویجت برای ماژول <strong>' . htmlspecialchars($slug) . '</strong> ساخته نشده است.
        </div></div>';
    }
}

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

        // نقشه دقیق تمام ماژول‌های نمایشی
        $widgetMap = [
            'shop'         => ['path' => '/modules/shop/src/Widget/ShopWidget.php', 'class' => '\PartoCMS\Shop\Widget\ShopWidget'],
            'services'     => ['path' => '/modules/services/src/Widget/ServiceWidget.php', 'class' => '\PartoCMS\Services\Widget\ServiceWidget'],
            'portfolio'    => ['path' => '/modules/portfolio/src/Widget/PortfolioWidget.php', 'class' => '\PartoCMS\Portfolio\Widget\PortfolioWidget'],
            'team'         => ['path' => '/modules/team/src/Widget/TeamWidget.php', 'class' => '\PartoCMS\Team\Widget\TeamWidget'],
            'testimonials' => ['path' => '/modules/testimonials/src/Widget/TestimonialWidget.php', 'class' => '\PartoCMS\Testimonials\Widget\TestimonialWidget'],
            'faq'          => ['path' => '/modules/faq/src/Widget/FaqWidget.php', 'class' => '\PartoCMS\Faq\Widget\FaqWidget'],
            'content'      => ['path' => '/modules/content/src/Widget/ContentWidget.php', 'class' => '\PartoCMS\Content\Widget\ContentWidget'],
            'newsletter'   => ['path' => '/modules/newsletter/src/Widget/NewsletterWidget.php', 'class' => '\PartoCMS\Newsletter\Widget\NewsletterWidget'],
            'ads'          => ['path' => '/modules/ads/src/Widget/AdWidget.php', 'class' => '\PartoCMS\Ads\Widget\AdWidget'],
            'reviews'      => ['path' => '/modules/reviews/src/Widget/ReviewWidget.php', 'class' => '\PartoCMS\Reviews\Widget\ReviewWidget'],
            'comments'     => ['path' => '/modules/comments/src/Widget/CommentWidget.php', 'class' => '\PartoCMS\Comments\Widget\CommentWidget'],
        ];

        // محاسبه دقیق مسیر ریشه پروژه (بازگشت ۴ مرحله به عقب از پوشه Widget)
        $rootPath = dirname(__DIR__, 4);

        foreach ($layout as $block) {
            $modSlug = $block['slug'] ?? '';
            
            if (isset($widgetMap[$modSlug])) {
                $config = $widgetMap[$modSlug];
                $widgetPath = $rootPath . $config['path'];
                
                if (file_exists($widgetPath)) {
                    require_once $widgetPath;
                    if (class_exists($config['class'])) {
                        $html .= $config['class']::render();
                    } else {
                        $html .= self::errorBlock($modSlug, 'کلاس ویجت تعریف نشده است.');
                    }
                } else {
                    $html .= self::errorBlock($modSlug, 'فایل ویجت در سرور یافت نشد.');
                }
            } elseif ($modSlug === 'text') {
                $html .= '<div class="container py-4"><div class="alert alert-secondary">اینجا یک ماژول متنی تستی است.</div></div>';
            } elseif ($modSlug === 'image') {
                $html .= '<div class="container py-4 text-center"><img src="https://via.placeholder.com/800x400" class="img-fluid rounded" alt="تصویر تستی"></div>';
            } else {
                $html .= self::errorBlock($modSlug, 'ویجت برای این ماژول تعریف نشده است.');
            }
        }
        return $html;
    }

    private static function errorBlock(string $slug, string $message): string
    {
        return '<div class="container my-4"><div class="alert alert-warning text-center">
            ⚠️ ماژول <strong>' . htmlspecialchars($slug) . '</strong> : ' . htmlspecialchars($message) . '
        </div></div>';
    }
}

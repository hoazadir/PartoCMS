<?php
declare(strict_types=1);

namespace PartoCMS\Shop\Widget;

class ShopWidget
{
    public static function render(array $options = []): string
    {
        $pdo = getDB();
        $limit = $options['limit'] ?? 6;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM shop_products WHERE status = 'published' ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">خطا در بارگذاری محصولات.</div>';
        }

        if (empty($products)) {
            return '<div class="alert alert-info">هیچ محصولی برای نمایش وجود ندارد.</div>';
        }

        ob_start();
        ?>
        <style>
            /* استایل اختصاصی برای قفل کردن تصاویر در هر دستگاهی */
            .shop-widget-img-wrapper {
                width: 100% !important;
                aspect-ratio: 4 / 3 !important;
                overflow: hidden !important;
                background-color: #f8f9fa;
                position: relative;
                border-top-left-radius: 0.75rem;
                border-top-right-radius: 0.75rem;
            }
            .shop-widget-img-wrapper img {
                width: 100% !important;
                height: 100% !important;
                object-fit: cover !important;
                object-position: center !important;
                display: block !important;
            }
        </style>

        <div class="container my-4">
            <div class="row g-3 g-md-4">
                <?php foreach ($products as $p): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0 rounded-4">
                            
                            <div class="shop-widget-img-wrapper">
                                <?php 
                                $imgUrl = '';
                                if (!empty($p['image'])) {
                                    $imgUrl = $p['image'];
                                    // اصلاح آدرس‌های هاردکد شده
                                    $imgUrl = str_replace(['http://127.0.0.1:8080', 'http://localhost:8080', 'http://localhost', 'http://127.0.0.1'], '', $imgUrl);
                                }
                                ?>
                                
                                <?php if ($imgUrl): ?>
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                <?php else: ?>
                                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                                        <i class="bi bi-image fs-1 mb-2" style="color:#cbd5e1;"></i>
                                        <span style="font-size:13px;">بدون تصویر</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body text-center d-flex flex-column">
                                <h5 class="card-title fw-bold fs-6"><?= htmlspecialchars($p['name']) ?></h5>
                                <p class="text-primary fw-bold mb-3 mt-auto"><?= number_format((float)$p['price']) ?> تومان</p>
                                <a href="/modules/shop/product.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary w-100 rounded-pill">مشاهده محصول</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

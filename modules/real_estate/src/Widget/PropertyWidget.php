<?php
declare(strict_types=1);

namespace PartoCMS\RealEstate\Widget;

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;
use PartoCMS\RealEstate\Domain\Entity\Property;

require_once __DIR__ . '/../Domain/Entity/Property.php';
require_once __DIR__ . '/../Infrastructure/Persistence/PropertyRepository.php';

class PropertyWidget
{
    public static function render(array $options = []): string
    {
        $limit = $options['limit'] ?? 6;
        $propertyType = $options['property_type'] ?? '';
        $listingType = $options['listing_type'] ?? '';

        $pdo = getDB();
        $repo = new PropertyRepository($pdo);
        
        $filters = ['status' => 'published'];
        if ($propertyType) $filters['property_type'] = $propertyType;
        if ($listingType) $filters['listing_type'] = $listingType;

        $properties = $repo->findAll($filters, $limit);

        if (empty($properties)) {
            return '<div class="alert alert-info text-center my-4">هیچ ملکی برای نمایش وجود ندارد.</div>';
        }

        ob_start();
        ?>
        <style>
            .re-card { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05); transition:transform 0.2s; height:100%; border:1px solid #f0f0f0; }
            .re-card:hover { transform:translateY(-5px); box-shadow:0 8px 20px rgba(0,0,0,0.1); }
            .re-card-img { width:100%; height:200px; object-fit:cover; background:#f8f9fa; }
            .re-card-body { padding:15px; }
            .re-card-title { font-weight:bold; font-size:16px; margin-bottom:8px; color:#2c3e50; }
            .re-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:bold; }
            .re-badge-sale { background:#dcfce7; color:#166534; }
            .re-badge-rent { background:#dbeafe; color:#1e40af; }
            .re-price { font-size:18px; font-weight:bold; color:#e74c3c; margin:10px 0; }
            .re-details { display:flex; gap:15px; color:#64748b; font-size:12px; margin-bottom:12px; }
        </style>
        <div class="container my-4">
            <div class="row g-4">
                <?php foreach ($properties as $prop): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="re-card">
                            <?php if ($prop->getFeaturedImage()): ?>
                                <img src="<?= htmlspecialchars($prop->getFeaturedImage()) ?>" class="re-card-img" alt="<?= htmlspecialchars($prop->getTitle()) ?>">
                            <?php else: ?>
                                <div class="re-card-img d-flex align-items-center justify-content-center"><span style="font-size:40px;">🏠</span></div>
                            <?php endif; ?>
                            <div class="re-card-body">
                                <span class="re-badge re-badge-<?= $prop->getListingType() === 'sale' ? 'sale' : 'rent' ?>">
                                    <?= $prop->getListingType() === 'sale' ? 'فروش' : 'اجاره' ?>
                                </span>
                                <h5 class="re-card-title mt-2"><?= htmlspecialchars($prop->getTitle()) ?></h5>
                                <div class="re-details">
                                    <span>📐 <?= $prop->getArea() ?> متر</span>
                                    <span>🛏 <?= $prop->getRooms() ?> اتاق</span>
                                </div>
                                <div class="re-price">
                                    <?= $prop->getPrice() ? number_format($prop->getPrice()) . ' تومان' : 'تماس بگیرید' ?>
                                </div>
                                <a href="/property.php?id=<?= $prop->getId() ?>" class="btn btn-outline-primary w-100 btn-sm rounded-pill mt-2">مشاهده جزئیات</a>
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

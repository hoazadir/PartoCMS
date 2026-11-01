<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/real_estate/src/Domain/Entity/Property.php';
require_once __DIR__ . '/modules/real_estate/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: properties.php'); exit; }

$pdo = getDB();
$repo = new PropertyRepository($pdo);
$property = $repo->findById($id);

if (!$property) { http_response_code(404); die('ملک یافت نشد.'); }

$siteName = getSetting('site_name', 'وب‌سایت من');
$gallery = $property->getGallery();
$videoUrl = $property->getVideoUrl();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($property->getTitle()) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .gallery-main { width: 100%; height: 450px; object-fit: cover; border-radius: 12px; cursor: pointer; }
        .gallery-thumb { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; cursor: pointer; opacity: 0.6; transition: 0.2s; }
        .gallery-thumb:hover, .gallery-thumb.active { opacity: 1; border: 2px solid #3b82f6; }
        .spec-item { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 8px; }
        .price-box { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; border-radius: 12px; padding: 20px; text-align: center; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏠 <?= htmlspecialchars($siteName) ?></a>
        <a href="properties.php" class="btn btn-outline-light btn-sm">لیست املاک</a>
    </div>
</nav>

<div class="container mb-5">
    <!-- Header -->
    <div class="mb-4">
        <span class="badge <?= $property->getListingType() === 'sale' ? 'bg-success' : 'bg-info' ?> fs-6">
            <?= $property->getListingType() === 'sale' ? 'فروش' : 'اجاره' ?>
        </span>
        <span class="badge bg-secondary fs-6"><?= htmlspecialchars($property->getPropertyType()) ?></span>
        <h1 class="fw-bold mt-3"><?= htmlspecialchars($property->getTitle()) ?></h1>
        <p class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($property->getCity()) ?> - <?= htmlspecialchars($property->getRegion()) ?></p>
    </div>

    <div class="row g-4">
        <!-- ستون راست: تصاویر و ویدیو -->
        <div class="col-md-8">
            <!-- تصویر شاخص / اسلایدشو -->
            <?php if ($property->getFeaturedImage()): ?>
                <img src="<?= htmlspecialchars($property->getFeaturedImage()) ?>" id="mainImage" class="gallery-main mb-3" alt="<?= htmlspecialchars($property->getTitle()) ?>">
            <?php else: ?>
                <div class="gallery-main bg-light d-flex align-items-center justify-content-center mb-3">
                    <span style="font-size:80px;">🏠</span>
                </div>
            <?php endif; ?>

            <!-- گالری تصاویر -->
            <?php if (!empty($gallery)): ?>
                <div class="row g-2 mb-4">
                    <?php foreach ($gallery as $i => $img): ?>
                        <div class="col-3">
                            <img src="<?= htmlspecialchars($img) ?>" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="changeImage('<?= htmlspecialchars($img) ?>', this)">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ویدیو تبلیغاتی -->
            <?php if ($videoUrl): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">🎬 ویدیو تبلیغاتی</div>
                    <div class="card-body">
                        <?php if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false): ?>
                            <?php
                                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches);
                                $ytId = $matches[1] ?? '';
                            ?>
                            <iframe width="100%" height="400" src="https://www.youtube.com/embed/<?= $ytId ?>" frameborder="0" allowfullscreen style="border-radius:8px;"></iframe>
                        <?php elseif (strpos($videoUrl, 'aparat.com') !== false): ?>
                            <?php
                                preg_match('/aparat\.com\/v\/([a-zA-Z0-9]+)/', $videoUrl, $matches);
                                $apId = $matches[1] ?? '';
                            ?>
                            <iframe width="100%" height="400" src="https://www.aparat.com/video/video/embed/videohash/<?= $apId ?>/vt/frame" frameborder="0" allowfullscreen style="border-radius:8px;"></iframe>
                        <?php else: ?>
                            <video controls width="100%" style="border-radius:8px;">
                                <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/mp4">
                                مرورگر شما از ویدیو پشتیبانی نمی‌کند.
                            </video>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- توضیحات -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">📝 توضیحات ملک</div>
                <div class="card-body">
                    <?= $property->getDescription() ?: '<p class="text-muted">توضیحاتی ثبت نشده است.</p>' ?>
                </div>
            </div>

            <!-- مشخصات -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📐 مشخصات ملک</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="spec-item">📐 <strong><?= $property->getArea() ?></strong> متر</div></div>
                        <div class="col-md-4"><div class="spec-item">🛏 <strong><?= $property->getRooms() ?></strong> اتاق</div></div>
                        <div class="col-md-4"><div class="spec-item">🚿 <strong><?= $property->getBathrooms() ?></strong> سرویس</div></div>
                        <div class="col-md-4"><div class="spec-item">📅 سال ساخت: <strong><?= $property->getYearBuilt() ?? '—' ?></strong></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون چپ: قیمت و مشاور -->
        <div class="col-md-4">
            <div class="price-box mb-4">
                <p class="mb-1" style="opacity:0.8;">قیمت</p>
                <h3 class="fw-bold mb-0">
                    <?= $property->getPrice() ? number_format($property->getPrice()) . ' تومان' : 'تماس بگیرید' ?>
                </h3>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">👤 مشاور املاک</div>
                <div class="card-body">
                    <p class="mb-2"><strong><?= htmlspecialchars($property->getAgentName() ?? 'ثبت نشده') ?></strong></p>
                    <?php if ($property->getAgentPhone()): ?>
                        <p class="mb-2"><i class="bi bi-telephone"></i> <?= htmlspecialchars($property->getAgentPhone()) ?></p>
                    <?php endif; ?>
                    <?php if ($property->getAgentEmail()): ?>
                        <p class="mb-3"><i class="bi bi-envelope"></i> <?= htmlspecialchars($property->getAgentEmail()) ?></p>
                    <?php endif; ?>
                    <a href="tel:<?= htmlspecialchars($property->getAgentPhone() ?? '') ?>" class="btn btn-primary w-100 rounded-pill">📞 تماس با مشاور</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📍 موقعیت</div>
                <div class="card-body">
                    <p class="mb-1"><?= htmlspecialchars($property->getAddress() ?? '—') ?></p>
                    <?php if ($property->getLatitude() && $property->getLongitude()): ?>
                        <div class="mt-3" style="border-radius:8px; overflow:hidden;">
                            <iframe width="100%" height="200" frameborder="0" style="border:0" 
                                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ($property->getLongitude() - 0.01) ?>%2C<?= ($property->getLatitude() - 0.01) ?>%2C<?= ($property->getLongitude() + 0.01) ?>%2C<?= ($property->getLatitude() + 0.01) ?>&layer=mapnik&marker=<?= $property->getLatitude() ?>%2C<?= $property->getLongitude() ?>">
                            </iframe>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-4 mt-5">
    <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمامی حقوق محفوظ است.</p>
</footer>

<script>
    function changeImage(src, el) {
        document.getElementById('mainImage').src = src;
        document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }
</script>

</body>
</html>

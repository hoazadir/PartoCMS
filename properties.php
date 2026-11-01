<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/real_estate/src/Domain/Entity/Property.php';
require_once __DIR__ . '/modules/real_estate/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

$pdo = getDB();
$repo = new PropertyRepository($pdo);

// فیلترها
$filters = [
    'status' => 'published',
    'property_type' => $_GET['property_type'] ?? '',
    'listing_type' => $_GET['listing_type'] ?? '',
    'city' => $_GET['city'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$properties = $repo->findAll($filters, $perPage, $offset);
$totalCount = $repo->count($filters);
$totalPages = ceil($totalCount / $perPage);

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لیست املاک | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .property-card { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05); transition:transform 0.2s; height:100%; }
        .property-card:hover { transform:translateY(-5px); box-shadow:0 8px 20px rgba(0,0,0,0.1); }
        .property-img { width:100%; height:220px; object-fit:cover; }
        .price-tag { color:#e74c3c; font-weight:bold; font-size:18px; }
        .filter-box { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:30px; }
    </style>
</head>
<body>

<!-- هدر سایت -->
<nav class="navbar navbar-dark bg-dark py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏠 <?= htmlspecialchars($siteName) ?></a>
        <a href="index.php" class="btn btn-outline-light btn-sm">صفحه اصلی</a>
    </div>
</nav>

<div class="container">
    <h1 class="fw-bold mb-4">🏠 لیست املاک (<?= number_format($totalCount) ?> مورد)</h1>

    <!-- فیلترها -->
    <div class="filter-box">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">نوع ملک</label>
                <select name="property_type" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="apartment" <?= $filters['property_type']==='apartment'?'selected':'' ?>>آپارتمان</option>
                    <option value="villa" <?= $filters['property_type']==='villa'?'selected':'' ?>>ویلا</option>
                    <option value="land" <?= $filters['property_type']==='land'?'selected':'' ?>>زمین</option>
                    <option value="commercial" <?= $filters['property_type']==='commercial'?'selected':'' ?>>تجاری</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">نوع معامله</label>
                <select name="listing_type" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="sale" <?= $filters['listing_type']==='sale'?'selected':'' ?>>فروش</option>
                    <option value="rent" <?= $filters['listing_type']==='rent'?'selected':'' ?>>اجاره</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">شهر</label>
                <input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['city']) ?>" placeholder="جستجو...">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm w-100">🔍 فیلتر</button>
            </div>
        </form>
    </div>

    <!-- لیست املاک -->
    <div class="row g-4">
        <?php if (empty($properties)): ?>
            <div class="col-12"><div class="alert alert-info text-center">هیچ ملکی یافت نشد.</div></div>
        <?php else: ?>
            <?php foreach ($properties as $prop): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="property-card">
                        <?php if ($prop->getFeaturedImage()): ?>
                            <img src="<?= htmlspecialchars($prop->getFeaturedImage()) ?>" class="property-img" alt="<?= htmlspecialchars($prop->getTitle()) ?>">
                        <?php else: ?>
                            <div class="property-img bg-light d-flex align-items-center justify-content-center"><span style="font-size:50px;">🏠</span></div>
                        <?php endif; ?>
                        <div class="p-3">
                            <span class="badge <?= $prop->getListingType()==='sale'?'bg-success':'bg-info' ?>">
                                <?= $prop->getListingType()==='sale'?'فروش':'اجاره' ?>
                            </span>
                            <h5 class="fw-bold mt-2 mb-2"><?= htmlspecialchars($prop->getTitle()) ?></h5>
                            <p class="text-muted small mb-2">📍 <?= htmlspecialchars($prop->getCity()) ?></p>
                            <div class="d-flex gap-3 text-muted small mb-2">
                                <span>📐 <?= $prop->getArea() ?> متر</span>
                                <span>🛏 <?= $prop->getRooms() ?> اتاق</span>
                            </div>
                            <p class="price-tag mb-3"><?= $prop->getPrice() ? number_format($prop->getPrice()) . ' تومان' : 'تماس بگیرید' ?></p>
                            <a href="property.php?id=<?= $prop->getId() ?>" class="btn btn-outline-primary w-100 rounded-pill">مشاهده جزئیات</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- صفحه‌بندی -->
    <?php if ($totalPages > 1): ?>
        <nav class="my-5">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&property_type=<?= urlencode($filters['property_type']) ?>&listing_type=<?= urlencode($filters['listing_type']) ?>&city=<?= urlencode($filters['city']) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<footer class="bg-dark text-white text-center py-4 mt-5">
    <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمامی حقوق محفوظ است.</p>
</footer>

</body>
</html>

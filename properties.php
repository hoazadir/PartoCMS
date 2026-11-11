<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/properties/bootstrap.php';

$service = properties_service();

$filters = [
    'property_type' => $_GET['property_type'] ?? '',
    'deal_type'     => $_GET['deal_type'] ?? '',
    'city'          => $_GET['city'] ?? '',
    'min_price'     => $_GET['min_price'] ?? '',
    'max_price'     => $_GET['max_price'] ?? '',
    'min_area'      => $_GET['min_area'] ?? '',
    'max_area'      => $_GET['max_area'] ?? '',
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int) (properties_get_settings()['items_per_page'] ?? 12);
$offset  = ($page - 1) * $perPage;

$totalCount = $service->getCount($filters);
$totalPages = (int) ceil($totalCount / $perPage);

$properties = $service->getPublished(array_merge($filters, [
    'limit'  => $perPage,
    'offset' => $offset,
]));

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
    <link rel="stylesheet" href="/modules/properties/assets/css/properties.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .filter-box { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:30px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏠 <?= htmlspecialchars($siteName) ?></a>
        <a href="index.php" class="btn btn-outline-light btn-sm">صفحه اصلی</a>
    </div>
</nav>

<div class="container">
    <h1 class="fw-bold mb-4">🏠 لیست املاک (<?= number_format($totalCount) ?> مورد)</h1>

    <div class="filter-box">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">نوع ملک</label>
                <select name="property_type" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="apartment" <?= $filters['property_type']==='apartment'?'selected':'' ?>>آپارتمان</option>
                    <option value="villa" <?= $filters['property_type']==='villa'?'selected':'' ?>>ویلا</option>
                    <option value="land" <?= $filters['property_type']==='land'?'selected':'' ?>>زمین</option>
                    <option value="office" <?= $filters['property_type']==='office'?'selected':'' ?>>اداری</option>
                    <option value="shop" <?= $filters['property_type']==='shop'?'selected':'' ?>>مغازه</option>
                    <option value="warehouse" <?= $filters['property_type']==='warehouse'?'selected':'' ?>>انبار</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">نوع معامله</label>
                <select name="deal_type" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="sale" <?= $filters['deal_type']==='sale'?'selected':'' ?>>فروش</option>
                    <option value="rent" <?= $filters['deal_type']==='rent'?'selected':'' ?>>اجاره</option>
                    <option value="mortgage" <?= $filters['deal_type']==='mortgage'?'selected':'' ?>>رهن</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">شهر</label>
                <input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['city']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">حداقل متراژ</label>
                <input type="number" name="min_area" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['min_area']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">حداکثر متراژ</label>
                <input type="number" name="max_area" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['max_area']) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">🔍 فیلتر</button>
            </div>
        </form>
    </div>

    <?php if (empty($properties)): ?>
        <div class="alert alert-info text-center">هیچ ملکی یافت نشد.</div>
    <?php else: ?>
        <div class="properties-grid" style="--cols:3;">
            <?php foreach ($properties as $prop): ?>
                <?php
                    $price = 'تماس بگیرید';
                    if ($prop->getDealType() === 'sale' && $prop->getPrice()) {
                        $price = number_format((float)$prop->getPrice()) . ' تومان';
                    } elseif ($prop->getRentPrice()) {
                        $price = 'اجاره: ' . number_format((float)$prop->getRentPrice()) . ' تومان';
                    } elseif ($prop->getDeposit()) {
                        $price = 'رهن: ' . number_format((float)$prop->getDeposit()) . ' تومان';
                    }
                    $dealClass = match($prop->getDealType()) {
                        'sale' => 'sale', 'rent' => 'rent', 'mortgage' => 'mortgage', default => 'sale',
                    };
                ?>
                <a href="/property.php?id=<?= $prop->getId() ?>" class="property-card">
                    <?php if ($prop->getThumbnailUrl()): ?>
                        <img src="<?= htmlspecialchars($prop->getThumbnailUrl()) ?>" class="property-card-img" alt="<?= htmlspecialchars($prop->getTitle()) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="property-card-img-placeholder">🏠</div>
                    <?php endif; ?>
                    <div class="property-card-body">
                        <div class="property-card-badges">
                            <span class="badge-deal <?= $dealClass ?>"><?= htmlspecialchars($prop->getDealTypeLabel()) ?></span>
                            <span class="badge-type"><?= htmlspecialchars($prop->getPropertyTypeLabel()) ?></span>
                        </div>
                        <h5 class="property-card-title"><?= htmlspecialchars($prop->getTitle()) ?></h5>
                        <div class="property-card-meta">
                            <?php if ($prop->getCity()): ?><span>📍 <?= htmlspecialchars($prop->getCity()) ?></span><?php endif; ?>
                            <?php if ($prop->getArea()): ?><span>📐 <?= $prop->getArea() ?> متر</span><?php endif; ?>
                            <?php if ($prop->getBedrooms()): ?><span>🛏 <?= $prop->getBedrooms() ?> خواب</span><?php endif; ?>
                        </div>
                        <div class="property-card-price"><?= htmlspecialchars($price) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>

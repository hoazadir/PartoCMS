<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$pdo = getDB();
$repo = new PropertyRepository($pdo);

// فیلترها
$filters = [
    'status' => $_GET['status'] ?? '',
    'property_type' => $_GET['property_type'] ?? '',
    'listing_type' => $_GET['listing_type'] ?? '',
    'city' => $_GET['city'] ?? '',
];

// صفحه‌بندی
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// دریافت داده‌ها
$properties = $repo->findAll($filters, $perPage, $offset);
$totalCount = $repo->count($filters);
$totalPages = ceil($totalCount / $perPage);

// آمار
$stats = [
    'total' => $repo->count(),
    'published' => $repo->count(['status' => 'published']),
    'draft' => $repo->count(['status' => 'draft']),
    'featured' => $repo->count(['is_featured' => 1]),
];

$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'real_estate';

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">🏠 مدیریت املاک</h2>
        <a href="create.php" class="btn btn-primary px-4">➕ افزودن ملک جدید</a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success">ملک با موفقیت حذف شد.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert alert-success">تغییرات با موفقیت ذخیره شد.</div>
    <?php endif; ?>

    <!-- کارت‌های آماری -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold"><?= number_format($stats['total']) ?></h4>
                        <small class="text-muted">کل املاک</small>
                    </div>
                    <div class="fs-1 text-primary">🏠</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold text-success"><?= number_format($stats['published']) ?></h4>
                        <small class="text-muted">منتشر شده</small>
                    </div>
                    <div class="fs-1 text-success">✅</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold text-warning"><?= number_format($stats['draft']) ?></h4>
                        <small class="text-muted">پیش‌نویس</small>
                    </div>
                    <div class="fs-1 text-warning">📝</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold text-danger"><?= number_format($stats['featured']) ?></h4>
                        <small class="text-muted">ویژه</small>
                    </div>
                    <div class="fs-1 text-danger">⭐</div>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">وضعیت</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                        <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="sold" <?= $filters['status'] === 'sold' ? 'selected' : '' ?>>فروخته شده</option>
                        <option value="rented" <?= $filters['status'] === 'rented' ? 'selected' : '' ?>>اجاره داده شده</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">نوع ملک</label>
                    <select name="property_type" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="apartment" <?= $filters['property_type'] === 'apartment' ? 'selected' : '' ?>>آپارتمان</option>
                        <option value="villa" <?= $filters['property_type'] === 'villa' ? 'selected' : '' ?>>ویلا</option>
                        <option value="land" <?= $filters['property_type'] === 'land' ? 'selected' : '' ?>>زمین</option>
                        <option value="commercial" <?= $filters['property_type'] === 'commercial' ? 'selected' : '' ?>>تجاری</option>
                        <option value="office" <?= $filters['property_type'] === 'office' ? 'selected' : '' ?>>اداری</option>
                        <option value="shop" <?= $filters['property_type'] === 'shop' ? 'selected' : '' ?>>مغازه</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">نوع معامله</label>
                    <select name="listing_type" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="sale" <?= $filters['listing_type'] === 'sale' ? 'selected' : '' ?>>فروش</option>
                        <option value="rent" <?= $filters['listing_type'] === 'rent' ? 'selected' : '' ?>>اجاره</option>
                        <option value="mortgage" <?= $filters['listing_type'] === 'mortgage' ? 'selected' : '' ?>>رهن</option>
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
    </div>

    <!-- لیست املاک -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">تصویر</th>
                            <th>عنوان</th>
                            <th>نوع / معامله</th>
                            <th>شهر</th>
                            <th>قیمت</th>
                            <th>وضعیت</th>
                            <th class="text-end pe-4">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($properties)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">هیچ ملکی یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($properties as $prop): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($prop->getFeaturedImage()): ?>
                                            <img src="<?= htmlspecialchars($prop->getFeaturedImage()) ?>" style="width:60px; height:45px; object-fit:cover; border-radius:6px;" alt="">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px; height:45px; border-radius:6px;">🏠</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($prop->getTitle()) ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($prop->getPropertyType()) ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($prop->getListingType()) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($prop->getCity() ?? '—') ?></td>
                                    <td>
                                        <?php if ($prop->getPrice()): ?>
                                            <?= number_format($prop->getPrice()) ?> تومان
                                        <?php elseif ($prop->getRentPrice()): ?>
                                            اجاره: <?= number_format($prop->getRentPrice()) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusMap = ['published' => 'success', 'draft' => 'warning', 'sold' => 'danger', 'rented' => 'info'];
                                            $color = $statusMap[$prop->getStatus()] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($prop->getStatus()) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="edit.php?id=<?= $prop->getId() ?>" class="btn btn-sm btn-outline-primary">✏️</a>
                                        <a href="delete.php?id=<?= $prop->getId() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف شود؟')">🗑</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filters['status']) ?>&property_type=<?= urlencode($filters['property_type']) ?>&listing_type=<?= urlencode($filters['listing_type']) ?>&city=<?= urlencode($filters['city']) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

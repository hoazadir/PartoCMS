<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/bootstrap.php';

use PartoCMS\Properties\Domain\Entity\Property;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$service = properties_service();
$stats = $service->getStats();

// فیلترها
$filters = [
    'status'        => $_GET['status'] ?? '',
    'property_type' => $_GET['property_type'] ?? '',
    'deal_type'     => $_GET['deal_type'] ?? '',
    'city'          => $_GET['city'] ?? '',
    'search'        => $_GET['search'] ?? '',
];

// صفحه‌بندی
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;

// دریافت داده‌ها
$properties = $service->getAll($filters);
$totalCount = $service->getCount($filters);
$totalPages = (int) ceil($totalCount / $perPage);
$cities = $service->getCities();

$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'properties';

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>
<style>
    .main {
        margin-right: 260px;
        padding: 25px;
    }
    @media (max-width: 1100px) {
        .main { margin-right: 0; padding: 70px 15px 15px; }
    }

    /* استایل جدول املاک */
    .properties-table {
        width: 100%;
        min-width: 900px; /* حداقل عرض برای جلوگیری از فشردگی */
    }
    .properties-table th,
    .properties-table td {
        white-space: nowrap;
        vertical-align: middle;
        padding: 12px 10px;
    }
    .properties-table .col-title {
        min-width: 180px;
        white-space: normal;
    }
    .properties-table .col-type,
    .properties-table .col-deal,
    .properties-table .col-city {
        min-width: 100px;
    }
    .properties-table .col-price {
        min-width: 140px;
        text-align: left;
    }
    .properties-table .col-status {
        min-width: 110px;
    }
    .properties-table .col-actions {
        min-width: 100px;
        text-align: center;
    }
    @media (max-width: 1100px) {
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }
</style>


<div class="main">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">🏠 مدیریت املاک</h2>
        <a href="create.php" class="btn btn-primary px-4">➕ افزودن ملک جدید</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'saved'): ?>
            <div class="alert alert-success">✅ تغییرات با موفقیت ذخیره شد.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">🗑️ ملک با موفقیت حذف شد.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 h-100">
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
            <div class="card border-0 shadow-sm p-3 h-100">
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
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 fw-bold text-warning"><?= number_format($stats['pending']) ?></h4>
                        <small class="text-muted">در انتظار تایید</small>
                    </div>
                    <div class="fs-1 text-warning">⏳</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm p-3 h-100">
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

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">وضعیت</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>
                        <option value="sold" <?= $filters['status'] === 'sold' ? 'selected' : '' ?>>فروخته شده</option>
                        <option value="rented" <?= $filters['status'] === 'rented' ? 'selected' : '' ?>>اجاره داده شده</option>
                        <option value="hidden" <?= $filters['status'] === 'hidden' ? 'selected' : '' ?>>مخفی</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">نوع ملک</label>
                    <select name="property_type" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="apartment" <?= $filters['property_type'] === 'apartment' ? 'selected' : '' ?>>آپارتمان</option>
                        <option value="villa" <?= $filters['property_type'] === 'villa' ? 'selected' : '' ?>>ویلا</option>
                        <option value="land" <?= $filters['property_type'] === 'land' ? 'selected' : '' ?>>زمین</option>
                        <option value="office" <?= $filters['property_type'] === 'office' ? 'selected' : '' ?>>اداری</option>
                        <option value="shop" <?= $filters['property_type'] === 'shop' ? 'selected' : '' ?>>مغازه</option>
                        <option value="warehouse" <?= $filters['property_type'] === 'warehouse' ? 'selected' : '' ?>>انبار</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">نوع معامله</label>
                    <select name="deal_type" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="sale" <?= $filters['deal_type'] === 'sale' ? 'selected' : '' ?>>فروش</option>
                        <option value="rent" <?= $filters['deal_type'] === 'rent' ? 'selected' : '' ?>>اجاره</option>
                        <option value="mortgage" <?= $filters['deal_type'] === 'mortgage' ? 'selected' : '' ?>>رهن</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">شهر</label>
                    <select name="city" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filters['city'] === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">جستجو</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="عنوان، آدرس...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">🔍 فیلتر</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 col-image">تصویر</th>
                            <th class="col-title">عنوان</th>
                            <th class="col-type">نوع</th>
                            <th class="col-deal">معامله</th>
                            <th class="col-city">شهر</th>
                            <th class="col-price">قیمت</th>
                            <th class="col-status">وضعیت</th>
                            <th class="text-end pe-4 col-actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($properties)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <div class="fs-1 mb-3">🏠</div>
                                    <p>هیچ ملکی یافت نشد.</p>
                                    <a href="create.php" class="btn btn-primary btn-sm">➕ افزودن اولین ملک</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($properties as $prop): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($prop->getThumbnailUrl()): ?>
                                            <img src="<?= htmlspecialchars($prop->getThumbnailUrl()) ?>" style="width:60px; height:45px; object-fit:cover; border-radius:6px;" alt="">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="width:60px; height:45px; border-radius:6px;">🏠</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold"><?= htmlspecialchars($prop->getTitle()) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($prop->getPropertyTypeLabel()) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($prop->getDealTypeLabel()) ?></span></td>
                                    <td><?= htmlspecialchars($prop->getCity() ?? '—') ?></td>
                                    <td>
                                        <?php if ($prop->getPrice()): ?>
                                            <?= number_format((float)$prop->getPrice()) ?> تومان
                                        <?php elseif ($prop->getRentPrice()): ?>
                                            اجاره: <?= number_format((float)$prop->getRentPrice()) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $statusMap = [
                                                'published' => ['success', 'منتشر شده'],
                                                'pending' => ['warning', 'در انتظار'],
                                                'sold' => ['danger', 'فروخته شده'],
                                                'rented' => ['info', 'اجاره داده شده'],
                                                'hidden' => ['secondary', 'مخفی'],
                                            ];
                                            $st = $statusMap[$prop->getStatus()] ?? ['secondary', $prop->getStatus()];
                                        ?>
                                        <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="edit.php?id=<?= $prop->getId() ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">✏️</a>
                                        <a href="delete.php?id=<?= $prop->getId() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف شود؟')" title="حذف">🗑</a>
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
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filters['status']) ?>&property_type=<?= urlencode($filters['property_type']) ?>&deal_type=<?= urlencode($filters['deal_type']) ?>&city=<?= urlencode($filters['city']) ?>&search=<?= urlencode($filters['search']) ?>"><?= $i ?></a>
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

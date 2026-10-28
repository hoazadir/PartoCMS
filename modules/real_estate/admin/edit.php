<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$pdo = getDB();
$repo = new PropertyRepository($pdo);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$property = $id ? $repo->findById($id) : null;

if ($id && !$property) {
    header('Location: index.php');
    exit;
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'real_estate';

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<div class="main-content">
    <form method="post" action="save.php" id="propertyForm">
        <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><?= $id ? '✏️ ویرایش ملک' : '➕ افزودن ملک جدید' ?></h2>
            <div>
                <button type="submit" class="btn btn-success px-4">💾 ذخیره</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </div>

        <div class="row g-4">
            <!-- ستون اصلی -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📝 اطلاعات اصلی</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">عنوان ملک *</label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($property?->getTitle() ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">آدرس (Slug)</label>
                            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($property?->getSlug() ?? '') ?>" dir="ltr">
                            <small class="text-muted">اگر خالی بماند، به صورت خودکار از عنوان ساخته می‌شود.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات کامل</label>
                            <textarea name="description" id="descriptionEditor" class="form-control" rows="10"><?= htmlspecialchars($property?->getDescription() ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📐 مشخصات ملک</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">متراژ (متر مربع)</label>
                                <input type="number" name="area" class="form-control" value="<?= $property?->getArea() ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تعداد اتاق</label>
                                <input type="number" name="rooms" class="form-control" value="<?= $property?->getRooms() ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تعداد سرویس</label>
                                <input type="number" name="bathrooms" class="form-control" value="<?= $property?->getBathrooms() ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">سال ساخت</label>
                                <input type="number" name="year_built" class="form-control" value="<?= $property?->getYearBuilt() ?? '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📍 موقعیت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">شهر</label>
                                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($property?->getCity() ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">منطقه</label>
                                <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($property?->getRegion() ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">آدرس دقیق</label>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($property?->getAddress() ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">عرض جغرافیایی (Latitude)</label>
                                <input type="text" name="latitude" class="form-control" value="<?= $property?->getLatitude() ?? '' ?>" dir="ltr" placeholder="35.6892">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">طول جغرافیایی (Longitude)</label>
                                <input type="text" name="longitude" class="form-control" value="<?= $property?->getLongitude() ?? '' ?>" dir="ltr" placeholder="51.3890">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">💰 قیمت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">قیمت فروش (تومان)</label>
                                <input type="number" name="price" class="form-control" value="<?= $property?->getPrice() ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اجاره (تومان)</label>
                                <input type="number" name="rent_price" class="form-control" value="<?= $property?->getRentPrice() ?? '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">رهن (تومان)</label>
                                <input type="number" name="deposit_price" class="form-control" value="<?= $property?->getDepositPrice() ?? '' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">👤 اطلاعات مشاور</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">نام مشاور</label>
                                <input type="text" name="agent_name" class="form-control" value="<?= htmlspecialchars($property?->getAgentName() ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تلفن</label>
                                <input type="text" name="agent_phone" class="form-control" value="<?= htmlspecialchars($property?->getAgentPhone() ?? '') ?>" dir="ltr">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ایمیل</label>
                                <input type="email" name="agent_email" class="form-control" value="<?= htmlspecialchars($property?->getAgentEmail() ?? '') ?>" dir="ltr">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ستون کناری -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">⚙️ تنظیمات</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">نوع ملک</label>
                            <select name="property_type" class="form-select">
                                <?php foreach (['apartment' => 'آپارتمان', 'villa' => 'ویلا', 'land' => 'زمین', 'commercial' => 'تجاری', 'office' => 'اداری', 'shop' => 'مغازه'] as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getPropertyType() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع معامله</label>
                            <select name="listing_type" class="form-select">
                                <?php foreach (['sale' => 'فروش', 'rent' => 'اجاره', 'mortgage' => 'رهن'] as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getListingType() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-select">
                                <?php foreach (['published' => 'منتشر شده', 'draft' => 'پیش‌نویس', 'sold' => 'فروخته شده', 'rented' => 'اجاره داده شده'] as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getStatus() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" <?= $property?->isFeatured() ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isFeatured">⭐ ملک ویژه</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ترتیب نمایش</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= $property?->getSortOrder() ?? 0 ?>">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">🖼️ تصویر شاخص</div>
                    <div class="card-body">
                        <input type="text" name="featured_image" id="featuredImage" class="form-control mb-2" value="<?= htmlspecialchars($property?->getFeaturedImage() ?? '') ?>" placeholder="https://...">
                        <small class="text-muted">URL تصویر را وارد کنید.</small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    tinymce.init({
        selector: '#descriptionEditor',
        height: 400,
        plugins: 'link lists table code',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link table | code',
        directionality: 'rtl'
    });
</script>
</body>
</html>

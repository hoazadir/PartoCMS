<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/bootstrap.php';

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$service = properties_service();
$property = $service->getById($id);
if (!$property) { header('Location: index.php'); exit; }

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
</style>

<div class="main">
    <form method="post" action="save.php" id="propertyForm">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">✏️ ویرایش ملک: <?= htmlspecialchars($property->getTitle()) ?></h2>
            <div>
                <button type="submit" class="btn btn-success px-4">💾 ذخیره تغییرات</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📝 اطلاعات اصلی</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">عنوان ملک *</label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($property->getTitle()) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">آدرس (Slug)</label>
                            <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($property->getSlug() ?? '') ?>" dir="ltr">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات کامل</label>
                            <textarea name="description" id="descriptionEditor" class="form-control" rows="10"><?= htmlspecialchars($property->getDescription() ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📐 مشخصات ملک</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">متراژ</label><input type="number" name="area" class="form-control" value="<?= $property->getArea() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">تعداد اتاق</label><input type="number" name="bedrooms" class="form-control" value="<?= $property->getBedrooms() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">تعداد سرویس</label><input type="number" name="bathrooms" class="form-control" value="<?= $property->getBathrooms() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">سال ساخت</label><input type="number" name="year_built" class="form-control" value="<?= $property->getYearBuilt() ?? '' ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📍 موقعیت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">شهر</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($property->getCity() ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">منطقه</label><input type="text" name="region" class="form-control" value="<?= htmlspecialchars($property->getRegion() ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label">آدرس دقیق</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($property->getAddress() ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">عرض جغرافیایی</label><input type="text" name="latitude" class="form-control" value="<?= $property->getLatitude() ?? '' ?>" dir="ltr"></div>
                            <div class="col-md-6"><label class="form-label">طول جغرافیایی</label><input type="text" name="longitude" class="form-control" value="<?= $property->getLongitude() ?? '' ?>" dir="ltr"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">💰 قیمت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">قیمت فروش</label><input type="number" name="price" class="form-control" value="<?= $property->getPrice() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">اجاره</label><input type="number" name="rent_price" class="form-control" value="<?= $property->getRentPrice() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">رهن</label><input type="number" name="deposit" class="form-control" value="<?= $property->getDeposit() ?? '' ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">👤 مشاور</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><input type="text" name="agent_name" class="form-control" placeholder="نام" value="<?= htmlspecialchars($property->getAgentName() ?? '') ?>"></div>
                            <div class="col-md-4"><input type="text" name="agent_phone" class="form-control" placeholder="تلفن" dir="ltr" value="<?= htmlspecialchars($property->getAgentPhone() ?? '') ?>"></div>
                            <div class="col-md-4"><input type="email" name="agent_email" class="form-control" placeholder="ایمیل" dir="ltr" value="<?= htmlspecialchars($property->getAgentEmail() ?? '') ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">⚙️ تنظیمات</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">نوع ملک</label>
                            <select name="property_type" class="form-select">
                                <option value="apartment" <?= $property->getPropertyType() === 'apartment' ? 'selected' : '' ?>>آپارتمان</option>
                                <option value="villa" <?= $property->getPropertyType() === 'villa' ? 'selected' : '' ?>>ویلا</option>
                                <option value="land" <?= $property->getPropertyType() === 'land' ? 'selected' : '' ?>>زمین</option>
                                <option value="office" <?= $property->getPropertyType() === 'office' ? 'selected' : '' ?>>اداری</option>
                                <option value="shop" <?= $property->getPropertyType() === 'shop' ? 'selected' : '' ?>>مغازه</option>
                                <option value="warehouse" <?= $property->getPropertyType() === 'warehouse' ? 'selected' : '' ?>>انبار</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع معامله</label>
                            <select name="deal_type" class="form-select">
                                <option value="sale" <?= $property->getDealType() === 'sale' ? 'selected' : '' ?>>فروش</option>
                                <option value="rent" <?= $property->getDealType() === 'rent' ? 'selected' : '' ?>>اجاره</option>
                                <option value="mortgage" <?= $property->getDealType() === 'mortgage' ? 'selected' : '' ?>>رهن</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-select">
                                <option value="published" <?= $property->getStatus() === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                                <option value="pending" <?= $property->getStatus() === 'pending' ? 'selected' : '' ?>>در انتظار تایید</option>
                                <option value="sold" <?= $property->getStatus() === 'sold' ? 'selected' : '' ?>>فروخته شده</option>
                                <option value="rented" <?= $property->getStatus() === 'rented' ? 'selected' : '' ?>>اجاره داده شده</option>
                                <option value="hidden" <?= $property->getStatus() === 'hidden' ? 'selected' : '' ?>>مخفی</option>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" <?= $property->isFeatured() ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isFeatured">⭐ ملک ویژه</label>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">🖼️ تصویر شاخص</div>
                    <div class="card-body">
                        <input type="text" name="thumbnail_url" class="form-control mb-2" value="<?= htmlspecialchars($property->getThumbnailUrl() ?? '') ?>" placeholder="https://...">
                        <small class="text-muted">URL تصویر اصلی ملک.</small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
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

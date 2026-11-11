<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/properties/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF عمومی برای این فرم (در کوکی، نه session — برای پایداری در Termux)
if (empty($_COOKIE['properties_public_csrf'])) {
    $newCsrf = bin2hex(random_bytes(32));
    setcookie('properties_public_csrf', $newCsrf, time() + 3600, '/', '', false, true);
    $_COOKIE['properties_public_csrf'] = $newCsrf;
}

$settings = properties_get_settings();
$maxImages = (int) ($settings['max_images'] ?? 10);
$requireApproval = (bool) ($settings['require_approval'] ?? true);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_COOKIE['properties_public_csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است. لطفاً صفحه را رفرش کنید.';
    } else {
        // اعتبارسنجی
        $title = trim((string)($_POST['title'] ?? ''));
        if (mb_strlen($title) < 5) $errors[] = 'عنوان باید حداقل ۵ کاراکتر باشد.';

        $propertyType = (string)($_POST['property_type'] ?? '');
        if (!in_array($propertyType, ['apartment','villa','land','office','shop','warehouse'], true)) {
            $errors[] = 'نوع ملک نامعتبر است.';
        }
        $dealType = (string)($_POST['deal_type'] ?? '');
        if (!in_array($dealType, ['sale','rent','mortgage'], true)) {
            $errors[] = 'نوع معامله نامعتبر است.';
        }
        $area = (int)($_POST['area'] ?? 0);
        if ($area <= 0) $errors[] = 'متراژ معتبر نیست.';

        $city = trim((string)($_POST['city'] ?? ''));
        if (mb_strlen($city) < 2) $errors[] = 'شهر را وارد کنید.';

        $phone = trim((string)($_POST['phone'] ?? ''));
        if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'شماره تماس معتبر نیست (09xxxxxxxxx).';

        $gallery = [];
        if (!empty($_POST['gallery_urls'])) {
            $urls = array_filter(array_map('trim', explode("\n", (string)$_POST['gallery_urls'])));
            $gallery = array_slice($urls, 0, $maxImages);
        }

        // آپلود تصویر شاخص
        $thumbnailUrl = null;
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['thumbnail']['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'فرمت تصویر شاخص نامعتبر است.';
            } elseif ($_FILES['thumbnail']['size'] > 3 * 1024 * 1024) {
                $errors[] = 'حجم تصویر شاخص بیش از ۳ مگابایت.';
            } else {
                $ext = $allowed[$mime];
                $yearMonth = date('Y/m');
                $uploadDir = __DIR__ . '/modules/properties/assets/uploads/properties/' . $yearMonth . '/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
                    $thumbnailUrl = '/modules/properties/assets/uploads/properties/' . $yearMonth . '/' . $filename;
                } else {
                    $errors[] = 'خطا در ذخیره تصویر شاخص.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $service = properties_service();
                $id = $service->submitFromFrontend([
                    'title'         => $title,
                    'description'   => (string)($_POST['description'] ?? ''),
                    'property_type' => $propertyType,
                    'deal_type'     => $dealType,
                    'area'          => $area,
                    'bedrooms'      => (int)($_POST['bedrooms'] ?? 0) ?: null,
                    'bathrooms'     => (int)($_POST['bathrooms'] ?? 0) ?: null,
                    'floor'         => (int)($_POST['floor'] ?? 0) ?: null,
                    'year_built'    => (int)($_POST['year_built'] ?? 0) ?: null,
                    'price'         => $_POST['price'] ?? null,
                    'rent_price'    => $_POST['rent_price'] ?? null,
                    'deposit'       => $_POST['deposit'] ?? null,
                    'address'       => (string)($_POST['address'] ?? ''),
                    'city'          => $city,
                    'region'        => (string)($_POST['region'] ?? ''),
                    'thumbnail_url' => $thumbnailUrl,
                    'gallery'       => $gallery,
                    'amenities'     => $_POST['amenities'] ?? [],
                ], $_SERVER['REMOTE_ADDR'] ?? null);

                $_SESSION['properties_flash'] = [
                    'type' => 'success',
                    'msg'  => $requireApproval
                        ? '✅ آگهی شما ثبت شد و پس از بررسی توسط مدیریت منتشر می‌شود.'
                        : '✅ آگهی شما با موفقیت منتشر شد.',
                ];
                header('Location: submit-property.php');
                exit;
            } catch (\Throwable $e) {
                $errors[] = 'خطا در ثبت آگهی: ' . $e->getMessage();
            }
        }
    }
}

$flash = $_SESSION['properties_flash'] ?? null;
unset($_SESSION['properties_flash']);

$csrf = $_COOKIE['properties_public_csrf'] ?? '';
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت آگهی ملک | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/modules/properties/assets/css/properties.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .form-card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
        .amenity-chip { display:inline-block; margin:4px; padding:6px 12px; border-radius:20px; background:#f1f5f9; cursor:pointer; user-select:none; font-size:14px; transition: 0.15s; }
        .amenity-chip input { display:none; }
        .amenity-chip:has(input:checked) { background:#0d6efd; color:#fff; }
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
    <h1 class="fw-bold mb-4">➕ ثبت آگهی ملک</h1>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card" id="submitForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <h5 class="border-bottom pb-2 mb-3">📋 اطلاعات اصلی</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label">عنوان آگهی <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required minlength="5" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">نوع ملک <span class="text-danger">*</span></label>
                <select name="property_type" class="form-select" required>
                    <option value="apartment" <?= ($_POST['property_type'] ?? '')==='apartment'?'selected':'' ?>>آپارتمان</option>
                    <option value="villa" <?= ($_POST['property_type'] ?? '')==='villa'?'selected':'' ?>>ویلا</option>
                    <option value="land" <?= ($_POST['property_type'] ?? '')==='land'?'selected':'' ?>>زمین</option>
                    <option value="office" <?= ($_POST['property_type'] ?? '')==='office'?'selected':'' ?>>اداری</option>
                    <option value="shop" <?= ($_POST['property_type'] ?? '')==='shop'?'selected':'' ?>>مغازه</option>
                    <option value="warehouse" <?= ($_POST['property_type'] ?? '')==='warehouse'?'selected':'' ?>>انبار</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">نوع معامله <span class="text-danger">*</span></label>
                <select name="deal_type" class="form-select" required>
                    <option value="sale" <?= ($_POST['deal_type'] ?? 'sale')==='sale'?'selected':'' ?>>فروش</option>
                    <option value="rent" <?= ($_POST['deal_type'] ?? '')==='rent'?'selected':'' ?>>اجاره</option>
                    <option value="mortgage" <?= ($_POST['deal_type'] ?? '')==='mortgage'?'selected':'' ?>>رهن</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">متراژ (متر) <span class="text-danger">*</span></label>
                <input type="number" name="area" class="form-control" required min="1" value="<?= htmlspecialchars($_POST['area'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">تعداد خواب</label>
                <input type="number" name="bedrooms" class="form-control" min="0" value="<?= htmlspecialchars($_POST['bedrooms'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">تعداد سرویس</label>
                <input type="number" name="bathrooms" class="form-control" min="0" value="<?= htmlspecialchars($_POST['bathrooms'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">طبقه</label>
                <input type="number" name="floor" class="form-control" value="<?= htmlspecialchars($_POST['floor'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">سال ساخت</label>
                <input type="number" name="year_built" class="form-control" value="<?= htmlspecialchars($_POST['year_built'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📍 موقعیت</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">شهر <span class="text-danger">*</span></label>
                <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">منطقه</label>
                <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($_POST['region'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">آدرس</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">💰 قیمت</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">قیمت فروش (تومان)</label>
                <input type="number" name="price" class="form-control" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">رهن (تومان)</label>
                <input type="number" name="deposit" class="form-control" value="<?= htmlspecialchars($_POST['deposit'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">اجاره ماهیانه (تومان)</label>
                <input type="number" name="rent_price" class="form-control" value="<?= htmlspecialchars($_POST['rent_price'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📝 توضیحات</h5>
        <div class="mb-4">
            <label class="form-label">توضیحات</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <h5 class="border-bottom pb-2 mb-3">✨ امکانات</h5>
        <div class="mb-4">
            <?php foreach (['آسانسور','پارکینگ','استخر','سونا','جکوزی','بالکن','حیاط','انباری','سیستم گرمایش','سیستم سرمایش'] as $a): ?>
                <label class="amenity-chip">
                    <input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($a) ?>"
                        <?= in_array($a, $_POST['amenities'] ?? [], true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($a) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <h5 class="border-bottom pb-2 mb-3">🖼 تصاویر</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">تصویر شاخص (حداکثر ۳ مگابایت)</label>
                <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-md-6">
                <label class="form-label">گالری (هر خط یک URL یا آپلود کنید)</label>
                <textarea name="gallery_urls" class="form-control" rows="3" placeholder="https://example.com/1.jpg&#10;https://example.com/2.jpg"><?= htmlspecialchars($_POST['gallery_urls'] ?? '') ?></textarea>
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📞 اطلاعات تماس</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">شماره تماس <span class="text-danger">*</span></label>
                <input type="tel" name="phone" class="form-control" required pattern="09\d{9}" placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <p class="text-muted small mb-0">
                <?= $requireApproval ? 'آگهی شما پس از بررسی مدیریت منتشر می‌شود.' : 'آگهی شما بلافاصله منتشر می‌شود.' ?>
            </p>
            <button type="submit" class="btn btn-primary px-4">📤 ثبت آگهی</button>
        </div>
    </form>
</div>

<footer class="bg-dark text-white text-center py-4 mt-5">
    <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></p>
</footer>

<script>
document.getElementById('submitForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'در حال ثبت...';
});
</script>

</body>
</html>

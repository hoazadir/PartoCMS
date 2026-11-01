<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/src/Domain/Entity/Property.php';
require_once dirname(__DIR__, 1) . '/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$pdo = getDB();
$repo = new PropertyRepository($pdo);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$property = $id ? $repo->findById($id) : null;

if ($id && !$property) { header('Location: index.php'); exit; }

$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'real_estate';

$galleryStr = '';
if ($property && !empty($property->getGallery())) {
    $galleryStr = implode("\n", $property->getGallery());
}

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
            <!-- ستون اصلی (فرم‌های ملک) -->
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
                            <div class="col-md-4"><label class="form-label">متراژ</label><input type="number" name="area" class="form-control" value="<?= $property?->getArea() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">تعداد اتاق</label><input type="number" name="rooms" class="form-control" value="<?= $property?->getRooms() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">تعداد سرویس</label><input type="number" name="bathrooms" class="form-control" value="<?= $property?->getBathrooms() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">سال ساخت</label><input type="number" name="year_built" class="form-control" value="<?= $property?->getYearBuilt() ?? '' ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📍 موقعیت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">شهر</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($property?->getCity() ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">منطقه</label><input type="text" name="region" class="form-control" value="<?= htmlspecialchars($property?->getRegion() ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label">آدرس دقیق</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($property?->getAddress() ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label">عرض جغرافیایی</label><input type="text" name="latitude" class="form-control" value="<?= $property?->getLatitude() ?? '' ?>" dir="ltr"></div>
                            <div class="col-md-6"><label class="form-label">طول جغرافیایی</label><input type="text" name="longitude" class="form-control" value="<?= $property?->getLongitude() ?? '' ?>" dir="ltr"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">💰 قیمت</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">قیمت فروش</label><input type="number" name="price" class="form-control" value="<?= $property?->getPrice() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">اجاره</label><input type="number" name="rent_price" class="form-control" value="<?= $property?->getRentPrice() ?? '' ?>"></div>
                            <div class="col-md-4"><label class="form-label">رهن</label><input type="number" name="deposit_price" class="form-control" value="<?= $property?->getDepositPrice() ?? '' ?>"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">👤 مشاور</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><input type="text" name="agent_name" class="form-control" placeholder="نام" value="<?= htmlspecialchars($property?->getAgentName() ?? '') ?>"></div>
                            <div class="col-md-4"><input type="text" name="agent_phone" class="form-control" placeholder="تلفن" dir="ltr" value="<?= htmlspecialchars($property?->getAgentPhone() ?? '') ?>"></div>
                            <div class="col-md-4"><input type="email" name="agent_email" class="form-control" placeholder="ایمیل" dir="ltr" value="<?= htmlspecialchars($property?->getAgentEmail() ?? '') ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ستون کناری -->
            <div class="col-md-4">
                <!-- تنظیمات -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">⚙️ تنظیمات</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">نوع ملک</label>
                            <select name="property_type" class="form-select">
                                <?php foreach (['apartment'=>'آپارتمان','villa'=>'ویلا','land'=>'زمین','commercial'=>'تجاری','office'=>'اداری','shop'=>'مغازه'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getPropertyType() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع معامله</label>
                            <select name="listing_type" class="form-select">
                                <?php foreach (['sale'=>'فروش','rent'=>'اجاره','mortgage'=>'رهن'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getListingType() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وضعیت</label>
                            <select name="status" class="form-select">
                                <?php foreach (['published'=>'منتشر شده','draft'=>'پیش‌نویس','sold'=>'فروخته شده','rented'=>'اجاره داده شده'] as $k=>$v): ?>
                                    <option value="<?= $k ?>" <?= $property?->getStatus() === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="isFeatured" <?= $property?->isFeatured() ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isFeatured">⭐ ملک ویژه</label>
                        </div>
                    </div>
                </div>

                <!-- ═══════════ کامپوننت واحد تصویر ═══════════ -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span>🖼️ تصویر شاخص</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#imagePickerModal">
                            📁 انتخاب از آپلودها
                        </button>
                    </div>
                    <div class="card-body text-center">
                        <div id="featuredImagePreview" class="mb-3">
                            <?php if ($property?->getFeaturedImage()): ?>
                                <img id="featuredImageImg" src="<?= htmlspecialchars($property->getFeaturedImage()) ?>" style="width:100%; max-height:200px; object-fit:cover; border-radius:8px;">
                            <?php else: ?>
                                <div id="featuredImagePlaceholder" class="bg-light d-flex align-items-center justify-content-center" style="height:150px; border-radius:8px; color:#94a3b8;">
                                    <div>
                                        <div style="font-size:40px;">🖼️</div>
                                        <div>تصویری انتخاب نشده</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="featured_image" id="featuredImageInput" value="<?= htmlspecialchars($property?->getFeaturedImage() ?? '') ?>">
                        
                        <div class="d-grid gap-2">
                            <input type="file" id="featuredImageFile" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-outline-success" onclick="document.getElementById('featuredImageFile').click()">
                                📤 آپلود تصویر جدید
                            </button>
                        </div>
                    </div>
                </div>

                <!-- گالری -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">📸 گالری تصاویر</div>
                    <div class="card-body">
                        <div id="galleryPreview" class="row g-2 mb-2"></div>
                        <input type="hidden" name="gallery" id="galleryInput" value="<?= htmlspecialchars($galleryStr) ?>">
                        <input type="file" id="galleryFile" accept="image/*" multiple style="display:none;">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="document.getElementById('galleryFile').click()">📁 افزودن تصویر به گالری</button>
                    </div>
                </div>

                <!-- ویدیو -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">🎬 ویدیو تبلیغاتی</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <input type="file" id="videoFile" accept="video/mp4,video/webm" style="display:none;">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="document.getElementById('videoFile').click()">📁 آپلود ویدیو</button>
                        </div>
                        <div id="videoPreview" class="mb-2"></div>
                        <input type="text" name="video_url" id="videoUrlInput" class="form-control mb-2" value="<?= htmlspecialchars($property?->getVideoUrl() ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                        <small class="text-muted">
                            <strong>مثال یوتیوب:</strong> https://youtube.com/watch?v=dQw4w9WgXcQ<br>
                            <strong>مثال آپارات:</strong> https://aparat.com/v/abc123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ═══════════ Modal انتخاب تصویر از آپلودها ═══════════ -->
<div class="modal fade" id="imagePickerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📁 انتخاب تصویر از آپلودها</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="uploadedImagesList" class="row g-2">
                    <div class="text-center py-5 text-muted">در حال بارگذاری...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- IFrame مخفی برای آپلود -->
<iframe name="uploadFrame" id="uploadFrame" style="display:none;"></iframe>
<form id="uploadForm" method="post" action="ajax/upload.php" target="uploadFrame" enctype="multipart/form-data" style="display:none;">
    <input type="file" name="file" id="uploadFileInput">
</form>

<script>
    let uploadTarget = '';

    // ============ آپلود تصویر ============
    function uploadImage(file, target) {
        const form = document.getElementById('uploadForm');
        const input = document.getElementById('uploadFileInput');
        
        // ساخت یک DataTransfer برای ارسال فایل به input
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        
        uploadTarget = target;
        form.submit();
    }

    document.getElementById('uploadFrame').addEventListener('load', function() {
        try {
            const json = JSON.parse(this.contentDocument.body.innerText);
            
            if (!json.success) {
                alert('خطا: ' + json.message);
                return;
            }
            
            if (uploadTarget === 'featured') {
                document.getElementById('featuredImageInput').value = json.url;
                document.getElementById('featuredImagePreview').innerHTML = '<img src="' + json.url + '" style="width:100%; max-height:200px; object-fit:cover; border-radius:8px;">';
            } else if (uploadTarget === 'gallery') {
                const galleryInput = document.getElementById('galleryInput');
                galleryInput.value = (galleryInput.value ? galleryInput.value + '\n' : '') + json.url;
                renderGallery();
            } else if (uploadTarget === 'video') {
                document.getElementById('videoUrlInput').value = json.url;
                document.getElementById('videoPreview').innerHTML = '<video src="' + json.url + '" controls style="width:100%; border-radius:8px;"></video>';
            }
            uploadTarget = '';
        } catch (e) {
            // اگر خطا داشت، مهم نیست (شاید پاسخ خالی است)
        }
    });

    // ============ آپلود تصویر شاخص ============
    document.getElementById('featuredImageFile').addEventListener('change', function() {
        if (this.files[0]) uploadImage(this.files[0], 'featured');
    });

    // ============ آپلود گالری ============
    document.getElementById('galleryFile').addEventListener('change', function() {
        if (this.files.length > 0) {
            Array.from(this.files).forEach(file => {
                // برای هر فایل، یک بار آپلود می‌کنیم (به صورت همزمان ساده‌تر است)
                uploadImage(file, 'gallery');
            });
        }
    });

    // ============ آپلود ویدیو ============
    document.getElementById('videoFile').addEventListener('change', function() {
        if (this.files[0]) uploadImage(this.files[0], 'video');
    });

    // ============ Modal انتخاب تصویر ============
    document.getElementById('imagePickerModal').addEventListener('show.bs.modal', async function() {
        const list = document.getElementById('uploadedImagesList');
        list.innerHTML = '<div class="text-center py-5 text-muted">در حال بارگذاری...</div>';
        
        try {
            const res = await fetch('ajax/list-uploads.php');
            const images = await res.json();
            
            if (images.length === 0) {
                list.innerHTML = '<div class="text-center py-5 text-muted">هیچ تصویری در پوشه آپلود وجود ندارد.</div>';
                return;
            }
            
            list.innerHTML = '';
            images.forEach(url => {
                const div = document.createElement('div');
                div.className = 'col-4 col-md-3';
                div.innerHTML = '<img src="' + url + '" style="width:100%; height:80px; object-fit:cover; border-radius:6px; cursor:pointer; border:2px solid transparent;" onmouseover="this.style.borderColor=\'#3b82f6\'" onmouseout="this.style.borderColor=\'transparent\'">';
                div.querySelector('img').addEventListener('click', function() {
                    document.getElementById('featuredImageInput').value = url;
                    document.getElementById('featuredImagePreview').innerHTML = '<img src="' + url + '" style="width:100%; max-height:200px; object-fit:cover; border-radius:8px;">';
                    const modal = bootstrap.Modal.getInstance(document.getElementById('imagePickerModal'));
                    modal.hide();
                });
                list.appendChild(div);
            });
        } catch (e) {
            list.innerHTML = '<div class="text-center py-5 text-danger">خطا در بارگذاری تصاویر.</div>';
        }
    });

    // ============ گالری ============
    function renderGallery() {
        const input = document.getElementById('galleryInput');
        const urls = input.value.split('\n').filter(u => u.trim());
        const preview = document.getElementById('galleryPreview');
        preview.innerHTML = '';
        urls.forEach((url, i) => {
            const div = document.createElement('div');
            div.className = 'col-4';
            div.innerHTML = '<div style="position:relative;"><img src="' + url + '" style="width:100%; height:80px; object-fit:cover; border-radius:6px;"><button type="button" onclick="removeGallery(' + i + ')" style="position:absolute; top:-5px; left:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer;">×</button></div>';
            preview.appendChild(div);
        });
    }

    function removeGallery(index) {
        const input = document.getElementById('galleryInput');
        const urls = input.value.split('\n').filter(u => u.trim());
        urls.splice(index, 1);
        input.value = urls.join('\n');
        renderGallery();
    }

    // لود اولیه گالری
    document.addEventListener('DOMContentLoaded', function() {
        renderGallery();
    });

    // TinyMCE
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

<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ─── چک لاگین ───
if (!isLoggedIn()) {
    header('Location: login.php?redirect=register-agency.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// ─── چک تکراری: آیا کاربر قبلاً بنگاه دارد؟ ───
try {
    $pdoCheck = getDB();
    $stmtCheck = $pdoCheck->prepare("SELECT id, status FROM agencies WHERE user_id = ? LIMIT 1");
    $stmtCheck->execute([$currentUserId]);
    $existingAgency = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if ($existingAgency) {
        $infoMessage = 'شما قبلاً درخواست ثبت بنگاه داده‌اید. وضعیت: ' . 
            ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'][$existingAgency['status']] ?? $existingAgency['status'];
    }
} catch (Throwable $e) {
    error_log('Agency check error: ' . $e->getMessage());
}

// ─── چک لاگین ───
if (!isLoggedIn()) {
    header('Location: login.php?redirect=register-agency.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// ─── چک تکراری: آیا کاربر قبلاً بنگاه دارد؟ ───
try {
    $pdoCheck = getDB();
    $stmtCheck = $pdoCheck->prepare("SELECT id, status FROM agencies WHERE user_id = ? LIMIT 1");
    $stmtCheck->execute([$currentUserId]);
    $existingAgency = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if ($existingAgency) {
        $infoMessage = 'شما قبلاً درخواست ثبت بنگاه داده‌اید. وضعیت: ' . 
            ['pending' => 'در انتظار تأیید', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'][$existingAgency['status']] ?? $existingAgency['status'];
    }
} catch (Throwable $e) {
    error_log('Agency check error: ' . $e->getMessage());
}

// CSRF
if (empty($_SESSION['agency_csrf'])) {
    $_SESSION['agency_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['agency_csrf'], $_POST['_csrf'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است.';
    } else {
        // اعتبارسنجی
        $name         = trim((string)($_POST['name'] ?? ''));
        $licenseNo    = trim((string)($_POST['license_no'] ?? ''));
        $ownerName    = trim((string)($_POST['owner_name'] ?? ''));
        $ownerNc      = trim((string)($_POST['owner_national_code'] ?? ''));
        $phone        = trim((string)($_POST['phone'] ?? ''));
        $email        = trim((string)($_POST['email'] ?? ''));
        $address      = trim((string)($_POST['address'] ?? ''));
        $cityId       = (int)($_POST['city_id'] ?? 0);
        $latitude     = trim((string)($_POST['latitude'] ?? ''));
        $longitude    = trim((string)($_POST['longitude'] ?? ''));
        
        // آپلودها
        $docLicense   = (string)($_POST['doc_license'] ?? '');
        $docIdFront   = (string)($_POST['doc_owner_id_front'] ?? '');
        $docIdBack    = (string)($_POST['doc_owner_id_back'] ?? '');
        $docDeed      = (string)($_POST['doc_deed'] ?? '');

        if (mb_strlen($name) < 3) $errors[] = 'نام بنگاه را وارد کنید.';
        if (mb_strlen($licenseNo) < 3) $errors[] = 'شماره پروانه کسب را وارد کنید.';
        if (mb_strlen($ownerName) < 3) $errors[] = 'نام مدیر/مالک را وارد کنید.';
        if (!preg_match('/^\d{10}$/', $ownerNc)) $errors[] = 'کد ملی باید ۱۰ رقم باشد.';
        if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'شماره تماس باید 09xxxxxxxxx باشد.';
        if (mb_strlen($address) < 5) $errors[] = 'آدرس دقیق را وارد کنید.';
        if ($cityId <= 0) $errors[] = 'شهر را انتخاب کنید.';
        if (empty($latitude) || empty($longitude)) $errors[] = 'موقعیت مکانی را روی نقشه انتخاب کنید.';
        if (empty($docLicense)) $errors[] = 'تصویر پروانه کسب الزامی است.';
        if (empty($docIdFront) || empty($docIdBack)) $errors[] = 'تصویر کارت ملی الزامی است.';
        if (empty($docDeed)) $errors[] = 'تصویر سند یا اجاره‌نامه الزامی است.';

        if (empty($errors)) {
            try {
                $pdo = getDB();

                // چک تکراری
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$phone]);
                if ($stmt->fetchColumn() > 0) $errors[] = 'شماره تلفن قبلاً ثبت شده است.';

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM agencies WHERE license_no = ?");
                $stmt->execute([$licenseNo]);
                if ($stmt->fetchColumn() > 0) $errors[] = 'شماره پروانه قبلاً ثبت شده است.';

                if (empty($errors)) {
                    $pdo->beginTransaction();

                    // ۱. نقش
                    // ─── استفاده از کاربر فعلی (به جای ساخت کاربر جدید) ───
                    $userId = $currentUserId;

                    // به‌روزرسانی اطلاعات کاربر (اختیاری)
                    if (!empty($email) || !empty($ownerNc) || !empty($phone)) {
                        $stmt = $pdo->prepare("
                            UPDATE users SET 
                                email = COALESCE(NULLIF(?, ''), email),
                                national_code = COALESCE(NULLIF(?, ''), national_code),
                                phone = COALESCE(NULLIF(?, ''), phone)
                            WHERE id = ?
                        ");
                        $stmt->execute([$email, $ownerNc, $phone, $userId]);
                    }

                    // ─── بنگاه
                    $stmt = $pdo->prepare("
                        INSERT INTO agencies (
                            user_id, name, license_no, license_image, owner_name, 
                            owner_national_code, owner_id_front, owner_id_back, 
                            address, city_id, latitude, longitude, deed_image, phone, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $userId, $name, $licenseNo, $docLicense, $ownerName,
                        $ownerNc, $docIdFront, $docIdBack,
                        $address, $cityId, $latitude, $longitude, $docDeed, $phone
                    ]);

                    $pdo->commit();
                    $_SESSION['agency_csrf'] = null;
                    $success = true;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'خطا در ثبت‌نام: ' . $e->getMessage();
            }
        }
    }
}

$csrf = $_SESSION['agency_csrf'] ?? '';
$siteName = getSetting('site_name', 'وب‌سایت من');
$neshanApiKey = '';
if (function_exists('properties_get_settings')) {
    $settings = properties_get_settings();
    $neshanApiKey = $settings['neshan_api_key'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام بنگاه املاک | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="/assets/css/notifications.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .form-card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
        #map { height: 300px; border-radius: 8px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏠 <?= htmlspecialchars($siteName) ?></a>
        <a href="index.php" class="btn btn-outline-light btn-sm">صفحه اصلی</a>
    </div>
</nav>

<div class="container mb-5">
    <h1 class="fw-bold mb-4">🏢 ثبت‌نام بنگاه املاک</h1>

    <?php if ($success): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast('✅ ثبت‌نام بنگاه با موفقیت انجام شد. پس از بررسی مدارک، پنل شما فعال می‌شود.', 'success', 0));</script>
    <?php elseif ($errors): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                <?php foreach ($errors as $err): ?>
                    showToast(<?= json_encode($err, JSON_UNESCAPED_UNICODE) ?>, 'error', 8000);
                <?php endforeach; ?>
            });
        </script>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" class="form-card" id="agencyForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <h5 class="border-bottom pb-2 mb-3">🏢 اطلاعات بنگاه</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">نام بنگاه <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">شماره پروانه کسب <span class="text-danger">*</span></label>
                <input type="text" name="license_no" class="form-control" required value="<?= htmlspecialchars($_POST['license_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">نام مدیر/مالک <span class="text-danger">*</span></label>
                <input type="text" name="owner_name" class="form-control" required value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">کد ملی مدیر <span class="text-danger">*</span></label>
                <input type="text" name="owner_national_code" class="form-control" required pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($_POST['owner_national_code'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">شماره تماس <span class="text-danger">*</span></label>
                <input type="tel" name="phone" class="form-control" required pattern="09\d{9}" placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📍 موقعیت مکانی</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">استان <span class="text-danger">*</span></label>
                <select id="province-select" class="form-select" style="width:100%">
                    <option value="">-- انتخاب استان --</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">شهر <span class="text-danger">*</span></label>
                <select id="city-select" name="city_id" class="form-select" style="width:100%" required>
                    <option value="">-- ابتدا استان را انتخاب کنید --</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">آدرس دقیق <span class="text-danger">*</span></label>
                <input type="text" name="address" class="form-control" required value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">موقعیت روی نقشه <span class="text-danger">*</span></label>
                <div id="map"></div>
                <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
                <small class="text-muted">روی نقشه کلیک کنید تا موقعیت دقیق انتخاب شود.</small>
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📎 مدارک و تصاویر</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">تصویر پروانه کسب <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_license" accept="image/*" data-doc-type="license">
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر روی کارت ملی مدیر <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_owner_id_front" accept="image/*" data-doc-type="owner_id_front">
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر پشت کارت ملی مدیر <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_owner_id_back" accept="image/*" data-doc-type="owner_id_back">
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر سند یا اجاره‌نامه <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_deed" accept="image/*" data-doc-type="deed">
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar"></div></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="index.php" class="btn btn-outline-secondary">انصراف</a>
            <button type="submit" class="btn btn-primary px-4">📤 ثبت‌نام</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/notifications.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('input[name="_csrf"]')?.value || '';
    const uploadedDocs = {};

    // ─── ۱. نقشه (اول از همه) ───
    const map = L.map('map').setView([35.6892, 51.3890], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    let marker;
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    if (latInput.value && lngInput.value) {
        marker = L.marker([latInput.value, lngInput.value]).addTo(map);
        map.setView([latInput.value, lngInput.value], 14);
    }

    // ─── ۲. کلیک روی نقشه (Reverse Geocoding) ───
    // ─── Reverse Geocoding چندلایه ───
    function reverseGeocode(lat, lng, addressInput) {
        console.log('[Geocode] شروع جستجو برای:', lat, lng);
        if (typeof showToast === 'function') showToast('🔍 در حال یافتن آدرس...', 'info', 2000);

        // تلاش ۱: Photon (سریع‌تر، CORS-friendly)
        fetch(`https://photon.komoot.io/reverse?lon=${lng}&lat=${lat}&lang=fa`)
            .then(r => r.json())
            .then(data => {
                if (data && data.features && data.features.length > 0) {
                    const props = data.features[0].properties || {};
                    const parts = [
                        props.street || props.name,
                        props.housenumber ? 'پلاک ' + props.housenumber : null,
                        props.city || props.town || props.village,
                        props.state,
                        props.country
                    ].filter(Boolean);
                    
                    if (parts.length > 0) {
                        addressInput.value = parts.join('، ');
                        console.log('[Geocode] Photon موفق:', addressInput.value);
                        if (typeof showToast === 'function') showToast('📍 آدرس ثبت شد.', 'success', 3000);
                        return;
                    }
                }
                console.log('[Geocode] Photon نتیجه نداد، تلاش با Nominatim...');
                tryNominatim(lat, lng, addressInput);
            })
            .catch(err => {
                console.error('[Geocode] Photon خطا:', err);
                tryNominatim(lat, lng, addressInput);
            });
    }

    function tryNominatim(lat, lng, addressInput) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fa&zoom=18&addressdetails=1`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(data => {
                console.log('[Geocode] Nominatim:', data);
                let address = '';
                if (data.address) {
                    const a = data.address;
                    const parts = [
                        a.road || a.pedestrian || a.footway,
                        a.house_number ? 'پلاک ' + a.house_number : null,
                        a.neighbourhood || a.suburb,
                        a.city || a.town || a.village || a.county,
                        a.state,
                        a.country
                    ].filter(Boolean);
                    address = parts.join('، ');
                }
                if (!address && data.display_name) {
                    address = data.display_name;
                }
                
                if (address) {
                    addressInput.value = address;
                    console.log('[Geocode] Nominatim موفق:', address);
                    if (typeof showToast === 'function') showToast('📍 آدرس ثبت شد.', 'success', 3000);
                } else {
                    console.log('[Geocode] هیچ آدرسی یافت نشد');
                    if (typeof showToast === 'function') showToast('⚠️ آدرس یافت نشد. لطفاً دستی وارد کنید.', 'warning', 4000);
                }
            })
            .catch(err => {
                console.error('[Geocode] Nominatim خطا:', err);
                if (typeof showToast === 'function') showToast('⚠️ خطا در یافتن آدرس. لطفاً دستی وارد کنید.', 'danger', 4000);
            });
    }

    // ─── کلیک روی نقشه ───
    map.on('click', function(e) {
        if (marker) map.removeLayer(marker);
        marker = L.marker(e.latlng).addTo(map);
        latInput.value = e.latlng.lat;
        lngInput.value = e.latlng.lng;

        const addressInput = document.querySelector('input[name="address"]');
        if (!addressInput) {
            console.error('addressInput پیدا نشد!');
            return;
        }

        // ✅ شرط مهم: اگر کاربر آدرس را دستی پر کرده، خودکار پر نکن
        if (addressInput.value.trim() !== '') {
            console.log('[Geocode] کاربر آدرس را دستی وارد کرده، رد شد.');
            return;
        }

        reverseGeocode(e.latlng.lat, e.latlng.lng, addressInput);
    });

    // ─── ۳. استان و شهر (Select2 + Geocoding) ───
    fetch('/modules/properties/ajax/get-provinces.php')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('province-select');
            if (!d.success) return;
            d.provinces.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                sel.appendChild(opt);
            });
        });

    $('#province-select').select2({ placeholder: 'جستجوی استان...', allowClear: true, width: '100%' });

    $('#city-select').select2({
        placeholder: 'جستجوی شهر...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '/modules/properties/ajax/get-cities.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { province_id: $('#province-select').val(), q: params.term };
            },
            processResults: function(data) {
                if (!data.success) return { results: [] };
                return { results: data.cities.map(c => ({ id: c.id, text: c.name })) };
            }
        }
    }).on('change', function() {
        const cityName = $(this).select2('data')[0]?.text;
        if (!cityName) return;
        const provinceName = $('#province-select').select2('data')[0]?.text || '';
        const query = `${cityName}, ${provinceName}, Iran`;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&accept-language=fa`)
            .then(r => r.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    map.setView([lat, lng], 13);
                    if (marker) map.removeLayer(marker);
                    marker = L.marker([lat, lng]).addTo(map);
                    latInput.value = lat;
                    lngInput.value = lng;
                }
            })
            .catch(err => console.error('Search:', err));
    });

    // ─── ۴. آپلود مدارک ───
    document.querySelectorAll('.doc-upload').forEach(function(input) {
        input.addEventListener('change', async function() {
            if (!this.files[0]) return;
            const docType = this.dataset.docType;
            const progress = this.parentElement.querySelector('.progress');
            const bar = this.parentElement.querySelector('.progress-bar');
            progress.style.display = 'block';
            bar.style.width = '30%';

            const fd = new FormData();
            fd.append('file', this.files[0]);
            fd.append('doc_type', docType);
            fd.append('_csrf', csrf);

            try {
                const res = await fetch('/modules/properties/ajax/upload-agency-docs.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.success) {
                    uploadedDocs[docType] = result.url;
                    bar.style.width = '100%';
                    bar.classList.add('bg-success');
                    this.style.borderColor = '#28a745';
                    if (typeof showToast === 'function') showToast('✅ مدرک آپلود شد.', 'success', 2000);
                } else {
                    bar.classList.add('bg-danger');
                    if (typeof showToast === 'function') showToast('❌ ' + (result.message || 'خطا'), 'error', 5000);
                }
            } catch (e) {
                if (typeof showToast === 'function') showToast('❌ خطای شبکه: ' + e.message, 'error', 5000);
            }
        });
    });

    // ─── ۵. ارسال فرم ───
    document.getElementById('agencyForm').addEventListener('submit', function() {
        for (const [type, url] of Object.entries(uploadedDocs)) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'doc_' + type;
            hidden.value = url;
            this.appendChild(hidden);
        }
    });
});
</script>
<script>
    window.NESHAN_API_KEY = '<?= htmlspecialchars($neshanApiKey, ENT_QUOTES) ?>';
</script>
</body>
</html>

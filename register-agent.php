<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF
if (empty($_SESSION['agent_csrf'])) {
    $_SESSION['agent_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['agent_csrf'], $_POST['_csrf'] ?? '')) {
        $errors[] = 'توکن امنیتی نامعتبر است.';
    } else {
        // اعتبارسنجی
        $firstName     = trim((string)($_POST['first_name'] ?? ''));
        $lastName      = trim((string)($_POST['last_name'] ?? ''));
        $nationalCode  = trim((string)($_POST['national_code'] ?? ''));
        $phone         = trim((string)($_POST['phone'] ?? ''));
        $agencyName    = trim((string)($_POST['agency_name'] ?? ''));
        $agencyLicense = trim((string)($_POST['agency_license_no'] ?? ''));
        $agencyManager = trim((string)($_POST['agency_manager'] ?? ''));
        $docAvatar         = (string)($_POST['doc_avatar'] ?? '');
        $docNationalCardFront = (string)($_POST['doc_national_card_front'] ?? '');
        $docNationalCardBack  = (string)($_POST['doc_national_card_back'] ?? '');
        $docLicense        = (string)($_POST['doc_license'] ?? '');
        $docManagerPhoto   = (string)($_POST['doc_manager_photo'] ?? '');
        $docContract       = (string)($_POST['doc_contract'] ?? '');
        $docSocialSecurity = (string)($_POST['doc_social_security'] ?? '');
        $email         = trim((string)($_POST['email'] ?? ''));

        if (mb_strlen($firstName) < 2) $errors[] = 'نام را وارد کنید.';
        if (mb_strlen($lastName) < 2) $errors[] = 'نام خانوادگی را وارد کنید.';
        if (!preg_match('/^\d{10}$/', $nationalCode)) $errors[] = 'کد ملی باید ۱۰ رقم باشد.';
        if (!preg_match('/^09\d{9}$/', $phone)) $errors[] = 'شماره تماس باید 09xxxxxxxxx باشد.';
        if (mb_strlen($agencyName) < 3) $errors[] = 'نام بنگاه را وارد کنید.';
        if (mb_strlen($agencyLicense) < 3) $errors[] = 'شماره پروانه کسب را وارد کنید.';

        if (empty($errors)) {
            try {
                $pdo = getDB();

                // چک تکراری بودن کد ملی
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM agent_profiles WHERE national_code = ?");
                $stmt->execute([$nationalCode]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'کد ملی قبلاً ثبت شده است.';
                }

                // چک تکراری بودن نام کاربری (شماره تلفن به عنوان username)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$phone]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'شماره تلفن قبلاً ثبت شده است.';
                }
            } catch (Throwable $e) {
                $errors[] = 'خطا در بررسی تکراری: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                // ۱. پیدا کردن نقش «مشاور»
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'agent' LIMIT 1");
                $stmt->execute();
                $agentRoleId = (int) $stmt->fetchColumn();
                if ($agentRoleId <= 0) throw new RuntimeException('نقش «مشاور املاک» یافت نشد.');

                // ۲. ایجاد کاربر (با رمز تصادفی — مشاور باید بعداً رمز را تنظیم کند)
                $tempPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role_id, phone, national_code, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $phone,
                    $email ?: ($phone . '@agent.local'),
                    $tempPassword,
                    $agentRoleId,
                    $phone,
                    $nationalCode,
                ]);
                $userId = (int) $pdo->lastInsertId();

                // ۳. ایجاد agent_profiles (status=pending)
                $stmt = $pdo->prepare("
                    INSERT INTO agent_profiles (user_id, first_name, last_name, national_code, phone, agency_name, agency_license_no, agency_manager, avatar, national_card_front, national_card_back, agency_license_image, manager_photo, contract_image, social_security_image, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $userId, $firstName, $lastName, $nationalCode, $phone,
                    $agencyName, $agencyLicense, $agencyManager ?: null,
                    $docAvatar ?: null, $docNationalCardFront ?: null, $docNationalCardBack ?: null,
                    $docLicense ?: null, $docManagerPhoto ?: null,
                    $docContract ?: null, $docSocialSecurity ?: null,
                ]);

                $pdo->commit();

                $_SESSION['agent_csrf'] = null;
                $success = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'خطا در ثبت‌نام: ' . $e->getMessage();
            }
        }
    }
}

$csrf = $_SESSION['agent_csrf'] ?? '';
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام مشاور املاک | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .form-card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
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
    <h1 class="fw-bold mb-4">👤 ثبت‌نام مشاور املاک</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ ثبت‌نام شما با موفقیت ثبت شد.<br>
            پس از بررسی مدارک توسط مدیریت، حساب شما فعال می‌شود. برای پیگیری با پشتیبانی تماس بگیرید.
        </div>
    <?php elseif ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" class="form-card" id="agentForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <h5 class="border-bottom pb-2 mb-3">📋 اطلاعات شخصی</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">نام <span class="text-danger">*</span></label>
                <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">کد ملی <span class="text-danger">*</span></label>
                <input type="text" name="national_code" class="form-control" required pattern="\d{10}" maxlength="10" dir="ltr" value="<?= htmlspecialchars($_POST['national_code'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">شماره تلفن همراه <span class="text-danger">*</span></label>
                <input type="tel" name="phone" class="form-control" required pattern="09\d{9}" placeholder="09xxxxxxxxx" dir="ltr" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">🏢 اطلاعات بنگاه</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">نام بنگاه <span class="text-danger">*</span></label>
                <input type="text" name="agency_name" class="form-control" required value="<?= htmlspecialchars($_POST['agency_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">شماره پروانه کسب <span class="text-danger">*</span></label>
                <input type="text" name="agency_license_no" class="form-control" required value="<?= htmlspecialchars($_POST['agency_license_no'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">نام مدیر بنگاه</label>
                <input type="text" name="agency_manager" class="form-control" value="<?= htmlspecialchars($_POST['agency_manager'] ?? '') ?>">
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3">📎 مدارک و تصاویر</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">تصویر پرسنلی مشاور <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_avatar" accept="image/*" data-doc-type="avatar">
                <small class="text-muted">عکس ۳×۴ با پس‌زمینه روشن</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر روی کارت ملی <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_national_card_front" accept="image/*" data-doc-type="national_card_front">
                <small class="text-muted">روی کارت ملی (با عکس و مشخصات)</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر پشت کارت ملی <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_national_card_back" accept="image/*" data-doc-type="national_card_back">
                <small class="text-muted">پشت کارت ملی (با شماره سریال)</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر پروانه کسب <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_license" accept="image/*" data-doc-type="license">
                <small class="text-muted">پروانه کسب از اتحادیه املاک</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">تصویر مدیر بنگاه</label>
                <input type="file" class="form-control doc-upload" id="doc_manager_photo" accept="image/*" data-doc-type="manager_photo">
                <small class="text-muted">اختیاری</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">قرارداد مشاور با بنگاه <span class="text-danger">*</span></label>
                <input type="file" class="form-control doc-upload" id="doc_contract" accept="image/*" data-doc-type="contract">
                <small class="text-muted">ممهور به مهر بنگاه + امضای مدیر</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">برگه ارسال تامین اجتماعی</label>
                <input type="file" class="form-control doc-upload" id="doc_social_security" accept="image/*" data-doc-type="social_security">
                <small class="text-muted">اختیاری — ممهور به مهر بنگاه + امضای مدیر</small>
                <div class="progress mt-1" style="height:5px;display:none;"><div class="progress-bar" style="width:0%"></div></div>
            </div>
        </div>

        <div class="alert alert-info">
            📌 <strong>توجه:</strong> پس از ثبت‌نام، مدارک شما توسط مدیریت بررسی می‌شود. برای تکمیل فرآیند، لطفاً تا تأیید نهایی، با پشتیبانی در تماس باشید.
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="btn btn-outline-secondary">انصراف</a>
            <button type="submit" class="btn btn-primary px-4">📤 ثبت‌نام</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrf = document.querySelector('input[name="_csrf"]').value;
    const uploadedDocs = {};

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
                const res = await fetch('/modules/properties/ajax/upload-agent-docs.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.success) {
                    uploadedDocs[docType] = result.url;
                    bar.style.width = '100%';
                    bar.classList.add('bg-success');
                    this.style.borderColor = '#28a745';
                } else {
                    bar.classList.add('bg-danger');
                    showToast('خطا: ' + (result.message || 'نامشخص'), 'error', 8000);
                }
            } catch (e) {
                showToast('خطای شبکه: ' + e.message, 'error', 8000);
            }
        });
    });

    // قبل از submit، مدارک آپلودشده را به فیلدهای مخفی تبدیل کن
    document.getElementById('agentForm').addEventListener('submit', function(e) {
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
</body>
</html>

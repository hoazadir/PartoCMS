<?php
/**
 * 🆕 PartoCMS Smart Installer — Step 4: Site Type
 *
 * صفحه چهارم: انتخاب نوع سایت
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

session_start();

// بررسی انجام مرحله ۳
if (empty($_SESSION['installer_admin'])) {
    header('Location: admin-user.php');
    exit;
}

// ─── تعریف انواع سایت ───
$siteTypes = [
    'corporate' => [
        'icon'  => '🏢',
        'name'  => 'سازمانی / شرکتی',
        'desc'  => 'مناسب برای شرکت‌ها، سازمان‌ها و کسب‌وکارها',
        'tier'  => 'standard',
        'modules' => ['base', 'blog', 'contact'],
    ],
    'shop' => [
        'icon'  => '🛒',
        'name'  => 'فروشگاهی',
        'desc'  => 'فروشگاه آنلاین با درگاه پرداخت و سبد خرید',
        'tier'  => 'standard',
        'modules' => ['base', 'shop', 'blog'],
    ],
    'blog' => [
        'icon'  => '📝',
        'name'  => 'وبلاگ / خبری',
        'desc'  => 'وبلاگ شخصی یا سایت خبری',
        'tier'  => 'basic',
        'modules' => ['base', 'blog'],
    ],
    'real-estate' => [
        'icon'  => '🏠',
        'name'  => 'مشاور املاک',
        'desc'  => 'سیستم مدیریت املاک و مشاورین',
        'tier'  => 'pro',
        'modules' => ['base', 'real-estate', 'crm', 'blog'],
    ],
    'medical' => [
        'icon'  => '🏥',
        'name'  => 'پزشکی / دندانپزشکی',
        'desc'  => 'نوبت‌دهی آنلاین، پرونده بیمار، لیست پزشکان',
        'tier'  => 'pro',
        'modules' => ['base', 'medical', 'crm', 'blog'],
    ],
    'restaurant' => [
        'icon'  => '🍽️',
        'name'  => 'رستوران / کافه',
        'desc'  => 'منوی آنلاین، رزرو میز، سفارش',
        'tier'  => 'standard',
        'modules' => ['base', 'restaurant', 'shop'],
    ],
    'education' => [
        'icon'  => '🎓',
        'name'  => 'آموزشگاه',
        'desc'  => 'دوره‌ها، ثبت‌نام، پنل دانشجو',
        'tier'  => 'pro',
        'modules' => ['base', 'education', 'crm'],
    ],
    'personal' => [
        'icon'  => '👤',
        'name'  => 'شخصی / رزومه',
        'desc'  => 'سایت شخصی یا رزومه آنلاین',
        'tier'  => 'basic',
        'modules' => ['base'],
    ],
    'advertising' => [
        'icon'  => '📢',
        'name'  => 'آژانس تبلیغاتی',
        'desc'  => 'مدیریت کمپین‌ها، مشتریان، پروژه‌های تبلیغاتی',
        'tier'  => 'pro',
        'modules' => ['base', 'ads', 'crm', 'blog'],
    ],
    'car-dealer' => [
        'icon'  => '🚗',
        'name'  => 'نمایشگاه خودرو',
        'desc'  => 'لیست خودروها، فیلتر پیشرفته، رزرو تست درایو',
        'tier'  => 'pro',
        'modules' => ['base', 'car-dealer', 'crm', 'blog'],
    ],
    'furniture' => [
        'icon'  => '🛋️',
        'name'  => 'نمایشگاه مبلمان',
        'desc'  => 'کاتالوگ محصولات، گالری، استعلام قیمت',
        'tier'  => 'standard',
        'modules' => ['base', 'furniture', 'shop'],
    ],
    'advertising' => [
        'icon'  => '📢',
        'name'  => 'آژانس تبلیغاتی',
        'desc'  => 'مدیریت کمپین‌ها، مشتریان، پروژه‌های تبلیغاتی',
        'tier'  => 'pro',
        'modules' => ['base', 'ads', 'crm', 'blog'],
    ],
    'car-dealer' => [
        'icon'  => '🚗',
        'name'  => 'نمایشگاه خودرو',
        'desc'  => 'لیست خودروها، فیلتر پیشرفته، رزرو تست درایو',
        'tier'  => 'pro',
        'modules' => ['base', 'car-dealer', 'crm', 'blog'],
    ],
    'furniture' => [
        'icon'  => '🛋️',
        'name'  => 'نمایشگاه مبلمان',
        'desc'  => 'کاتالوگ محصولات، گالری، استعلام قیمت',
        'tier'  => 'standard',
        'modules' => ['base', 'furniture', 'shop'],
    ],
    'custom' => [
        'icon'  => '🔧',
        'name'  => 'سفارشی',
        'desc'  => 'خودتان ماژول‌ها را انتخاب کنید',
        'tier'  => 'custom',
        'modules' => ['base'],
    ],
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteType = $_POST['site_type'] ?? '';
    $tier     = $_POST['tier'] ?? '';

    if (!isset($siteTypes[$siteType])) {
        $errors[] = 'نوع سایت نامعتبر است';
    }

    if (empty($errors)) {
        $_SESSION['installer_site'] = [
            'type'      => $siteType,
            'tier'      => $tier ?: $siteTypes[$siteType]['tier'],
            'modules'   => $siteTypes[$siteType]['modules'],
        ];

        header('Location: install.php');
        exit;
    }
}

$currentType = $_SESSION['installer_site']['type'] ?? 'corporate';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب PartoCMS — مرحله ۴: نوع سایت</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: Tahoma, 'Vazirmatn', sans-serif;
        padding: 20px;
    }
    .installer-container { max-width: 1000px; margin: 0 auto; }
    .installer-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    .installer-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: #fff; padding: 30px; text-align: center;
    }
    .installer-header h1 { margin: 0 0 10px; font-size: 26px; font-weight: bold; }
    .installer-header p { margin: 0; opacity: 0.9; font-size: 14px; }

    .stepper {
        display: flex; justify-content: space-between;
        padding: 20px 30px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0; overflow-x: auto;
    }
    .step { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 80px; opacity: 0.5; }
    .step.completed { opacity: 1; }
    .step.active { opacity: 1; }
    .step .circle {
        width: 36px; height: 36px; border-radius: 50%;
        background: #cbd5e1; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; margin-bottom: 8px; font-size: 14px;
    }
    .step.completed .circle { background: #10b981; }
    .step.active .circle { background: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
    .step .label { font-size: 11px; color: #64748b; font-weight: bold; text-align: center; }
    .step.active .label { color: #3b82f6; }

    .installer-body { padding: 30px; }

    .alert-error {
        background: #fee2e2; border-right: 4px solid #dc2626;
        color: #991b1b; padding: 12px 18px; border-radius: 8px;
        margin-bottom: 20px; font-size: 13.5px;
    }

    .types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .type-card {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }

    .type-card:hover {
        border-color: #3b82f6;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.15);
    }

    .type-card.selected {
        border-color: #3b82f6;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .type-card .type-icon { font-size: 42px; display: block; margin-bottom: 10px; }
    .type-card .type-name { font-weight: bold; color: #1e293b; font-size: 15px; margin-bottom: 5px; }
    .type-card .type-desc { font-size: 11.5px; color: #64748b; line-height: 1.6; min-height: 36px; }
    .type-card .tier-badge {
        display: inline-block; margin-top: 10px;
        padding: 3px 10px; border-radius: 12px;
        font-size: 10px; font-weight: bold;
    }
    .tier-basic    { background: #e0e7ff; color: #3730a3; }
    .tier-standard { background: #dcfce7; color: #166534; }
    .tier-pro      { background: #fef3c7; color: #92400e; }
    .tier-custom   { background: #f1f5f9; color: #475569; }

    .type-card input[type="radio"] { display: none; }

    .actions {
        padding: 20px 30px; background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff; border: none; padding: 12px 30px;
        border-radius: 8px; font-weight: bold; font-size: 14px;
        text-decoration: none; display: inline-flex;
        align-items: center; gap: 8px; transition: all 0.2s;
        cursor: pointer; font-family: inherit;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59,130,246,0.4); color: #fff;
    }
    .btn-primary-custom.disabled {
        background: #cbd5e1; cursor: not-allowed; pointer-events: none;
    }
    .btn-secondary-custom {
        background: #e2e8f0; color: #334155;
        padding: 12px 24px; border-radius: 8px;
        text-decoration: none; font-weight: bold; font-size: 14px;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-secondary-custom:hover { background: #cbd5e1; color: #1e293b; }

    .brand-footer { text-align: center; margin-top: 20px; color: rgba(255,255,255,0.8); font-size: 12px; }

    @media (max-width: 700px) {
        .installer-header h1 { font-size: 20px; }
        .installer-body { padding: 20px; }
        .types-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
</head>
<body>

<div class="installer-container">
    <div class="installer-card">

        <div class="installer-header">
            <h1>🚀 نصب PartoCMS</h1>
            <p>مرحله ۴ از ۵: نوع سایت</p>
        </div>

        <div class="stepper">
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">پیش‌نیازها</div>
            </div>
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">دیتابیس</div>
            </div>
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">ادمین</div>
            </div>
            <div class="step active">
                <div class="circle">۴</div>
                <div class="label">نوع سایت</div>
            </div>
            <div class="step">
                <div class="circle">۵</div>
                <div class="label">نصب</div>
            </div>
        </div>

        <div class="installer-body">

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    ❌ <?= htmlspecialchars($errors[0]) ?>
                </div>
            <?php endif; ?>

            <p style="color:#64748b;font-size:13.5px;margin-bottom:20px;text-align:center;">
                لطفاً نوع سایت خود را انتخاب کنید تا ماژول‌های مناسب به‌صورت خودکار نصب شوند.
            </p>

            <form method="post" id="siteTypeForm">
                <div class="types-grid">
                    <?php foreach ($siteTypes as $slug => $type): ?>
                        <label class="type-card <?= $currentType === $slug ? 'selected' : '' ?>"
                               data-slug="<?= htmlspecialchars($slug) ?>"
                               data-tier="<?= htmlspecialchars($type['tier']) ?>">
                            <input type="radio" name="site_type" value="<?= htmlspecialchars($slug) ?>"
                                   <?= $currentType === $slug ? 'checked' : '' ?>>
                            <span class="type-icon"><?= $type['icon'] ?></span>
                            <div class="type-name"><?= htmlspecialchars($type['name']) ?></div>
                            <div class="type-desc"><?= htmlspecialchars($type['desc']) ?></div>
                            <span class="tier-badge tier-<?= htmlspecialchars($type['tier']) ?>">
                                <?= strtoupper($type['tier']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="tier" id="tierInput" value="<?= htmlspecialchars($siteTypes[$currentType]['tier']) ?>">
            </form>

        </div>

        <div class="actions">
            <a href="admin-user.php" class="btn-secondary-custom">
                <i class="bi bi-arrow-right"></i> مرحله قبل
            </a>

            <button type="submit" form="siteTypeForm" class="btn-primary-custom">
                <i class="bi bi-check-circle"></i> شروع نصب
            </button>
        </div>

    </div>

    <div class="brand-footer">
        PartoCMS Smart Installer v1.0 — © ۲۰۲۶
    </div>
</div>

<script>
document.querySelectorAll('.type-card').forEach(card => {
    card.addEventListener('click', function() {
        // حذف selected از همه
        document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));

        // افزودن به این کارت
        this.classList.add('selected');

        // فعال کردن radio
        this.querySelector('input[type="radio"]').checked = true;

        // ذخیره tier
        document.getElementById('tierInput').value = this.dataset.tier;
    });
});
</script>

</body>
</html>

<?php
/**
 * 🆕 PartoCMS Smart Installer — Step 1: Requirements
 *
 * صفحه اول اینستالر: بررسی پیش‌نیازهای نصب
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

// بررسی نصب قبلی
$configPath = dirname(__DIR__) . '/config.php';
$isInstalled = false;
if (file_exists($configPath)) {
    // اگر config.php وجود دارد و نصب کامل است → رد
    $content = file_get_contents($configPath);
    if (strpos($content, 'YOUR_DB_NAME_HERE') === false) {
        // config پر شده — شاید نصب شده
        // ولی اجازه می‌دهیم کاربر دوباره نصب کند (با هشدار)
    }
}

// بارگذاری config اگر هست (برای DB check)
if (file_exists($configPath)) {
    @require_once $configPath;
}

require_once __DIR__ . '/includes/RequirementChecker.php';

// اجرای بررسی
$checker = new RequirementChecker();
$results = $checker->checkAll();
$summary = $checker->getSummary();
$allPassed = $checker->allPassed();

// دسته‌بندی برای UI
$categories = [
    'environment' => ['icon' => '🌍', 'title' => 'محیط اجرا'],
    'php_version' => ['icon' => '🐘', 'title' => 'نسخه PHP'],
    'extensions'  => ['icon' => '🧩', 'title' => 'افزونه‌های PHP'],
    'permissions' => ['icon' => '🔐', 'title' => 'دسترسی‌ها'],
    'functions'   => ['icon' => '⚙️', 'title' => 'توابع PHP'],
    'database'    => ['icon' => '🗄️', 'title' => 'دیتابیس'],
    'disk_space'  => ['icon' => '💾', 'title' => 'فضای دیسک'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب PartoCMS — مرحله ۱: پیش‌نیازها</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: Tahoma, 'Vazirmatn', sans-serif;
        padding: 20px;
    }
    .installer-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .installer-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    .installer-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: #fff;
        padding: 30px;
        text-align: center;
    }
    .installer-header h1 {
        margin: 0 0 10px;
        font-size: 26px;
        font-weight: bold;
    }
    .installer-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }
    .stepper {
        display: flex;
        justify-content: space-between;
        padding: 20px 30px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        min-width: 80px;
        position: relative;
        opacity: 0.5;
    }
    .step.active { opacity: 1; }
    .step.completed { opacity: 1; }
    .step .circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #cbd5e1;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 14px;
    }
    .step.active .circle {
        background: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
    }
    .step.completed .circle { background: #10b981; }
    .step .label {
        font-size: 11px;
        color: #64748b;
        font-weight: bold;
        text-align: center;
    }
    .step.active .label { color: #3b82f6; }
    .installer-body { padding: 30px; }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 25px;
    }
    .summary-item {
        padding: 16px;
        border-radius: 10px;
        text-align: center;
    }
    .summary-item .num {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 4px;
    }
    .summary-item .lbl {
        font-size: 11px;
        opacity: 0.8;
    }
    .summary-item.ok   { background: #dcfce7; color: #166534; }
    .summary-item.warn { background: #fef3c7; color: #92400e; }
    .summary-item.fail { background: #fee2e2; color: #991b1b; }
    .summary-item.total { background: #dbeafe; color: #1e40af; }

    .category {
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .category-header {
        background: #f1f5f9;
        padding: 12px 18px;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 14px;
    }
    .category-header .cat-icon { font-size: 20px; }
    .category-header .cat-count {
        margin-right: auto;
        font-size: 12px;
        color: #64748b;
        font-weight: normal;
    }
    .check-item {
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .check-item:last-child { border-bottom: 0; }
    .check-item .icon { font-size: 16px; }
    .check-item .msg { flex: 1; }
    .check-item.ok   { background: #fff; }
    .check-item.warn { background: #fffbeb; }
    .check-item.fail { background: #fef2f2; }

    .actions {
        padding: 20px 30px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59,130,246,0.4);
        color: #fff;
    }
    .btn-primary-custom.disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        pointer-events: none;
    }
    .alert-warning {
        background: #fef3c7;
        border-right: 4px solid #f59e0b;
        color: #92400e;
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13.5px;
    }
    .brand-footer {
        text-align: center;
        margin-top: 20px;
        color: rgba(255,255,255,0.8);
        font-size: 12px;
    }
    @media (max-width: 700px) {
        .summary-grid { grid-template-columns: repeat(2, 1fr); }
        .installer-header h1 { font-size: 20px; }
        .stepper { padding: 15px; }
        .installer-body { padding: 20px; }
    }
</style>
</head>
<body>

<div class="installer-container">
    <div class="installer-card">

        <!-- Header -->
        <div class="installer-header">
            <h1>🚀 نصب PartoCMS</h1>
            <p>سیستم مدیریت محتوای هوشمند — نسخه ۱.۰</p>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <div class="step active">
                <div class="circle">۱</div>
                <div class="label">پیش‌نیازها</div>
            </div>
            <div class="step">
                <div class="circle">۲</div>
                <div class="label">دیتابیس</div>
            </div>
            <div class="step">
                <div class="circle">۳</div>
                <div class="label">ادمین</div>
            </div>
            <div class="step">
                <div class="circle">۴</div>
                <div class="label">نوع سایت</div>
            </div>
            <div class="step">
                <div class="circle">۵</div>
                <div class="label">نصب</div>
            </div>
        </div>

        <!-- Body -->
        <div class="installer-body">

            <!-- هشدار کلی -->
            <?php if ($allPassed): ?>
                <div class="alert alert-success" style="background:#dcfce7;border-right:4px solid #16a34a;color:#166534;padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:13.5px;">
                    ✅ <strong>همه پیش‌نیازها آماده است!</strong> می‌توانید به مرحله بعد بروید.
                </div>
            <?php else: ?>
                <div class="alert-warning">
                    ⚠️ <strong>برخی پیش‌نیازها مشکل دارند.</strong>
                    لطفاً موارد قرمز را برطرف کنید، سپس صفحه را رفرش کنید.
                </div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="summary-grid">
                <div class="summary-item total">
                    <div class="num"><?= $summary['total'] ?></div>
                    <div class="lbl">مجموع بررسی‌ها</div>
                </div>
                <div class="summary-item ok">
                    <div class="num"><?= $summary['ok'] ?></div>
                    <div class="lbl">موفق ✅</div>
                </div>
                <div class="summary-item warn">
                    <div class="num"><?= $summary['warn'] ?></div>
                    <div class="lbl">هشدار ⚠️</div>
                </div>
                <div class="summary-item fail">
                    <div class="num"><?= $summary['fail'] ?></div>
                    <div class="lbl">خطا ❌</div>
                </div>
            </div>

            <!-- Categories -->
            <?php foreach ($categories as $key => $cat): ?>
                <?php if (empty($results[$key])) continue; ?>
                <?php
                $items = $results[$key];
                $catOK = 0; $catWarn = 0; $catFail = 0;
                foreach ($items as $it) {
                    if ($it['status'] === 'ok') $catOK++;
                    elseif ($it['status'] === 'warn') $catWarn++;
                    elseif ($it['status'] === 'fail') $catFail++;
                }
                ?>
                <div class="category">
                    <div class="category-header">
                        <span class="cat-icon"><?= $cat['icon'] ?></span>
                        <span><?= htmlspecialchars($cat['title']) ?></span>
                        <span class="cat-count">
                            <?php if ($catFail > 0): ?>
                                <span style="color:#dc2626;">❌ <?= $catFail ?></span>
                            <?php endif; ?>
                            <?php if ($catWarn > 0): ?>
                                <span style="color:#d97706;">⚠️ <?= $catWarn ?></span>
                            <?php endif; ?>
                            <?php if ($catOK > 0): ?>
                                <span style="color:#16a34a;">✅ <?= $catOK ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php foreach ($items as $item): ?>
                        <div class="check-item <?= htmlspecialchars($item['status']) ?>">
                            <span class="icon">
                                <?php
                                echo match($item['status']) {
                                    'ok'   => '✅',
                                    'warn' => '⚠️',
                                    'fail' => '❌',
                                    default => 'ℹ️',
                                };
                                ?>
                            </span>
                            <span class="msg"><?= htmlspecialchars($item['message']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="?refresh=1" class="btn-secondary" style="background:#e2e8f0;color:#334155;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px;">
                🔄 بررسی مجدد
            </a>

            <?php if ($allPassed): ?>
                <a href="database.php" class="btn-primary-custom">
                    مرحله بعد: دیتابیس →
                </a>
            <?php else: ?>
                <span class="btn-primary-custom disabled">
                    ابتدا خطاها را برطرف کنید
                </span>
            <?php endif; ?>
        </div>

    </div>

    <div class="brand-footer">
        PartoCMS Smart Installer v1.0 — © ۲۰۲۶
    </div>
</div>

</body>
</html>

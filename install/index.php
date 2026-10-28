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



// رندر با قالب مشترک
require_once __DIR__ . '/views/layout.php';

ob_start();
include __DIR__ . '/views/step1.php';
$content = ob_get_clean();

render_installer_layout(1, 'پیش‌نیازها', $content);

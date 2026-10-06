<?php
/**
 * 🆕 PartoCMS Smart Installer — Step 5: Install
 *
 * مرحله پنجم: نصب واقعی
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

session_start();

// ─── بررسی مراحل قبل ───
if (empty($_SESSION['installer_db'])) {
    header('Location: database.php');
    exit;
}
if (empty($_SESSION['installer_admin'])) {
    header('Location: admin-user.php');
    exit;
}
if (empty($_SESSION['installer_site'])) {
    header('Location: site-type.php');
    exit;
}

$db    = $_SESSION['installer_db'];
$admin = $_SESSION['installer_admin'];
$site  = $_SESSION['installer_site'];

// ─── بررسی نصب قبلی ───
$rootPath  = dirname(__DIR__);
$lockFile  = $rootPath . '/install.lock';
$alreadyInstalled = file_exists($lockFile);

// ─── وضعیت ───
$errors  = [];
$success = false;
$report  = [];


// ═══════════════════════════════════════════════════════════
// اجرای نصب
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {

    try {
        // ─── ۱. اتصال به MySQL ───
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
        $report[] = ['step' => 'connect', 'ok' => true, 'msg' => 'اتصال به MySQL موفق'];

        // ─── ۲. ساخت دیتابیس ───
        $dbName = str_replace('`', '', $db['name']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        $report[] = ['step' => 'create_db', 'ok' => true, 'msg' => "دیتابیس «{$dbName}» آماده شد"];

        // ─── ۳. اجرای فایل‌های SQL ───
        $sqlFiles = [
            ['file' => 'database/schema.sql',            'title' => 'جدول‌های اصلی'],
            ['file' => 'database/site-builder.sql',      'title' => 'سیستم نقش‌ها'],
            ['file' => 'database/categories-media.sql',  'title' => 'دسته‌ها و رسانه'],
            ['file' => 'modules/ads/database/schema.sql',  'title' => 'ماژول تبلیغات'],
            ['file' => 'modules/shop/database/schema.sql', 'title' => 'ماژول فروشگاه'],
        ];

        foreach ($sqlFiles as $item) {
            $file = $rootPath . '/' . $item['file'];
            if (!file_exists($file)) {
                $report[] = ['step' => $item['file'], 'ok' => false, 'msg' => "فایل پیدا نشد: {$item['file']}"];
                continue;
            }
            $result = runSqlFile($pdo, $file, $dbName);
            if (!empty($result['errors'])) {
                $report[] = [
                    'step' => $item['file'], 'ok' => false,
                    'msg'  => $item['title'] . " — " . $result['ok'] . "/" . $result['total'],
                    'errors' => $result['errors'],
                ];
            } else {
                $report[] = [
                    'step' => $item['file'], 'ok' => true,
                    'msg'  => $item['title'] . " — {$result['ok']} دستور اجرا شد",
                ];
            }
        }


        // ─── ۴. ساخت کاربر ادمین ───
        $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, is_active)
            VALUES (?, ?, ?, 'admin', 1)
            ON DUPLICATE KEY UPDATE
                password = VALUES(password),
                email = VALUES(email),
                role = 'admin',
                is_active = 1
        ");
        $stmt->execute([$admin['username'], $admin['email'], $hash]);
        $report[] = ['step' => 'admin', 'ok' => true, 'msg' => "کاربر ادمین «{$admin['username']}» ساخته شد"];

        // ─── ۵. درج تنظیمات ───
        $settings = [
            'site_name'        => $admin['site_name'],
            'admin_email'      => $admin['email'],
            'site_description' => 'ساخته شده با PartoCMS',
            'site_type'        => $site['type'],
            'site_tier'        => $site['tier'],
            'theme_color'      => '#3b97e3',
        ];
        $stmtSettings = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($settings as $key => $value) {
            $stmtSettings->execute([$key, $value]);
        }
        $report[] = ['step' => 'settings', 'ok' => true, 'msg' => "تنظیمات سایت درج شد"];

        // ─── ۶. فعال کردن ماژول‌ها ───
        try {
            $modulesToEnable = $site['modules'] ?? [];
            if (!empty($modulesToEnable)) {
                $placeholders = implode(',', array_fill(0, count($modulesToEnable), '?'));
                $stmtMod = $pdo->prepare("UPDATE modules SET is_enabled = 1 WHERE slug IN ({$placeholders})");
                $stmtMod->execute($modulesToEnable);
                $report[] = ['step' => 'modules', 'ok' => true, 'msg' => count($modulesToEnable) . " ماژول فعال شد"];
            }
        } catch (Throwable $e) {
            $report[] = ['step' => 'modules', 'ok' => false, 'msg' => "فعال‌سازی ماژول‌ها: " . $e->getMessage()];
        }

        // ─── ۷. ساخت config.php ───
        $exampleFile = $rootPath . '/config.example.php';
        $configFile  = $rootPath . '/config.php';

        if (!file_exists($exampleFile)) {
            throw new Exception('config.example.php پیدا نشد');
        }

        $config = file_get_contents($exampleFile);
        $config = str_replace(
            ['YOUR_DB_NAME_HERE', 'YOUR_DB_USER_HERE', 'YOUR_DB_PASSWORD_HERE'],
            [$db['name'], $db['user'], addcslashes($db['pass'], "'\\")],
            $config
        );
        $config = preg_replace(
            "/define\('DB_HOST',\s*'[^']*'\);/",
            "define('DB_HOST', '" . addcslashes($db['host'], "'\\") . "');",
            $config
        );
        $config = preg_replace(
            "/define\('DB_CHARSET',\s*'[^']*'\);/",
            "define('DB_CHARSET', '" . addcslashes($db['charset'], "'\\") . "');",
            $config
        );

        if (file_exists($configFile)) {
            @copy($configFile, $configFile . '.bak-' . date('YmdHis'));
        }

        if (@file_put_contents($configFile, $config) === false) {
            throw new Exception('نوشتن config.php ناموفق بود');
        }
        $report[] = ['step' => 'config', 'ok' => true, 'msg' => 'config.php ساخته شد'];

        // ─── ۸. install.lock ───
        @file_put_contents($lockFile, date('Y-m-d H:i:s') . PHP_EOL);
        $report[] = ['step' => 'lock', 'ok' => true, 'msg' => 'قفل نصب ایجاد شد'];

        $success = true;

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}


// ═══════════════════════════════════════════════════════════
// Helper: اجرای فایل SQL
// ═══════════════════════════════════════════════════════════
function runSqlFile(PDO $pdo, string $file, string $dbName): array
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        return ['total' => 0, 'ok' => 0, 'errors' => ['خواندن فایل ناموفق']];
    }

    $sql = preg_replace('/`?grapesjs_cms`?/i', '`' . $dbName . '`', $sql);
    $sql = preg_replace('/^\s*CREATE\s+DATABASE[^;]*;\s*$/im', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[^;]*;\s*$/im', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*#.*$/m', '', $sql);

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    $result = ['total' => 0, 'ok' => 0, 'errors' => []];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || strlen($stmt) < 3) continue;

        $result['total']++;
        try {
            $pdo->exec($stmt);
            $result['ok']++;
        } catch (Throwable $e) {
            $preview = substr(preg_replace('/\s+/', ' ', $stmt), 0, 60);
            $result['errors'][] = $preview . ' — ' . $e->getMessage();
        }
    }
    return $result;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب PartoCMS — مرحله ۵</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<style>
body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: Tahoma, sans-serif; padding: 20px; }
.card-w { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
.hdr { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #fff; padding: 30px; text-align: center; }
.hdr h1 { margin: 0 0 10px; font-size: 26px; font-weight: bold; }
.hdr p { margin: 0; opacity: 0.9; font-size: 14px; }
.stepper { display: flex; justify-content: space-between; padding: 20px 30px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.step { display: flex; flex-direction: column; align-items: center; flex: 1; opacity: 0.5; }
.step.completed, .step.active { opacity: 1; }
.step .circle { width: 36px; height: 36px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-bottom: 8px; }
.step.active .circle { background: #3b82f6; }
.step .label { font-size: 11px; color: #64748b; font-weight: bold; text-align: center; }
.body-w { padding: 30px; }
.alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 13.5px; }
.alert-error { background: #fee2e2; border-right: 4px solid #dc2626; color: #991b1b; }
.alert-success { background: #dcfce7; border-right: 4px solid #16a34a; color: #166534; }
.alert-warning { background: #fef3c7; border-right: 4px solid #f59e0b; color: #92400e; }
.alert-info { background: #dbeafe; border-right: 4px solid #3b82f6; color: #1e40af; }
.summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.srow { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 13px; }
.srow:last-child { border-bottom: none; }
.srow .l { color: #64748b; }
.srow .v { font-weight: bold; font-family: monospace; }
.cred { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.cred h3 { margin: 0 0 15px; color: #92400e; font-size: 15px; }
.cred .cr { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
.cred .cr .l { color: #78350f; }
.cred .cr .v { font-family: monospace; font-weight: bold; color: #92400e; direction: ltr; }
.report-item { display: flex; gap: 10px; padding: 10px 14px; margin-bottom: 8px; background: #f8fafc; border-radius: 8px; font-size: 13px; border-right: 3px solid #cbd5e1; }
.report-item.ok { border-right-color: #10b981; background: #f0fdf4; }
.report-item.fail { border-right-color: #dc2626; background: #fef2f2; }
.report-item .msg { flex: 1; }
.actions { padding: 20px 30px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
.btn { border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-family: inherit; }
.btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
.btn-success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 14px 40px; font-size: 15px; }
.btn-secondary { background: #e2e8f0; color: #334155; padding: 12px 24px; }
</style>
</head>
<body>
<div class="card-w">
    <div class="hdr">
        <h1>🚀 نصب PartoCMS</h1>
        <p>مرحله ۵ از ۵: نصب نهایی</p>
    </div>

    <div class="stepper">
        <div class="step completed"><div class="circle">✓</div><div class="label">پیش‌نیازها</div></div>
        <div class="step completed"><div class="circle">✓</div><div class="label">دیتابیس</div></div>
        <div class="step completed"><div class="circle">✓</div><div class="label">ادمین</div></div>
        <div class="step completed"><div class="circle">✓</div><div class="label">نوع سایت</div></div>
        <div class="step active"><div class="circle">۵</div><div class="label">نصب</div></div>
    </div>

    <div class="body-w">

        <?php if ($alreadyInstalled && !$success): ?>
            <div class="alert alert-warning">
                <strong>⚠️ PartoCMS قبلاً نصب شده است!</strong>
                فایل <code>install.lock</code> وجود دارد.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>❌ خطا:</strong>
                <ul style="margin: 5px 0 0; padding-right: 20px;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>🎉 نصب با موفقیت انجام شد!</strong>
            </div>

            <div class="cred">
                <h3>🔑 اطلاعات ورود ادمین</h3>
                <div class="cr"><span class="l">نام کاربری:</span><span class="v"><?= htmlspecialchars($admin['username']) ?></span></div>
                <div class="cr"><span class="l">ایمیل:</span><span class="v"><?= htmlspecialchars($admin['email']) ?></span></div>
                <div class="cr"><span class="l">رمز عبور:</span><span class="v"><?= htmlspecialchars($admin['password']) ?></span></div>
            </div>

            <div class="alert alert-warning">
                <strong>🛡️ مرحله امنیتی مهم:</strong>
                لطفاً پوشه <code>install/</code> را از سرور حذف کنید.
            </div>

            <h3 style="font-size: 15px; margin: 20px 0 15px;">📋 گزارش نصب</h3>
            <?php foreach ($report as $item): ?>
                <div class="report-item <?= $item['ok'] ? 'ok' : 'fail' ?>">
                    <span><?= $item['ok'] ? '✅' : '❌' ?></span>
                    <div class="msg"><?= htmlspecialchars($item['msg']) ?></div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="summary">
                <div class="srow"><span class="l">دیتابیس:</span><span class="v"><?= htmlspecialchars($db['name']) ?></span></div>
                <div class="srow"><span class="l">نام سایت:</span><span class="v"><?= htmlspecialchars($admin['site_name']) ?></span></div>
                <div class="srow"><span class="l">ادمین:</span><span class="v"><?= htmlspecialchars($admin['username']) ?></span></div>
                <div class="srow"><span class="l">نوع سایت:</span><span class="v"><?= htmlspecialchars($site['type']) ?> / <?= htmlspecialchars($site['tier']) ?></span></div>
            </div>

            <div class="alert alert-info">
                💡 این عملیات ممکن است ۵ تا ۳۰ ثانیه طول بکشد.
            </div>
        <?php endif; ?>

    </div>

    <div class="actions">
        <?php if ($success): ?>
            <a href="../admin/login.php" class="btn btn-success">🔓 ورود به پنل</a>
            <a href="../index.php" class="btn btn-secondary" target="_blank">🌐 مشاهده سایت</a>
        <?php else: ?>
            <a href="site-type.php" class="btn btn-secondary">مرحله قبل</a>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="install">
                <button type="submit" class="btn btn-success">🚀 شروع نصب</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

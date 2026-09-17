<?php
/**
 * PartoCMS - Cron Setup Wizard
 * راه‌اندازی cron در محیط‌های مختلف
 *
 * @version 1.0
 * @date 2026-09-17
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/queue_manager.php';
require_once __DIR__ . '/includes/environment.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$message = '';
$messageType = '';

$env = Environment::getInfo();
$queue = new QueueManager($pdo);

// ============================================================
//   عملیات
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- تست اجرای cron ----------
    if ($action === 'test_run') {
        try {
            $script = realpath(__DIR__ . '/cron_translation_queue.php');
            if (!$script) throw new Exception('cron script not found');

            $php = PHP_BINARY ?: 'php';
            $cmd = sprintf(
                '%s %s 2 30 2>&1',
                escapeshellcmd($php),
                escapeshellarg($script)
            );

            $output = [];
            $code = 0;
            @exec($cmd, $output, $code);

            $message = '<strong>نتیجه تست:</strong><br>';
            $message .= '<pre style="background:#1e293b;color:#10b981;padding:12px;border-radius:8px;font-size:11px;direction:ltr;text-align:left;max-height:200px;overflow:auto">';
            $message .= htmlspecialchars(implode("\n", $output));
            $message .= '</pre>';
            $message .= "کد خروج: <strong>$code</strong>";
            $messageType = $code === 0 ? 'success' : 'danger';
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // ---------- نصب خودکار crontab (فقط VPS/Linux) ----------
    if ($action === 'install_crontab') {
        try {
            // چک وجود crontab
            $crontabPath = trim((string)@shell_exec('which crontab 2>/dev/null'));
            if (empty($crontabPath)) {
                throw new Exception('crontab در سیستم نصب نیست');
            }

            $script = realpath(__DIR__ . '/cron_translation_queue.php');
            $php = PHP_BINARY ?: 'php';
            $logFile = __DIR__ . '/../logs/cron_stdout.log';

            // خط cron
            $cronLine = "*/2 * * * * " . escapeshellcmd($php) . " " . escapeshellarg($script) . " 3 120 >> " . escapeshellarg($logFile) . " 2>&1";

            // گرفتن crontab فعلی
            $current = @shell_exec('crontab -l 2>/dev/null') ?: '';

            // حذف خط قدیمی اگه هست
            $lines = explode("\n", $current);
            $lines = array_filter($lines, function($l) {
                return strpos($l, 'cron_translation_queue.php') === false;
            });

            // اضافه کردن خط جدید
            $lines[] = "# PartoCMS Translation Queue";
            $lines[] = $cronLine;

            $newCron = implode("\n", $lines) . "\n";

            // ذخیره در فایل موقت و نصب
            $tmp = __DIR__ . '/../logs/cron_tmp.txt';
            file_put_contents($tmp, $newCron);
            @exec('crontab ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            @unlink($tmp);

            if ($code !== 0) {
                throw new Exception('crontab install failed: ' . implode("\n", $out));
            }

            $message = "✅ Cron با موفقیت نصب شد!<br><br>";
            $message .= "<strong>خط جدید:</strong><br>";
            $message .= "<code style='background:#f1f5f9;padding:5px 10px;display:inline-block;border-radius:5px;direction:ltr'>" . htmlspecialchars($cronLine) . "</code>";
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // ---------- حذف crontab ----------
    if ($action === 'remove_crontab') {
        try {
            $current = @shell_exec('crontab -l 2>/dev/null') ?: '';
            $lines = explode("\n", $current);
            $lines = array_filter($lines, function($l) {
                return strpos($l, 'cron_translation_queue.php') === false
                    && strpos($l, 'PartoCMS Translation Queue') === false;
            });

            $tmp = __DIR__ . '/../logs/cron_tmp.txt';
            file_put_contents($tmp, implode("\n", $lines) . "\n");
            @exec('crontab ' . escapeshellarg($tmp) . ' 2>&1');
            @unlink($tmp);

            $message = "✅ Cron حذف شد";
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ============================================================
//   بررسی وضعیت crontab
// ============================================================
$crontabInstalled = false;
$currentCron = '';
try {
    $currentCron = @shell_exec('crontab -l 2>/dev/null') ?: '';
    $crontabInstalled = strpos($currentCron, 'cron_translation_queue.php') !== false;
} catch (Throwable $e) {}

// ============================================================
//   اطلاعات مسیرها
// ============================================================
$phpPath = rtrim(PHP_BINARY ?: '/usr/bin/php', '/');

$scriptPath = realpath(__DIR__ . '/cron_translation_queue.php');
$logPath = __DIR__ . '/../logs/cron_stdout.log';
$basePath = dirname(__DIR__);

// دستورات آماده
$cmds = [
    'linux_cron' => "*/2 * * * * {$phpPath} {$scriptPath} 3 120 >> {$logPath} 2>&1",
    'manual' => "{$phpPath} {$scriptPath} 5",
    'direct' => "{$phpPath} {$scriptPath} 20 600",
    'wget' => "wget -q -O /dev/null \"https://yourdomain.com/admin/cron_web.php?key=YOUR_SECRET_KEY\"",
    'curl' => "curl -s \"https://yourdomain.com/admin/cron_web.php?key=YOUR_SECRET_KEY\" > /dev/null",
];

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';

// تشخیص محیط
$os = $env['os'];
$isTermux = ($os === 'termux');
$isLinux = ($os === 'linux');
$isWindows = ($os === 'windows');
$isMacOS = ($os === 'macos');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>راه‌اندازی Cron — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.card .header h5{margin:0;font-size:15px;color:#1e293b}
.card .body{padding:20px}

/* Status */
.status-box{padding:15px 20px;border-radius:10px;margin-bottom:15px;font-size:13px}
.status-ok{background:#d1fae5;color:#065f46;border-right:4px solid #10b981}
.status-warn{background:#fef3c7;color:#92400e;border-right:4px solid #f59e0b}
.status-bad{background:#fee2e2;color:#991b1b;border-right:4px solid #ef4444}

/* Buttons */
.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;transition:.2s;text-decoration:none}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-info-a{background:linear-gradient(135deg,#6366f1,#4f46e5)}

/* Code block */
.cmd-box{background:#1e293b;color:#10b981;padding:14px 18px;border-radius:10px;font-family:monospace;font-size:12px;direction:ltr;text-align:left;overflow-x:auto;position:relative;margin:10px 0}
.cmd-box .copy-btn{position:absolute;top:8px;right:8px;background:#334155;color:#cbd5e1;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:10px}
.cmd-box .copy-btn:hover{background:#475569;color:#fff}

/* Tabs */
.env-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;border-bottom:2px solid #e2e8f0;padding-bottom:12px}
.env-tab{padding:10px 18px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;font-size:13px;font-weight:bold;color:#64748b;transition:.2s;text-decoration:none}
.env-tab:hover{border-color:#7c3aed;color:#7c3aed}
.env-tab.active{background:#7c3aed;border-color:#7c3aed;color:#fff}

.step{padding:15px 20px;background:#f8fafc;border-radius:10px;margin-bottom:12px;border-right:3px solid #7c3aed}
.step .num{display:inline-block;width:24px;height:24px;background:#7c3aed;color:#fff;border-radius:50%;text-align:center;line-height:24px;font-weight:bold;font-size:12px;margin-left:8px}
.step h6{margin:0 0 8px;color:#1e293b;font-size:14px}
.step p{margin:5px 0;font-size:12px;color:#475569;line-height:1.8}

.info-box{background:#ecfeff;border-right:4px solid #0891b2;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}
.warn-box{background:#fef3c7;border-right:4px solid #f59e0b;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}
.danger-box{background:#fee2e2;border-right:4px solid #ef4444;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <!-- Header -->
    <div class="page-header">
        <h1>⏰ راه‌اندازی Cron</h1>
        <p>اجرای خودکار صف ترجمه در محیط‌های مختلف</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- وضعیت فعلی -->
    <div class="card">
        <div class="header"><h5>📊 وضعیت فعلی</h5></div>
        <div class="body">
            <div class="status-box <?= $crontabInstalled ? 'status-ok' : ($isWindows || $os === 'unknown' ? 'status-warn' : 'status-warn') ?>">
                <?php if ($crontabInstalled): ?>
                    ✅ <strong>Cron نصب شده</strong> — صف هر ۲ دقیقه خودکار پردازش می‌شود
                <?php elseif ($isWindows): ?>
                    ⚠️ <strong>Windows Server</strong> — نیاز به Task Scheduler دارد
                <?php else: ?>
                    ⚠️ <strong>Cron نصب نیست</strong> — صف فقط دستی پردازش می‌شود
                <?php endif; ?>
            </div>

            <table style="width:100%;font-size:12px;border-collapse:collapse">
                <tr><td style="padding:6px 0;width:180px"><strong>سیستم عامل:</strong></td><td><code><?= htmlspecialchars($os) ?></code></td></tr>
                <tr><td style="padding:6px 0"><strong>PHP:</strong></td><td><code><?= htmlspecialchars($phpPath) ?></code></td></tr>
                <tr><td style="padding:6px 0"><strong>اسکریپت:</strong></td><td><code><?= htmlspecialchars($scriptPath ?: 'نایافت') ?></code></td></tr>
                <tr><td style="padding:6px 0"><strong>روش اجرا:</strong></td><td><code><?= htmlspecialchars($env['background_method']) ?></code></td></tr>
                <tr><td style="padding:6px 0"><strong>Shell exec:</strong></td><td><?= $env['has_shell_exec'] ? '✅' : '❌' ?></td></tr>
                <tr><td style="padding:6px 0"><strong>crontab:</strong></td><td><?= $crontabInstalled ? '✅ نصب شده' : '❌ نصب نیست' ?></td></tr>
            </table>

            <hr style="margin:20px 0">

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="test_run">
                    <button type="submit" class="btn-a btn-primary-a">
                        <i class="bi bi-play-circle"></i> تست دستی (۲ job)
                    </button>
                </form>

                <?php if (!$crontabInstalled && ($isLinux || $isTermux)): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('نصب cron؟')">
                    <input type="hidden" name="action" value="install_crontab">
                    <button type="submit" class="btn-a btn-success-a">
                        <i class="bi bi-plus-circle"></i> نصب خودکار Cron
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($crontabInstalled): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('حذف cron؟')">
                    <input type="hidden" name="action" value="remove_crontab">
                    <button type="submit" class="btn-a btn-danger-a">
                        <i class="bi bi-trash"></i> حذف Cron
                    </button>
                </form>
                <?php endif; ?>

                <a href="queue.php" class="btn-a btn-secondary-a">
                    <i class="bi bi-list-ul"></i> پنل صف
                </a>
            </div>
        </div>
    </div>

    <!-- راهنمای محیط‌ها -->
    <div class="card">
        <div class="header"><h5>📖 راهنمای راه‌اندازی در محیط‌های مختلف</h5></div>
        <div class="body">

            <div class="env-tabs">
                <a class="env-tab <?= $isLinux ? 'active' : '' ?>" onclick="showEnv('linux')">🐧 VPS Linux (Ubuntu/Debian)</a>
                <a class="env-tab <?= $isTermux ? 'active' : '' ?>" onclick="showEnv('termux')">📱 Termux</a>
                <a class="env-tab" onclick="showEnv('cpanel')">🎛️ cPanel / DirectAdmin</a>
                <a class="env-tab" onclick="showEnv('windows')">🪟 Windows Server</a>
                <a class="env-tab" onclick="showEnv('docker')">🐳 Docker</a>
                <a class="env-tab" onclick="showEnv('none')">❌ بدون Cron</a>
            </div>

            <!-- Linux -->
            <div class="env-content" id="env-linux" style="display:<?= $isLinux ? 'block' : 'none' ?>">
                <div class="info-box">
                    ✅ <strong>روش پیشنهادی:</strong> crontab سراسری. صف هر ۲ دقیقه پردازش می‌شود.
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> ویرایش crontab</h6>
                    <div class="cmd-box">
                        crontab -e
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> اضافه کردن خط cron</h6>
                    <div class="cmd-box">
                        <?= htmlspecialchars($cmds['linux_cron']) ?>
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>💡 <strong>تنظیمات:</strong> <code>*/2</code> هر ۲ دقیقه، <code>3</code> حداکثر ۳ job، <code>120</code> حداکثر ۱۲۰ ثانیه</p>
                </div>

                <div class="step">
                    <h6><span class="num">۳</span> ذخیره و خروج</h6>
                    <p>در nano: <code>Ctrl+O</code> → <code>Enter</code> → <code>Ctrl+X</code></p>
                </div>

                <div class="step">
                    <h6><span class="num">۴</span> تست</h6>
                    <div class="cmd-box">
                        crontab -l
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>باید خط cron را ببینی. منتظر بمان ۲ دقیقه و لاگ را چک کن:</p>
                    <div class="cmd-box">
                        tail -f <?= htmlspecialchars($logPath) ?>
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>
            </div>

            <!-- Termux -->
            <div class="env-content" id="env-termux" style="display:<?= $isTermux ? 'block' : 'none' ?>">
                <div class="info-box">
                    ✅ <strong>روش پیشنهادی:</strong> crontab از <code>termux-services</code> یا <code>cronie</code>.
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> نصب cronie</h6>
                    <div class="cmd-box">
                        pkg install cronie
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> فعال‌سازی crond</h6>
                    <div class="cmd-box">
                        crond
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>این دستور crond را در پس‌زمینه اجرا می‌کند.</p>
                </div>

                <div class="step">
                    <h6><span class="num">۳</span> افزودن خط cron</h6>
                    <div class="cmd-box">
                        crontab -e
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>و اضافه کن:</p>
                    <div class="cmd-box">
                        <?= htmlspecialchars($cmds['linux_cron']) ?>
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>

                <div class="warn-box">
                    ⚠️ <strong>نکته:</strong> crond بعد از بستن Termux خاموش می‌شود. برای اجرای دائمی از <code>termux-services</code> استفاده کن.
                </div>
            </div>

            <!-- cPanel -->
            <div class="env-content" id="env-cpanel" style="display:none">
                <div class="info-box">
                    🎛️ <strong>روش پیشنهادی:</strong> از UI مدیریت هاست استفاده کن.
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> ورود به cPanel</h6>
                    <p>از مرورگر وارد <code>cpanel.yourdomain.com</code> شو</p>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> بخش Cron Jobs</h6>
                    <p>جستجو کن: <strong>Cron Jobs</strong> → کلیک کن</p>
                </div>

                <div class="step">
                    <h6><span class="num">۳</span> تنظیم زمان</h6>
                    <p>انتخاب کن:</p>
                    <ul style="font-size:12px;color:#475569">
                        <li><strong>Minute:</strong> <code>*/2</code></li>
                        <li><strong>Hour:</strong> <code>*</code></li>
                        <li><strong>Day:</strong> <code>*</code></li>
                        <li><strong>Month:</strong> <code>*</code></li>
                        <li><strong>Weekday:</strong> <code>*</code></li>
                    </ul>
                </div>

                <div class="step">
                    <h6><span class="num">۴</span> وارد کردن دستور</h6>
                    <div class="cmd-box">
                        /usr/local/bin/php <?= htmlspecialchars($basePath) ?>/admin/cron_translation_queue.php 3 120 > /dev/null 2>&1
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>⚠️ <strong>مهم:</strong> مسیر PHP رو با <code>which php</code> پیدا کن یا با پشتیبانی هاست چک کن</p>
                </div>

                <div class="step">
                    <h6><span class="num">۵</span> تست</h6>
                    <p>روی <strong>Run Now</strong> کلیک کن و لاگ رو چک کن.</p>
                </div>

                <div class="warn-box">
                    ⚠️ اگه cron در cPanel کار نکرد، شاید <code>exec</code> و <code>shell_exec</code> غیرفعال باشن. در این حالت از <strong>روش بدون Cron</strong> استفاده کن.
                </div>
            </div>

            <!-- Windows -->
            <div class="env-content" id="env-windows" style="display:none">
                <div class="info-box">
                    🪟 <strong>روش پیشنهادی:</strong> Windows Task Scheduler
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> باز کردن Task Scheduler</h6>
                    <p>Start → جستجو: <code>Task Scheduler</code></p>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> Create Basic Task</h6>
                    <p>Action → Create Basic Task</p>
                    <p>Name: <code>PartoCMS Translation Queue</code></p>
                </div>

                <div class="step">
                    <h6><span class="num">۳</span> Trigger</h6>
                    <p>Daily → Repeat every <strong>2 minutes</strong> for <strong>1 day</strong></p>
                </div>

                <div class="step">
                    <h6><span class="num">۴</span> Action</h6>
                    <p>Start a program:</p>
                    <ul style="font-size:12px;color:#475569">
                        <li>Program: <code>C:\path\to\php.exe</code></li>
                        <li>Arguments: <code><?= htmlspecialchars(str_replace('/', '\\', $scriptPath ?: 'path')) ?> 3 120</code></li>
                        <li>Start in: <code><?= htmlspecialchars(str_replace('/', '\\', $basePath)) ?></code></li>
                    </ul>
                </div>
            </div>

            <!-- Docker -->
            <div class="env-content" id="env-docker" style="display:none">
                <div class="info-box">
                    🐳 <strong>روش پیشنهادی:</strong> sidecar container یا supervisord
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> روش Sidecar (پیشنهادی)</h6>
                    <p>در <code>docker-compose.yml</code> یه سرویس اضافه کن:</p>
                    <div class="cmd-box">
cron-worker:
  image: php:8.5-cli
  volumes:
    - .:/var/www/html
  working_dir: /var/www/html
  command: >
    bash -c "while true; do
      php /var/www/html/admin/cron_translation_queue.php 3 120;
      sleep 120;
    done"
  restart: unless-stopped
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> روش Supervisord (داخل کانتینر)</h6>
                    <p>در <code>supervisord.conf</code>:</p>
                    <div class="cmd-box">
[program:partocms_queue]
command=php /var/www/html/admin/cron_translation_queue.php 3 120
autostart=true
autorestart=true
startsecs=10
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                </div>
            </div>

            <!-- بدون Cron -->
            <div class="env-content" id="env-none" style="display:none">
                <div class="info-box">
                    ❌ <strong>برای هاست‌های اشتراکی که cron و exec ندارند</strong>
                </div>

                <div class="step">
                    <h6><span class="num">۱</span> روش ۱: پردازش با هر بازدید (piggyback)</h6>
                    <p>در <code>config.php</code> این کد را اضافه کن:</p>
                    <div class="cmd-box">
// در config.php بعد از session_start()
if (random_int(1, 50) === 1) {  // ۲٪ شانس
    $cronScript = __DIR__ . '/admin/cron_translation_queue.php';
    if (file_exists($cronScript)) {
        // اجرای سریع ۱ job بدون انتظار
        @include $cronScript;
    }
}
                        <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                    </div>
                    <p>⚠️ این روش باعث کند شدن بازدیدها می‌شود. بهتره داخل cron اجرا بشه.</p>
                </div>

                <div class="step">
                    <h6><span class="num">۲</span> روش ۲: اجرای دستی از پنل</h6>
                    <p>از پنل صف (<a href="queue.php">queue.php</a>) → دکمه <strong>«پردازش فوری»</strong></p>
                </div>

                <div class="step">
                    <h6><span class="num">۳</span> روش ۳: Ping از سرویس خارجی</h6>
                    <p>فایل <code>admin/cron_web.php</code> بساز و از سرویس‌هایی مثل:</p>
                    <ul style="font-size:12px;color:#475569">
                        <li><a href="https://cron-job.org" target="_blank">cron-job.org</a> (رایگان)</li>
                        <li><a href="https://www.easycron.com" target="_blank">EasyCron</a></li>
                        <li><a href="https://uptimerobot.com" target="_blank">UptimeRobot</a></li>
                    </ul>
                    <p>استفاده کن — هر ۲-۵ دقیقه URL رو صدا بزنه.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- دستورات سریع -->
    <div class="card">
        <div class="header"><h5>⚡ دستورات سریع</h5></div>
        <div class="body">
            <div class="step">
                <h6>🚀 پردازش فوری ۵ job</h6>
                <div class="cmd-box">
                    <?= htmlspecialchars($cmds['manual']) ?>
                    <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                </div>
            </div>

            <div class="step">
                <h6>⚡ پردازش کامل (همه jobها)</h6>
                <div class="cmd-box">
                    <?= htmlspecialchars($cmds['direct']) ?>
                    <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                </div>
            </div>

            <div class="step">
                <h6>📊 مشاهده لاگ زنده</h6>
                <div class="cmd-box">
                    tail -f <?= htmlspecialchars($logPath) ?>
                    <button class="copy-btn" onclick="copyText(this)">📋 کپی</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function showEnv(name) {
    document.querySelectorAll('.env-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.env-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('env-' + name).style.display = 'block';
    event.target.classList.add('active');
}

function copyText(btn) {
    const code = btn.parentNode.textContent.replace('📋 کپی', '').trim();
    navigator.clipboard.writeText(code).then(() => {
        const old = btn.textContent;
        btn.textContent = '✅ شد!';
        setTimeout(() => btn.textContent = old, 1500);
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

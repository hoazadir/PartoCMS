<?php
/**
 * PartoCMS - Security Audit (تست جامع امنیتی)
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

// ==================== LOAD SECURITY CLASSES ====================
foreach (['rate_limiter.php', 'fim.php', 'input_validator.php'] as $f) {
    $p = __DIR__ . '/includes/' . $f;
    if (file_exists($p)) require_once $p;
}

// ==================== Test Framework ====================
$tests = [];
$passed = 0;
$failed = 0;
$warnings = 0;

function addTest(&$tests, $name, $status, $detail = '', $severity = 'medium') {
    global $passed, $failed, $warnings;
    $tests[] = ['name' => $name, 'status' => $status, 'detail' => $detail, 'severity' => $severity];
    if ($status === 'pass') $passed++;
    elseif ($status === 'fail') $failed++;
    else $warnings++;
}

function checkFile($path) {
    return file_exists($path) ? 'pass' : 'fail';
}

// ==================== 1. Security Files ====================
$requiredFiles = [
    'security_config.php'     => __DIR__ . '/includes/security_config.php',
    'fim.php'                 => __DIR__ . '/includes/fim.php',
    'input_validator.php'     => __DIR__ . '/includes/input_validator.php',
    'rate_limiter.php'        => __DIR__ . '/includes/rate_limiter.php',
    'telegram_notifier.php'   => __DIR__ . '/includes/telegram_notifier.php',
    'virustotal_scanner.php'  => __DIR__ . '/includes/virustotal_scanner.php',
    'security_dashboard.php'  => __DIR__ . '/security_dashboard.php',
    'cron_security_check.php' => __DIR__ . '/cron_security_check.php',
    'telegram_setup.php'      => __DIR__ . '/telegram_setup.php',
    'virustotal_setup.php'    => __DIR__ . '/virustotal_setup.php',
    'login.php'               => __DIR__ . '/login.php',
    'media.php'               => __DIR__ . '/media.php',
];

foreach ($requiredFiles as $name => $path) {
    addTest($tests, "File: $name", checkFile($path), $path, 'high');
}

// ==================== 2. PHP Syntax Check ====================
foreach ($requiredFiles as $name => $path) {
    if (!file_exists($path)) continue;
    $output = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    $ok = (strpos($output, 'No syntax errors') !== false);
    addTest($tests, "Syntax: $name", $ok ? 'pass' : 'fail', trim(substr($output, 0, 80)), 'high');
}

// ==================== 3. Database Tables ====================
$requiredTables = ['file_hashes', 'security_logs', 'login_attempts', 'login_blocks'];
foreach ($requiredTables as $table) {
    try {
        $exists = (bool) $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetch();
        addTest($tests, "DB Table: $table", $exists ? 'pass' : 'fail', '', 'high');
    } catch (Throwable $e) {
        addTest($tests, "DB Table: $table", 'fail', $e->getMessage(), 'high');
    }
}

// ==================== 4. Settings ====================
$settingsChecks = [
    'virustotal_api_key'   => ['critical' => true,  'label' => 'VirusTotal API Key'],
    'telegram_bot_token'   => ['critical' => false, 'label' => 'Telegram Bot Token'],
    'telegram_chat_id'     => ['critical' => false, 'label' => 'Telegram Chat ID'],
];

foreach ($settingsChecks as $key => $info) {
    try {
        $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
        $st->execute([':k' => $key]);
        $val = $st->fetchColumn();
        $ok = !empty($val);
        $detail = $ok ? '✅ تنظیم شده (' . strlen($val) . ' char)' : '❌ تنظیم نشده';
        addTest($tests, "Setting: " . $info['label'], $ok ? 'pass' : ($info['critical'] ? 'fail' : 'warn'), $detail, 'medium');
    } catch (Throwable $e) {
        addTest($tests, "Setting: " . $info['label'], 'warn', $e->getMessage(), 'medium');
    }
}

// ==================== 5. File Permissions ====================
$uploadsDir = __DIR__ . '/../assets/uploads';
if (is_dir($uploadsDir)) {
    $writable = is_writable($uploadsDir);
    addTest($tests, "Uploads dir writable", $writable ? 'pass' : 'fail', $uploadsDir, 'high');
    
    // Check for executable files in uploads
    $dangerous = glob($uploadsDir . '/*.{php,php3,php4,php5,phtml,phar,exe,sh}', GLOB_BRACE);
    $dangerous = array_merge($dangerous, glob($uploadsDir . '/*.PHP'));
    addTest($tests, "No executable files in uploads", empty($dangerous) ? 'pass' : 'fail', 
        count($dangerous) . ' file(s) found', 'critical');
} else {
    addTest($tests, "Uploads dir exists", 'fail', $uploadsDir, 'high');
}

// ==================== 6. Cron Job ====================
$cronOutput = shell_exec("crontab -l 2>/dev/null");
$cronOk = strpos($cronOutput, 'cron_security_check.php') !== false;
addTest($tests, "Cron job configured", $cronOk ? 'pass' : 'warn', 
    $cronOk ? 'cron_security_check.php found' : 'Not configured', 'medium');

// Check crond status
$crondStatus = shell_exec("sv status crond 2>/dev/null");
$crondOk = strpos($crondStatus, 'run:') !== false;
addTest($tests, "crond service running", $crondOk ? 'pass' : 'warn', trim($crondStatus), 'medium');

// ==================== 7. VirusTotal API Test ====================
$vtKey = '';
try {
    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'virustotal_api_key' LIMIT 1");
    $st->execute();
    $vtKey = $st->fetchColumn() ?: '';
} catch (Throwable $e) {}

if (!empty($vtKey)) {
    // Test by checking a known file hash (EICAR's sha256)
    $eicarHash = '275a021bbfb6489e54d471899f7db9d1663fc695ec2fe2a2c4538aabf651fd0f';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://www.virustotal.com/api/v3/files/$eicarHash",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['x-apikey: ' . $vtKey, 'Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200) {
        $data = json_decode($res, true);
        $stats = $data['data']['attributes']['last_analysis_stats'] ?? [];
        $malicious = $stats['malicious'] ?? 0;
        addTest($tests, "VirusTotal API responds", 'pass', 
            "EICAR detected by $malicious engines", 'high');
    } elseif ($code === 404) {
        addTest($tests, "VirusTotal API responds", 'pass', 'File not found (but API works)', 'high');
    } elseif ($code === 401) {
        addTest($tests, "VirusTotal API Key valid", 'fail', 'HTTP 401 Unauthorized', 'critical');
    } else {
        addTest($tests, "VirusTotal API responds", 'warn', "HTTP $code", 'medium');
    }
} else {
    addTest($tests, "VirusTotal API Key", 'warn', 'Not configured', 'medium');
}

// ==================== 8. Telegram API Test ====================
$tgToken = '';
try {
    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token' LIMIT 1");
    $st->execute();
    $tgToken = $st->fetchColumn() ?: '';
} catch (Throwable $e) {}

if (!empty($tgToken)) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.telegram.org/bot$tgToken/getMe",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($res, true);
    if ($code === 200 && !empty($data['ok'])) {
        $botName = $data['result']['username'] ?? '?';
        addTest($tests, "Telegram Bot responds", 'pass', "@$botName", 'high');
    } else {
        addTest($tests, "Telegram Bot responds", 'fail', "HTTP $code", 'high');
    }
} else {
    addTest($tests, "Telegram Bot Token", 'warn', 'Not configured', 'medium');
}

// ==================== 9. Security Headers ====================
$headers = [
    'X-Frame-Options'        => headers_list(),
    'X-Content-Type-Options' => headers_list(),
];

// Check if security headers are sent by our code
$testHeaders = function() {
    if (!headers_sent()) {
        header("X-Test-Header-Audit: yes");
    }
};

// Instead, let's check the config
$secConfig = @file_get_contents(__DIR__ . '/includes/security_config.php');
$headerChecks = [
    'X-Frame-Options'        => strpos($secConfig, 'X-Frame-Options') !== false,
    'X-Content-Type-Options' => strpos($secConfig, 'X-Content-Type-Options') !== false,
    'Content-Security-Policy'=> strpos($secConfig, 'Content-Security-Policy') !== false,
    'Referrer-Policy'        => strpos($secConfig, 'Referrer-Policy') !== false,
];

foreach ($headerChecks as $header => $ok) {
    addTest($tests, "Header: $header", $ok ? 'pass' : 'fail', '', 'high');
}

// ==================== 10. Rate Limiter Test ====================
if (class_exists('RateLimiter')) {
    try {
        $limiter = new RateLimiter($pdo);
        $ip = RateLimiter::getClientIp();
        addTest($tests, "RateLimiter class works", 'pass', "Client IP: $ip", 'high');
    } catch (Throwable $e) {
        addTest($tests, "RateLimiter class works", 'fail', $e->getMessage(), 'high');
    }
} else {
    addTest($tests, "RateLimiter loaded", 'fail', 'Class not found', 'high');
}

// ==================== 11. FIM Class Test ====================
if (class_exists('FileIntegrityMonitor')) {
    addTest($tests, "FIM class loaded", 'pass', '', 'high');
    
    try {
        $fim = new FileIntegrityMonitor($pdo, realpath(__DIR__ . '/..'));
        $count = $pdo->query("SELECT COUNT(*) FROM file_hashes")->fetchColumn();
        addTest($tests, "FIM has data", $count > 0 ? 'pass' : 'warn', "$count files monitored", 'medium');
    } catch (Throwable $e) {
        addTest($tests, "FIM works", 'fail', $e->getMessage(), 'medium');
    }
} else {
    addTest($tests, "FIM loaded", 'fail', 'Class not found', 'high');
}

// ==================== 12. Statistics ====================
$stats = [];
try {
    $stats['files_monitored'] = $pdo->query("SELECT COUNT(*) FROM file_hashes")->fetchColumn();
    $stats['malware_blocked'] = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE type='malware'")->fetchColumn();
    $stats['total_scans'] = $pdo->query("SELECT COUNT(*) FROM security_logs WHERE type='scan'")->fetchColumn();
    $stats['login_attempts'] = $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
    $stats['active_blocks'] = $pdo->query("SELECT COUNT(*) FROM login_blocks WHERE blocked_until > NOW()")->fetchColumn();
} catch (Throwable $e) {}

// ==================== Calculate Score ====================
$total = $passed + $failed + $warnings;
$score = $total > 0 ? round(($passed / $total) * 100) : 0;
$grade = $score >= 90 ? 'A+' : ($score >= 80 ? 'A' : ($score >= 70 ? 'B' : ($score >= 60 ? 'C' : 'D')));

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تست جامع امنیتی — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#059669,#047857);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.score-card{background:#fff;border-radius:15px;padding:30px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:25px}
.score-value{font-size:72px;font-weight:bold;background:linear-gradient(135deg,#059669,#047857);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.score-grade{font-size:32px;margin-top:5px}
.score-label{color:#64748b;font-size:14px;margin-top:10px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:20px 0}
.stat-mini{background:#f8fafc;border-radius:10px;padding:12px;text-align:center}
.stat-mini .num{font-size:22px;font-weight:bold;color:#1e293b}
.stat-mini .lbl{font-size:11px;color:#64748b}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.test-item{padding:12px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:12px;font-size:13px}
.test-item:hover{background:#f8fafc}
.test-icon{font-size:18px;width:24px;text-align:center}
.test-name{flex:1;color:#1e293b;font-weight:bold}
.test-detail{color:#64748b;font-size:12px;font-family:monospace;direction:ltr;text-align:left;max-width:40%;word-break:break-all}
.test-item.pass{border-right:4px solid #10b981}
.test-item.fail{border-right:4px solid #ef4444;background:#fef2f2}
.test-item.warn{border-right:4px solid #f59e0b;background:#fffbeb}
.progress{height:12px;border-radius:10px;background:#e2e8f0;overflow:hidden;margin-top:15px}
.progress-bar{height:100%;transition:width .5s;background:linear-gradient(90deg,#059669,#10b981)}
.filter-btns{display:flex;gap:8px;padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.filter-btn{padding:6px 14px;border-radius:20px;background:#fff;border:1px solid #e2e8f0;font-size:12px;color:#475569;text-decoration:none;cursor:pointer}
.filter-btn.active{background:#1e293b;color:#fff}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🛡️ تست جامع امنیتی</h1>
        <p>بررسی کامل ۹ لایه امنیتی PartoCMS</p>
    </div>

    <!-- Score Card -->
    <div class="score-card">
        <div class="score-value"><?= $score ?>%</div>
        <div class="score-grade">
            <?php if ($grade === 'A+') echo '🏆'; elseif ($grade === 'A') echo '⭐'; elseif ($grade === 'B') echo '👍'; else echo '⚠️'; ?>
            نمره: <?= $grade ?>
        </div>
        <div class="score-label">
            <?= $passed ?> تست موفق • <?= $failed ?> خطا • <?= $warnings ?> هشدار
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= $score ?>%"></div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-mini">
            <div class="num"><?= number_format($stats['files_monitored'] ?? 0) ?></div>
            <div class="lbl">فایل تحت نظر</div>
        </div>
        <div class="stat-mini">
            <div class="num" style="color:#ef4444"><?= $stats['malware_blocked'] ?? 0 ?></div>
            <div class="lbl">بدافزار مسدود</div>
        </div>
        <div class="stat-mini">
            <div class="num" style="color:#3b82f6"><?= $stats['total_scans'] ?? 0 ?></div>
            <div class="lbl">اسکن انجام شده</div>
        </div>
        <div class="stat-mini">
            <div class="num"><?= $stats['login_attempts'] ?? 0 ?></div>
            <div class="lbl">تلاش لاگین</div>
        </div>
        <div class="stat-mini">
            <div class="num" style="color:#f59e0b"><?= $stats['active_blocks'] ?? 0 ?></div>
            <div class="lbl">IP بلاک شده</div>
        </div>
    </div>

    <!-- Failed Tests Alert -->
    <?php 
    $failedTests = array_filter($tests, fn($t) => $t['status'] === 'fail');
    if (!empty($failedTests)): 
    ?>
    <div class="content-card">
        <div class="header">
            <h5>❌ خطاهای بحرانی (<?= count($failedTests) ?>)</h5>
        </div>
        <?php foreach ($failedTests as $test): ?>
        <div class="test-item fail">
            <span class="test-icon">❌</span>
            <span class="test-name"><?= htmlspecialchars($test['name']) ?></span>
            <span class="test-detail"><?= htmlspecialchars($test['detail']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Warnings -->
    <?php 
    $warnTests = array_filter($tests, fn($t) => $t['status'] === 'warn');
    if (!empty($warnTests)): 
    ?>
    <div class="content-card">
        <div class="header">
            <h5>⚠️ هشدارها (<?= count($warnTests) ?>)</h5>
        </div>
        <?php foreach ($warnTests as $test): ?>
        <div class="test-item warn">
            <span class="test-icon">⚠️</span>
            <span class="test-name"><?= htmlspecialchars($test['name']) ?></span>
            <span class="test-detail"><?= htmlspecialchars($test['detail']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Passed Tests -->
    <div class="content-card">
        <div class="header">
            <h5>✅ تست‌های موفق (<?= $passed ?>)</h5>
        </div>
        <?php foreach ($tests as $test): if ($test['status'] !== 'pass') continue; ?>
        <div class="test-item pass">
            <span class="test-icon">✅</span>
            <span class="test-name"><?= htmlspecialchars($test['name']) ?></span>
            <?php if (!empty($test['detail'])): ?>
            <span class="test-detail"><?= htmlspecialchars($test['detail']) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Actions -->
    <div class="content-card">
        <div class="header"><h5>🔄 اجرای دوباره</h5></div>
        <div style="padding:15px">
            <a href="security_audit.php" class="btn btn-primary" style="background:linear-gradient(135deg,#059669,#047857);border:none;padding:10px 20px;border-radius:8px;color:#fff;text-decoration:none;font-weight:bold">
                🔄 اجرای مجدد تست
            </a>
            <a href="security_dashboard.php" class="btn btn-secondary" style="background:#64748b;padding:10px 20px;border-radius:8px;color:#fff;text-decoration:none;font-weight:bold;margin-right:8px">
                🛡️ بازگشت به داشبورد
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php
/**
 * اسکریپت تشخیصی موقت
 * ⚠️ بعد از تست، این فایل رو حتماً پاک کن
 */
header('Content-Type: text/html; charset=utf-8');

// جلوگیری از دسترسی تصادفی
if (!isset($_GET['run']) || $_GET['run'] !== 'yes') {
    die('برای اجرا، آدرس رو اینطور باز کن: diag.php?run=yes');
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Diagnostic</title>';
echo '<style>
body{background:#111;color:#0f0;font-family:monospace;padding:20px;direction:ltr;font-size:13px;line-height:1.7;}
h2{color:#ff0;border-bottom:1px solid #333;padding-bottom:5px;margin-top:25px;}
.box{background:#000;border:1px solid #333;padding:12px;margin:8px 0;border-radius:5px;}
.ok{color:#0f0;} .bad{color:#f55;} .warn{color:#fa0;}
table{border-collapse:collapse;width:100%;}
td{padding:6px 10px;border-bottom:1px solid #222;vertical-align:top;}
td:first-child{color:#888;width:250px;}
</style></head><body>';

echo '<h1 style="color:#0ff;">🔍 Diagnostic Report</h1>';

// ============================================================
echo '<h2>1) Server Info</h2><div class="box"><table>';
$serverInfo = [
    'PHP Version'      => phpversion(),
    'Server Software'  => $_SERVER['SERVER_SOFTWARE'] ?? '?',
    'HTTP_HOST'        => $_SERVER['HTTP_HOST'] ?? '?',
    'REQUEST_URI'      => $_SERVER['REQUEST_URI'] ?? '?',
    'SCRIPT_NAME'      => $_SERVER['SCRIPT_NAME'] ?? '?',
    'PHP_SELF'         => $_SERVER['PHP_SELF'] ?? '?',
    'DOCUMENT_ROOT'    => $_SERVER['DOCUMENT_ROOT'] ?? '?',
    'SCRIPT_FILENAME'  => $_SERVER['SCRIPT_FILENAME'] ?? '?',
    '__FILE__'         => __FILE__,
    '__DIR__'          => __DIR__,
];
foreach ($serverInfo as $k => $v) {
    echo "<tr><td>$k</td><td>" . htmlspecialchars($v) . "</td></tr>";
}
echo '</table></div>';

// ============================================================
echo '<h2>2) Files in /admin/ directory</h2><div class="box">';
$files = @scandir(__DIR__);
if ($files === false) {
    echo '<span class="bad">❌ نمی‌توان پوشه را خواند</span>';
} else {
    $phpFiles = array_filter($files, fn($f) => pathinfo($f, PATHINFO_EXTENSION) === 'php');
    echo '<b>تعداد فایل‌های PHP: ' . count($phpFiles) . '</b><br><br>';
    sort($phpFiles);
    foreach ($phpFiles as $f) {
        $exists = file_exists(__DIR__ . '/' . $f) ? '✅' : '❌';
        $size = file_exists(__DIR__ . '/' . $f) ? number_format(filesize(__DIR__ . '/' . $f)) . ' bytes' : '';
        echo "$exists <b>" . htmlspecialchars($f) . "</b> — $size<br>";
    }
}
echo '</div>';

// ============================================================
echo '<h2>3) Check Required Files</h2><div class="box">';
$requiredFiles = [
    'index.php', 'reports.php', 'seo.php', 'backups.php',
    'categories.php', 'media.php', 'comments.php',
    'forms.php', 'menus.php', 'templates.php', 'editor.php',
    'users.php', 'roles.php', 'modules.php', 'settings.php',
    'logout.php', 'sidebar.php'
];
foreach ($requiredFiles as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo '<span class="ok">✅</span> ' . $f . '<br>';
    } else {
        echo '<span class="bad">❌ MISSING → ' . $f . '</span><br>';
    }
}
echo '</div>';

// ============================================================
echo '<h2>4) Find config file & constants</h2><div class="box">';
$searchDirs = [
    __DIR__,
    dirname(__DIR__),
    dirname(__DIR__) . '/config',
    dirname(__DIR__) . '/includes',
    dirname(__DIR__) . '/core',
];
foreach ($searchDirs as $dir) {
    if (!is_dir($dir)) continue;
    echo '<b>📁 ' . htmlspecialchars($dir) . '</b><br>';
    $found = [];
    foreach (scandir($dir) as $f) {
        if (in_array($f, ['.', '..'])) continue;
        if (preg_match('/(config|constant|setting|init|bootstrap)/i', $f)) {
            $found[] = $f;
        }
    }
    if (empty($found)) {
        echo '<span class="warn">(فایل کانفیگ پیدا نشد)</span><br>';
    } else {
        foreach ($found as $f) {
            echo '→ ' . htmlspecialchars($f) . '<br>';
        }
    }
    echo '<br>';
}
echo '</div>';

// ============================================================
echo '<h2>5) Try to load config & read constants</h2><div class="box">';

$possibleConfigs = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../core/config.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../settings.php',
    __DIR__ . '/../init.php',
];

$loaded = false;
foreach ($possibleConfigs as $cfg) {
    if (file_exists($cfg)) {
        echo "📄 Found config: <b>" . htmlspecialchars($cfg) . "</b><br>";
        // تلاش برای لود کردن امن (بدون اجرای real)
        // فقط ثابت‌ها رو چک می‌کنیم که تعریف شدن یا نه
        $loaded = $cfg;
        break;
    }
}

if (!$loaded) {
    echo '<span class="bad">❌ هیچ فایل کانفیگی پیدا نشد. مسیرها رو دستی چک کن.</span>';
} else {
    // تلاش می‌کنیم با @ خطاها رو خفه کنیم و ببینیم ثابت‌ها تعریف شدن
    @include_once $loaded;

    $constants = ['ADMIN_URL', 'SITE_URL', 'BASE_URL', 'ROOT_PATH', 'BASE_PATH'];
    echo '<br><b>🔍 ثابت‌های تعریف‌شده:</b><br>';
    foreach ($constants as $c) {
        if (defined($c)) {
            echo '<span class="ok">✅ ' . $c . ' = [' . htmlspecialchars(constant($c)) . ']</span><br>';
        } else {
            echo '<span class="warn">⚠️ ' . $c . ' تعریف نشده</span><br>';
        }
    }
}
echo '</div>';

// ============================================================
echo '<h2>6) Test URL Building</h2><div class="box">';
$testUrls = [
    'ADMIN_URL + /index.php' => (defined('ADMIN_URL') ? ADMIN_URL . '/index.php' : 'ADMIN_URL تعریف نشده'),
    'ADMIN_URL + index.php'  => (defined('ADMIN_URL') ? ADMIN_URL . 'index.php' : 'ADMIN_URL تعریف نشده'),
    'SITE_URL + /index.php'  => (defined('SITE_URL') ? SITE_URL . '/index.php' : 'SITE_URL تعریف نشده'),
];
foreach ($testUrls as $label => $url) {
    echo '<b>' . htmlspecialchars($label) . ':</b><br>';
    echo '→ <span style="color:#0ff;">' . htmlspecialchars($url) . '</span><br><br>';
}
echo '</div>';

// ============================================================
echo '<h2>7) Check .htaccess files</h2><div class="box">';
$htaccessLocations = [
    __DIR__ . '/.htaccess',
    dirname(__DIR__) . '/.htaccess',
];
foreach ($htaccessLocations as $ht) {
    if (file_exists($ht)) {
        echo '<span class="ok">✅ موجود:</span> ' . htmlspecialchars($ht) . '<br>';
        echo '<pre style="background:#000;padding:10px;border:1px solid #333;overflow:auto;">';
        echo htmlspecialchars(file_get_contents($ht));
        echo '</pre><br>';
    } else {
        echo '<span class="warn">⚠️ ندارد:</span> ' . htmlspecialchars($ht) . '<br>';
    }
}
echo '</div>';

// ============================================================
echo '<h2>8) Test actual HTTP request</h2><div class="box">';
if (function_exists('curl_init')) {
    $testUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/admin/index.php';
    echo '🔗 تست: <b>' . htmlspecialchars($testUrl) . '</b><br>';
    
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $color = ($httpCode >= 200 && $httpCode < 300) ? 'ok' : 'bad';
    echo '<span class="' . $color . '">HTTP Status: ' . $httpCode . '</span><br>';
    echo '<pre style="background:#000;padding:10px;border:1px solid #333;overflow:auto;max-height:200px;">';
    echo htmlspecialchars($response);
    echo '</pre>';
} else {
    echo '<span class="warn">curl در دسترس نیست</span>';
}
echo '</div>';

// ============================================================
echo '<h2>9) Permission Check</h2><div class="box">';
echo 'پوشه admin قابل خواندن: ' . (is_readable(__DIR__) ? '<span class="ok">✅</span>' : '<span class="bad">❌</span>') . '<br>';
echo 'پوشه admin قابل نوشتن: ' . (is_writable(__DIR__) ? '<span class="ok">✅</span>' : '<span class="warn">⚠️</span>') . '<br>';
echo 'Permission index.php: ' . (file_exists(__DIR__ . '/index.php') ? substr(sprintf('%o', fileperms(__DIR__ . '/index.php')), -4) : 'N/A') . '<br>';
echo '</div>';

echo '<hr style="border-color:#333;margin:30px 0;">';
echo '<p style="color:#f55;"><b>⚠️ این فایل رو بعد از تست پاک کن!</b></p>';
echo '</body></html>';

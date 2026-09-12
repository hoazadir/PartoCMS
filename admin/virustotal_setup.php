<?php
/**
 * PartoCMS - VirusTotal Virus Scanner Setup
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

// ==================== Helper: settings auto-detect ====================
function detectSettingsColumnsVt($pdo) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'Field');
        if (in_array('setting_key', $names) && in_array('setting_value', $names)) return ['key' => 'setting_key', 'value' => 'setting_value'];
        if (in_array('name', $names) && in_array('value', $names)) return ['key' => 'name', 'value' => 'value'];
        if (in_array('key', $names) && in_array('value', $names)) return ['key' => 'key', 'value' => 'value'];
        return null;
    } catch (Throwable $e) { return null; }
}

function getSettingVt($pdo, $key, $default = '') {
    $cols = detectSettingsColumnsVt($pdo);
    if (!$cols) return $default;
    try {
        $st = $pdo->prepare("SELECT `{$cols['value']}` FROM settings WHERE `{$cols['key']}` = :k LIMIT 1");
        $st->execute([':k' => $key]);
        $v = $st->fetchColumn();
        return ($v === false) ? $default : $v;
    } catch (Throwable $e) { return $default; }
}

function setSettingVt($pdo, $key, $value) {
    $cols = detectSettingsColumnsVt($pdo);
    if (!$cols) return false;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE `{$cols['key']}` = :k");
        $st->execute([':k' => $key]);
        if ($st->fetchColumn() > 0) {
            $st = $pdo->prepare("UPDATE settings SET `{$cols['value']}` = :v WHERE `{$cols['key']}` = :k");
            return $st->execute([':v' => $value, ':k' => $key]);
        } else {
            $st = $pdo->prepare("INSERT INTO settings (`{$cols['key']}`, `{$cols['value']}`) VALUES (:k, :v)");
            return $st->execute([':k' => $key, ':v' => $value]);
        }
    } catch (Throwable $e) { return false; }
}

// ==================== Test VirusTotal API Key ====================
function testVirusTotalKey($apiKey) {
    // Test with a harmless file (hostname)
    $testFile = tempnam(sys_get_temp_dir(), 'vt_test_');
    file_put_contents($testFile, "This is a safe test file.\n");

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.virustotal.com/api/v3/files',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['file' => new CURLFile($testFile)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'x-apikey: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    @unlink($testFile);

    if ($err) return ['ok' => false, 'error' => 'Network error: ' . $err];
    if ($httpCode === 401) return ['ok' => false, 'error' => 'API Key نامعتبر است (401 Unauthorized)'];
    if ($httpCode === 403) return ['ok' => false, 'error' => 'دسترسی ممنوع (403) - ممکن است از محدودیت منطقه‌ای باشد'];
    if ($httpCode === 429) return ['ok' => false, 'error' => 'محدودیت نرخ درخواست (429) - کمی صبر کنید'];
    if ($httpCode !== 200 && $httpCode !== 201) {
        return ['ok' => false, 'error' => "HTTP $httpCode - " . substr($res, 0, 200)];
    }

    $data = json_decode($res, true);
    $analysisId = $data['data']['id'] ?? null;
    return ['ok' => true, 'analysis_id' => $analysisId, 'data' => $data];
}

// ==================== Actions ====================
$message = '';
$messageType = '';
$savedKey = getSettingVt($pdo, 'virustotal_api_key', '');
$isConfigured = !empty($savedKey);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_key') {
        $key = trim($_POST['api_key'] ?? '');
        if (empty($key)) {
            $message = 'لطفاً API Key را وارد کنید';
            $messageType = 'danger';
        } elseif (strlen($key) < 20) {
            $message = 'API Key کوتاه است — فرمت صحیح: ۶۴ کاراکتر';
            $messageType = 'danger';
        } else {
            $test = testVirusTotalKey($key);
            if (!empty($test['ok'])) {
                if (setSettingVt($pdo, 'virustotal_api_key', $key)) {
                    $message = '✅ API Key ذخیره شد و اعتبارسنجی موفق بود';
                    $messageType = 'success';
                    $savedKey = $key;
                    $isConfigured = true;
                } else {
                    $message = '❌ خطا در ذخیره در دیتابیس';
                    $messageType = 'danger';
                }
            } else {
                $message = '❌ ' . htmlspecialchars($test['error']);
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'test') {
        if (empty($savedKey)) {
            $message = 'ابتدا API Key را ذخیره کنید';
            $messageType = 'danger';
        } else {
            $test = testVirusTotalKey($savedKey);
            if (!empty($test['ok'])) {
                $message = '✅ API Key معتبر است — همه چیز آماده است';
                $messageType = 'success';
            } else {
                $message = '❌ ' . htmlspecialchars($test['error']);
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'reset') {
        setSettingVt($pdo, 'virustotal_api_key', '');
        $savedKey = '';
        $isConfigured = false;
        $message = '♻️ API Key حذف شد';
        $messageType = 'info';
    }
}

$csrfToken = class_exists('SecurityValidator') ? SecurityValidator::generateCsrfToken() : ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)));
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = $csrfToken;
$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ویروس‌یاب VirusTotal — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.content-card .body{padding:22px}
.instruction-list{background:#f0f9ff;border-right:4px solid #0ea5e9;padding:15px 20px;border-radius:8px;margin-bottom:20px}
.instruction-list ol{margin:0;padding-right:20px;font-size:13px;line-height:2}
.instruction-list code{background:#1e293b;color:#7dd3fc;padding:2px 8px;border-radius:5px;font-size:12px;direction:ltr;display:inline-block}
.instruction-list a{color:#0ea5e9;font-weight:bold;text-decoration:none}
.instruction-list a:hover{text-decoration:underline}
.btn-vt{padding:12px 22px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px;transition:.2s;margin:4px}
.btn-vt:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
.btn-primary-vt{background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff}
.btn-success-vt{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-danger-vt{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
.btn-secondary-vt{background:linear-gradient(135deg,#64748b,#334155);color:#fff;text-decoration:none}
.token-input{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:10px;font-family:monospace;font-size:13px;direction:ltr;text-align:left;transition:.2s}
.token-input:focus{outline:none;border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,.15)}
.saved-box{background:#f0fdf4;border:2px solid #10b981;border-radius:10px;padding:15px 20px;margin-bottom:15px;font-size:13px;font-family:monospace;direction:ltr;text-align:left;word-break:break-all}
.status-badge{display:inline-block;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:bold}
.status-active{background:#d1fae5;color:#065f46}
.status-inactive{background:#fee2e2;color:#991b1b}
.external-link{display:inline-flex;align-items:center;gap:5px;background:#fff;padding:8px 14px;border-radius:8px;color:#0ea5e9;text-decoration:none;font-size:13px;border:1px solid #bae6fd;margin:5px 5px 5px 0;font-weight:bold}
.external-link:hover{background:#0ea5e9;color:#fff}
.plan-box{background:#ecfdf5;border:2px solid #10b981;border-radius:10px;padding:15px 20px;font-size:13px;line-height:1.8}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🦠 ویروس‌یاب VirusTotal</h1>
        <p>محافظت از فایل‌های آپلودی با اسکن هم‌زمان ۷۰+ آنتی‌ویروس</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Status Card -->
    <div class="content-card">
        <div class="header"><h5>وضعیت فعلی</h5></div>
        <div class="body">
            <?php if ($isConfigured): ?>
                <p>وضعیت: <span class="status-badge status-active">✅ فعال</span></p>
                <div class="saved-box">
                    <b>API Key:</b> <?= htmlspecialchars(substr($savedKey, 0, 12)) ?>...<?= htmlspecialchars(substr($savedKey, -8)) ?>
                </div>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="test">
                    <button class="btn-vt btn-success-vt"><i class="bi bi-check-circle"></i> تست اتصال</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="reset">
                    <button class="btn-vt btn-danger-vt"><i class="bi bi-trash"></i> حذف</button>
                </form>
            <?php else: ?>
                <p>وضعیت: <span class="status-badge status-inactive">❌ غیرفعال</span></p>
                <p style="font-size:13px;color:#64748b;margin-bottom:0">
                    برای فعال‌سازی، API Key را از سایت VirusTotal بگیرید و در فرم زیر وارد کنید.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instructions -->
    <div class="content-card">
        <div class="header"><h5>📖 راهنمای دریافت API Key (رایگان)</h5></div>
        <div class="body">
            <div class="instruction-list">
                <ol>
                    <li>به سایت VirusTotal بروید: <a href="https://www.virustotal.com/gui/join-us" target="_blank">virustotal.com</a></li>
                    <li>روی <b>Join us</b> کلیک کنید و ثبت‌نام کنید</li>
                    <li>ایمیل خود را تأیید کنید (لینک فعال‌سازی)</li>
                    <li>وارد حساب کاربری خود شوید</li>
                    <li>روی <b>نام کاربری</b> (گوشه بالا راست) کلیک کنید</li>
                    <li>گزینه <b>API Key</b> را انتخاب کنید</li>
                    <li>API Key را کپی کنید و در فرم زیر بچسبانید</li>
                </ol>
                <a href="https://www.virustotal.com/gui/join-us" target="_blank" class="external-link">
                    <i class="bi bi-box-arrow-up-right"></i> ثبت‌نام در VirusTotal
                </a>
                <a href="https://www.virustotal.com/gui/my-apikey" target="_blank" class="external-link">
                    <i class="bi bi-key"></i> دریافت API Key
                </a>
            </div>

            <div class="plan-box">
                <b>📊 پلن رایگان:</b><br>
                ✅ ۵۰۰ اسکن در روز<br>
                ✅ ۴ درخواست در دقیقه<br>
                ✅ اسکن هم‌زمان با ۷۰+ آنتی‌ویروس<br>
                ✅ بدون محدودیت منطقه‌ای — از ایران کار می‌کند<br>
                ✅ بدون نیاز به کارت بانکی
            </div>
        </div>
    </div>

    <!-- Setup Form -->
    <div class="content-card">
        <div class="header"><h5>🔑 تنظیم API Key</h5></div>
        <div class="body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save_key">
                <label style="font-weight:bold;margin-bottom:8px;display:block;font-size:13px">API Key:</label>
                <input type="text" name="api_key" class="token-input" 
                       placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                       value="<?= htmlspecialchars($savedKey) ?>" autocomplete="off">
                <div style="margin-top:15px">
                    <button type="submit" class="btn-vt btn-primary-vt">
                        <i class="bi bi-shield-check"></i> ذخیره و اعتبارسنجی
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

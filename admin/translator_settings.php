<?php
/**
 * PartoCMS - Translation Providers Settings
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/multi_translator.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$message = '';
$messageType = '';

// ==================== ذخیره کلید ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_keys') {
        try {
            $keys = [
                'deepl_api_key'     => trim($_POST['deepl_api_key'] ?? ''),
                'microsoft_api_key' => trim($_POST['microsoft_api_key'] ?? ''),
                'microsoft_region'  => trim($_POST['microsoft_region'] ?? 'global'),
                'yandex_api_key'    => trim($_POST['yandex_api_key'] ?? ''),
            ];

            foreach ($keys as $k => $v) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$k, $v]);
            }
            $message = '✅ تنظیمات ذخیره شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ خطا: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'test_providers') {
        $t = new MultiTranslator($pdo);
        $results = $t->testProviders();
        $okCount = 0;
        $html = '<strong>نتایج تست:</strong><br>';
        foreach ($results as $name => $r) {
            if ($r['ok']) {
                $okCount++;
                $html .= "✅ <strong>$name</strong> → کیفیت: {$r['score']}/100 — " . htmlspecialchars(mb_substr($r['result'], 0, 60)) . " ({$r['ms']}ms)<br>";
            } else {
                $html .= "❌ <strong>$name</strong> → " . htmlspecialchars($r['error']) . " ({$r['ms']}ms)<br>";
            }
        }
        $html .= "<hr><strong>خلاصه: $okCount از " . count($results) . " سرویس کار می‌کند</strong>";
        $message = $html;
        $messageType = $okCount >= 2 ? 'success' : ($okCount === 1 ? 'warning' : 'danger');
    }

    if ($action === 'toggle_auto_translate') {
        try {
            $newVal = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) VALUES ('auto_translate_on_publish', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$newVal]);
            $message = $newVal === '1' ? '✅ ترجمه خودکار فعال شد' : '⛔ ترجمه خودکار غیرفعال شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ خطا: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'clear_stats') {
        $pdo->exec("UPDATE provider_stats SET 
            total_calls=0, success_calls=0, failed_calls=0, 
            total_response_ms=0, avg_quality_score=0");
        $message = '✅ آمار پاک شد';
        $messageType = 'success';
    }
}

// ==================== دریافت مقادیر فعلی ====================
function getKey($pdo, $k) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$k]);
        return $stmt->fetchColumn() ?: '';
    } catch (Throwable $e) { return ''; }
}

$deeplKey     = getKey($pdo, 'deepl_api_key');
$msKey        = getKey($pdo, 'microsoft_api_key');
$msRegion     = getKey($pdo, 'microsoft_region') ?: 'global';
$yandexKey    = getKey($pdo, 'yandex_api_key');

// ==================== آمار Provider ها ====================
$stats = [];
try {
    $stats = $pdo->query("
        SELECT * FROM provider_stats 
        ORDER BY priority ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات ترجمه — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:18px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center}
.card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.card .body{padding:22px}
.provider-row{display:grid;grid-template-columns:180px 1fr 100px 100px;gap:15px;align-items:center;padding:15px;border-bottom:1px solid #f1f5f9}
.provider-row:last-child{border-bottom:none}
.provider-row:hover{background:#f8fafc}
.provider-info .name{font-weight:bold;color:#1e293b;font-size:14px}
.provider-info .desc{font-size:11px;color:#64748b;margin-top:3px}
.provider-status{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold}
.st-ok{background:#d1fae5;color:#065f46}
.st-off{background:#f1f5f9;color:#64748b}
.st-err{background:#fee2e2;color:#991b1b}
.input-key{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:12px;direction:ltr;text-align:left}
.input-key:focus{outline:none;border-color:#0891b2}
.btn-a{padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;margin:4px;display:inline-flex;align-items:center;gap:6px;transition:.2s}
.btn-a:hover{transform:translateY(-1px)}
.btn-primary-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.stats-table{width:100%;border-collapse:collapse;font-size:12px}
.stats-table th{background:#334155;color:#fff;padding:10px;text-align:right}
.stats-table td{padding:10px;border-bottom:1px solid #f1f5f9;font-family:monospace}
.stats-table tr:hover td{background:#f8fafc}
.help-box{background:#ecfeff;border-right:4px solid #0891b2;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}
.help-box a{color:#0891b2;font-weight:bold;text-decoration:none}
.help-box a:hover{text-decoration:underline}
.key-preview{font-family:monospace;font-size:11px;color:#94a3b8;direction:ltr;display:block;margin-top:4px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🌍 تنظیمات سرویس‌های ترجمه</h1>
        <p>مدیریت API Key سرویس‌های ترجمه خودکار</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- راهنما -->
    <div class="card">
        <div class="header"><h5>📖 چطور کار می‌کند؟</h5></div>
        <div class="body">
            <div class="help-box">
                سیستم ترجمه PartoCMS از <strong>چند سرویس به صورت موازی</strong> استفاده می‌کند. 
                هر متن به همه سرویس‌ها فرستاده می‌شود، سپس بهترین کیفیت انتخاب می‌شود.
                <br><br>
                <strong>ترتیب اولویت:</strong> DeepL → Microsoft → Yandex → Google → MyMemory
                <br>
                اگر یک سرویس خطا بدهد، خودکار به بعدی می‌رود.
            </div>
            <p style="font-size:13px;color:#64748b;margin:0">
                💡 <strong>حداقل یک سرویس</strong> کافی است. سرویس‌های رایگان مثل MyMemory و Google بدون کلید کار می‌کنند.
            </p>
        </div>
    </div>

    <!-- فرم کلیدها -->
    <form method="post">
        <input type="hidden" name="action" value="save_keys">
        
        <!-- DeepL -->
        <div class="card">
            <div class="header">
                <h5>🥇 DeepL (کیفیت برتر)</h5>
                <?php if ($deeplKey): ?>
                    <span class="provider-status st-ok"><i class="bi bi-check-circle"></i> تنظیم شده</span>
                <?php else: ?>
                    <span class="provider-status st-off">تنظیم نشده</span>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="help-box">
                    🎁 <strong>رایگان:</strong> ۵۰۰,۰۰۰ کاراکتر در ماه
                    <br>
                    🔗 دریافت API Key: <a href="https://www.deepl.com/pro-api" target="_blank">deepl.com/pro-api</a>
                    <br>
                    📌 ثبت‌نام → انتخاب <strong>DeepL API Free</strong> → کپی Authentication Key (با پسوند <code>:fx</code>)
                </div>
                <label style="font-weight:bold;font-size:13px;display:block;margin-bottom:8px">API Key:</label>
                <input type="text" name="deepl_api_key" class="input-key" 
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx:fx"
                       value="<?= htmlspecialchars($deeplKey) ?>">
                <?php if ($deeplKey): ?>
                    <span class="key-preview">وضعیت: <?= htmlspecialchars(substr($deeplKey, 0, 12)) ?>...<?= htmlspecialchars(substr($deeplKey, -6)) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Microsoft -->
        <div class="card">
            <div class="header">
                <h5>🥈 Microsoft Translator (۲ میلیون کاراکتر/ماه)</h5>
                <?php if ($msKey): ?>
                    <span class="provider-status st-ok"><i class="bi bi-check-circle"></i> تنظیم شده</span>
                <?php else: ?>
                    <span class="provider-status st-off">تنظیم نشده</span>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="help-box">
                    🎁 <strong>رایگان:</strong> ۲ میلیون کاراکتر در ماه
                    <br>
                    🔗 دریافت API Key: <a href="https://azure.microsoft.com/free" target="_blank">azure.microsoft.com/free</a>
                    <br>
                    📌 ثبت‌نام → ساخت Resource از نوع Translator → کپی Key 1 و Region
                </div>
                <label style="font-weight:bold;font-size:13px;display:block;margin-bottom:8px">API Key:</label>
                <input type="text" name="microsoft_api_key" class="input-key"
                       placeholder="32-character-hex-key"
                       value="<?= htmlspecialchars($msKey) ?>">
                <label style="font-weight:bold;font-size:13px;display:block;margin:12px 0 8px">Region:</label>
                <select name="microsoft_region" class="input-key" style="font-family:Tahoma">
                    <?php foreach (['global','westeurope','eastus','westus','northeurope','southeastasia','japaneast'] as $r): ?>
                        <option value="<?= $r ?>" <?= $msRegion === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Yandex -->
        <div class="card">
            <div class="header">
                <h5>🥉 Yandex Translate</h5>
                <?php if ($yandexKey): ?>
                    <span class="provider-status st-ok"><i class="bi bi-check-circle"></i> تنظیم شده</span>
                <?php else: ?>
                    <span class="provider-status st-off">تنظیم نشده</span>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="help-box">
                    🎁 <strong>رایگان:</strong> ۱ میلیون کاراکتر (فقط ۹۰ روز اول)
                    <br>
                    🔗 دریافت API Key: <a href="https://translate.yandex.com/developers/keys" target="_blank">translate.yandex.com/developers/keys</a>
                </div>
                <label style="font-weight:bold;font-size:13px;display:block;margin-bottom:8px">API Key:</label>
                <input type="text" name="yandex_api_key" class="input-key"
                       placeholder="Yandex API key"
                       value="<?= htmlspecialchars($yandexKey) ?>">
            </div>
        </div>

        <!-- دکمه‌های عملیات -->
        <div class="card">
            <div class="body" style="text-align:center">
                <button type="submit" class="btn-a btn-primary-a">
                    <i class="bi bi-save"></i> ذخیره تنظیمات
                </button>
            </div>
        </div>
    </form>

    <!-- Auto Translate Setting -->
    <?php
    $autoTranslateEnabled = false;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_translate_on_publish' LIMIT 1");
        $stmt->execute();
        $autoTranslateEnabled = ($stmt->fetchColumn() === '1');
    } catch (Throwable $e) {}
    ?>
    <div class="card">
        <div class="header">
            <h5>⚡ ترجمه خودکار در انتشار</h5>
            <?php if ($autoTranslateEnabled): ?>
                <span class="provider-status st-ok"><i class="bi bi-check-circle"></i> فعال</span>
            <?php else: ?>
                <span class="provider-status st-off">غیرفعال</span>
            <?php endif; ?>
        </div>
        <div class="body">
            <div class="help-box">
                🎯 وقتی فعال باشه، هر مقاله‌ای که <strong>publish</strong> می‌شه، خودکار به همه زبان‌های فعال ترجمه می‌شه.
                <br>
                ⏱ ترجمه در <strong>پس‌زمینه</strong> اجرا می‌شه — کاربر فوری پاسخ می‌گیره.
                <br>
                📱 نتیجه به <strong>Telegram</strong> فرستاده می‌شه (اگه تنظیم شده باشه).
            </div>

            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_auto_translate">
                <input type="hidden" name="enabled" value="<?= $autoTranslateEnabled ? '0' : '1' ?>">
                <button type="submit" class="btn-a <?= $autoTranslateEnabled ? 'btn-warning-a' : 'btn-success-a' ?>">
                    <?php if ($autoTranslateEnabled): ?>
                        <i class="bi bi-x-circle"></i> غیرفعال کردن
                    <?php else: ?>
                        <i class="bi bi-lightning-charge"></i> فعال کردن
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>
    <!-- تست و آمار -->
    <div class="card">
        <div class="header"><h5>🧪 تست و آمار</h5></div>
        <div class="body">
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="test_providers">
                <button type="submit" class="btn-a btn-success-a">
                    <i class="bi bi-lightning-charge"></i> تست همه سرویس‌ها
                </button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('آمار ریست شود؟')">
                <input type="hidden" name="action" value="clear_stats">
                <button type="submit" class="btn-a btn-secondary-a">
                    <i class="bi bi-arrow-counterclockwise"></i> ریست آمار
                </button>
            </form>
        </div>
    </div>

    <!-- جدول آمار -->
    <?php if (!empty($stats)): ?>
    <div class="card">
        <div class="header"><h5>📊 آمار Provider ها</h5></div>
        <div class="body" style="padding:0">
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>سرویس</th>
                        <th>وضعیت</th>
                        <th>کل</th>
                        <th>موفق</th>
                        <th>خطا</th>
                        <th>میانگین کیفیت</th>
                        <th>میانگین زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['provider']) ?></strong></td>
                        <td>
                            <?php if (!$s['is_active']): ?>
                                <span class="provider-status st-off">غیرفعال</span>
                            <?php elseif ($s['success_calls'] > 0): ?>
                                <span class="provider-status st-ok">فعال</span>
                            <?php else: ?>
                                <span class="provider-status st-off">تست نشده</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($s['total_calls']) ?></td>
                        <td style="color:#10b981"><?= number_format($s['success_calls']) ?></td>
                        <td style="color:#ef4444"><?= number_format($s['failed_calls']) ?></td>
                        <td><?= number_format($s['avg_quality_score'], 1) ?></td>
                        <td>
                            <?php 
                            $avg = $s['success_calls'] > 0 
                                ? round($s['total_response_ms'] / $s['success_calls']) 
                                : 0;
                            echo $avg ? $avg . 'ms' : '—';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- بازگشت -->
    <a href="content_translate.php" class="btn-a btn-secondary-a">
        <i class="bi bi-arrow-right"></i> بازگشت به ترجمه محتوا
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * 🆕 PartoCMS - AI Providers Management
 *
 * صفحه مدیریت ارائه‌دهندگان هوش مصنوعی ابری
 * Tabs: Ollama | Groq | Gemini | OpenRouter | تنظیمات | وضعیت
 *
 * ⚠️ این فایل جدید است — به ai_settings.php دست نزده‌ایم.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();

// ═══ تعریف Tabs ═══
$tabs = [
    'ollama'     => ['icon' => '🖥', 'label' => 'Ollama (محلی)'],
    'groq'       => ['icon' => '🟢', 'label' => 'Groq'],
    'gemini'     => ['icon' => '🔵', 'label' => 'Gemini'],
    'openrouter' => ['icon' => '🟣', 'label' => 'OpenRouter'],
    'settings'   => ['icon' => '⚙️', 'label' => 'تنظیمات'],
    'status'     => ['icon' => '📊', 'label' => 'وضعیت'],
];

// ═══ Tab فعال ═══
$activeTab = $_GET['tab'] ?? 'groq';
if (!isset($tabs[$activeTab])) {
    $activeTab = 'groq';
}

// ═══ لود فایل‌های AI (فقط خواندن — بدون تغییر) ═══
$aiEnvFile    = __DIR__ . '/../includes/AI/AIEnvironment.php';
$aiProxyFile  = __DIR__ . '/../includes/AI/AIProxyManager.php';
$aiGateFile   = __DIR__ . '/../includes/AI/AIGateway.php';

if (file_exists($aiEnvFile))   require_once $aiEnvFile;
if (file_exists($aiProxyFile)) require_once $aiProxyFile;
if (file_exists($aiGateFile))  require_once $aiGateFile;

// ═══ دریافت تنظیمات فعلی ═══
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ai_%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Throwable $e) {
    $settings = [];
}

// ═══ Helper تابع ترجمه ═══
if (!function_exists('__t')) {
    function __t($key, $params = [], $default = '') {
        return $default;
    }
}

// ═══ مسیر sidebar (مطابق الگوی ai_settings.php) ═══
$sidebarFile = __DIR__ . '/includes/sidebar.php';
?>
<!DOCTYPE html>
<html <?= function_exists('__html_attrs') ? __html_attrs() : 'lang="fa" dir="rtl"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارائه‌دهندگان هوش مصنوعی — PartoCMS</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #2563eb;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }

        body {
            font-family: Tahoma, 'Vazirmatn', sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
        }

        /* 🆕 چیدمان با sidebar */
        .main-content {
            margin-right: 260px;
            padding: 20px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-right: 0 !important;
                padding: 70px 15px 15px !important;
            }
        }

        .container-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        /* ═══ Tabs ═══ */
        .tabs-nav {
            display: flex;
            gap: 5px;
            background: #fff;
            padding: 8px;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: var(--gray-700);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            background: var(--gray-100);
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }

        /* ═══ Content ═══ */
        .tab-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            min-height: 400px;
        }

        /* ═══ Provider Card ═══ */
        .provider-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-200);
        }

        .provider-icon {
            font-size: 40px;
        }

        .provider-title {
            flex: 1;
        }

        .provider-title h2 {
            margin: 0 0 5px;
            font-size: 20px;
        }

        .provider-title p {
            margin: 0;
            color: var(--gray-500);
            font-size: 13px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }

        /* ═══ Form ═══ */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-size: 14px;
        }

        .form-group small {
            display: block;
            color: var(--gray-500);
            font-size: 12px;
            margin-top: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-control[dir="ltr"] {
            direction: ltr;
            font-family: monospace;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .input-group .form-control {
            flex: 1;
        }

        /* ═══ Buttons ═══ */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-success {
            background: var(--success);
            color: #fff;
        }

        .btn-warning {
            background: var(--warning);
            color: #fff;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ═══ Info Box ═══ */
        .info-box {
            background: #eff6ff;
            border-right: 4px solid var(--primary);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box h4 {
            margin: 0 0 10px;
            color: var(--primary);
            font-size: 15px;
        }

        .info-box ol {
            margin: 0;
            padding-right: 20px;
            line-height: 2;
            font-size: 13.5px;
            color: var(--gray-700);
        }

        .info-box a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .info-box a:hover {
            text-decoration: underline;
        }

        /* ═══ Limits Grid ═══ */
        .limits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .limit-item {
            background: var(--gray-50);
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .limit-item .label {
            font-size: 11px;
            color: var(--gray-500);
            margin-bottom: 4px;
        }

        .limit-item .value {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* ═══ Alerts ═══ */
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-right: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-right: 4px solid var(--danger);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-right: 4px solid var(--primary);
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-right: 4px solid var(--warning);
        }

        /* ═══ Back Link ═══ */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .back-link:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .tab-btn {
                padding: 10px 14px;
                font-size: 13px;
            }
            .tab-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<?php require_once $sidebarFile; ?>
<div class="main-content">

<div class="container-main">

    <!-- Back Link -->
    <a href="index.php" class="back-link">
        <i class="bi bi-arrow-right"></i>
        بازگشت به داشبورد
    </a>

    <!-- Header -->
    <div class="page-header">
        <h1>
            <span>☁️</span>
            <span>ارائه‌دهندگان هوش مصنوعی ابری</span>
        </h1>
        <p>
            مدیریت API Keyها، ترتیب Fallback و Proxy برای دسترسی به سرویس‌های ابری.
            اگر Ollama کند یا خطا داد، سیستم خودکار به این‌ها سوئیچ می‌کند.
        </p>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-nav">
        <?php foreach ($tabs as $slug => $info): ?>
            <a href="?tab=<?= $slug ?>"
               class="tab-btn <?= $activeTab === $slug ? 'active' : '' ?>">
                <span><?= $info['icon'] ?></span>
                <span><?= htmlspecialchars($info['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php
        $tabFile = __DIR__ . '/ai_providers/includes/tab-' . $activeTab . '.php';

        if (file_exists($tabFile)) {
            include $tabFile;
        } else {
            echo '<div class="alert alert-warning">';
            echo '<i class="bi bi-exclamation-triangle"></i> ';
            echo 'فایل تب <code>' . htmlspecialchars($activeTab) . '</code> پیدا نشد.';
            echo '</div>';
        }
        ?>
    </div>

</div>

</div><!-- /.main-content -->

<script>
// ═══ Helper Functions ═══
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

function showAlert(type, message) {
    // 🆕 پشتیبانی از ۴ نوع: success, error, info, warning
    const icons = {
        success: 'check-circle-fill',
        error:   'x-circle-fill',
        info:    'info-circle-fill',
        warning: 'exclamation-triangle-fill',
    };
    const icon = icons[type] || 'info-circle';

    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.style.cssText = 'padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; gap: 10px;';
    
    // رنگ‌بندی
    const colors = {
        success: 'background:#dcfce7; color:#166534; border-right:4px solid #16a34a;',
        error:   'background:#fee2e2; color:#991b1b; border-right:4px solid #dc2626;',
        info:    'background:#dbeafe; color:#1e40af; border-right:4px solid #2563eb;',
        warning: 'background:#fef3c7; color:#92400e; border-right:4px solid #f59e0b;',
    };
    alertDiv.style.cssText += ' ' + (colors[type] || colors.info);
    
    alertDiv.innerHTML = '<i class="bi bi-' + icon + '"></i> <span>' + message + '</span>';

    // 🆕 پیدا کردن container
    let container = document.querySelector('.tab-content') 
                 || document.querySelector('.main-content') 
                 || document.querySelector('.container-main')
                 || document.body;
    
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // اسکرول به بالا
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(() => {
            alertDiv.style.transition = 'opacity 0.3s';
            alertDiv.style.opacity = '0';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    } else {
        // fallback: alert مرورگر
        alert('[' + type + '] ' + message);
    }
}

// ═══ Save Provider Settings ═══
function saveProviderSettings(provider, fields) {
    const formData = new FormData();
    formData.append('provider', provider);

    Object.keys(fields).forEach(key => {
        const el = document.getElementById(fields[key]);
        if (el) formData.append(key, el.value);
    });

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', data.message || 'ذخیره شد');
        } else {
            showAlert('error', data.error || 'خطا در ذخیره');
        }
    })
    .catch(err => {
        showAlert('error', 'خطای شبکه: ' + err.message);
    });
}

// ═══ Test Connection ═══
// 🆕 پشتیبانی از دو روش فراخوانی:
//   testProvider('openrouter')           ← روش قدیمی
//   testProvider(event, 'openrouter')    ← روش جدید
function testProvider(arg1, arg2) {
    let event = null;
    let provider = '';

    // تشخیص آرگومان‌ها
    if (typeof arg1 === 'string') {
        provider = arg1;
    } else if (arg1 && typeof arg1 === 'object') {
        event = arg1;
        provider = arg2 || '';
    }

    if (!provider) {
        showAlert('error', '❌ provider نامعتبر');
        return;
    }

    // پیدا کردن button
    const btn = event?.target?.closest?.('button') 
              || document.querySelector(`[onclick*="'${provider}'"]`);

    const original = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> تست...';
    }

    // نمایش فوری وضعیت
    showAlert('info', '⏳ در حال تست ' + provider + '...');

    // مسیر کامل
    const baseUrl = window.location.origin;
    const url = baseUrl + '/ajax/ai_provider_test.php?provider=' + encodeURIComponent(provider);

    console.log('[testProvider] URL:', url);

    fetch(url)
    .then(r => {
        console.log('[testProvider] Status:', r.status);
        if (!r.ok) {
            throw new Error('HTTP ' + r.status);
        }
        return r.json();
    })
    .then(data => {
        console.log('[testProvider] Data:', data);
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'اتصال موفق'));
        } else {
            showAlert('error', '❌ ' + (data.error || 'اتصال ناموفق'));
        }
    })
    .catch(err => {
        console.error('[testProvider] Error:', err);
        showAlert('error', 'خطا: ' + err.message);
    })
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
}
</script>

</body>
</html>

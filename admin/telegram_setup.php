<?php
/**
 * PartoCMS - راه‌اندازی خودکار ربات تلگرام
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

// ==================== توابع کمکی تنظیمات ====================
function detectSettingsColumns($pdo) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'Field');
        // حالت‌های رایج
        if (in_array('setting_key', $names) && in_array('setting_value', $names)) {
            return ['key' => 'setting_key', 'value' => 'setting_value'];
        }
        if (in_array('name', $names) && in_array('value', $names)) {
            return ['key' => 'name', 'value' => 'value'];
        }
        if (in_array('key', $names) && in_array('value', $names)) {
            return ['key' => 'key', 'value' => 'value'];
        }
        return null;
    } catch (Throwable $e) { return null; }
}

function getSettingAuto($pdo, $key, $default = '') {
    $cols = detectSettingsColumns($pdo);
    if (!$cols) return $default;
    try {
        $st = $pdo->prepare("SELECT `{$cols['value']}` FROM settings WHERE `{$cols['key']}` = :k LIMIT 1");
        $st->execute([':k' => $key]);
        $v = $st->fetchColumn();
        return ($v === false) ? $default : $v;
    } catch (Throwable $e) { return $default; }
}

function setSettingAuto($pdo, $key, $value) {
    $cols = detectSettingsColumns($pdo);
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

// ==================== توابع Telegram API ====================
function tgApi($method, $params = [], $token = null) {
    if (!$token) return ['ok' => false, 'error' => 'توکن تنظیم نشده'];
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => !empty($params),
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok' => false, 'error' => 'خطای شبکه: ' . $err];
    return json_decode($res, true) ?: ['ok' => false, 'error' => 'پاسخ نامعتبر'];
}

// ==================== پردازش فرم ====================
$message = '';
$messageType = '';
$step = $_GET['step'] ?? 1;

// وضعیت ذخیره‌شده
$savedToken  = getSettingAuto($pdo, 'telegram_bot_token', '');
$savedChatId = getSettingAuto($pdo, 'telegram_chat_id', '');

// توکن موقت در سشن (تا زمان تأیید نهایی)
$tempToken = $_SESSION['tg_temp_token'] ?? '';
$tempBotInfo = $_SESSION['tg_temp_bot'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'verify_token':
            $token = trim($_POST['bot_token'] ?? '');
            if (!$token) {
                $message = 'لطفاً توکن را وارد کنید';
                $messageType = 'danger';
                break;
            }
            $result = tgApi('getMe', [], $token);
            if (empty($result['ok'])) {
                $message = '❌ توکن نامعتبر: ' . ($result['description'] ?? $result['error'] ?? 'نامشخص');
                $messageType = 'danger';
            } else {
                $_SESSION['tg_temp_token'] = $token;
                $_SESSION['tg_temp_bot'] = [
                    'id' => $result['result']['id'],
                    'first_name' => $result['result']['first_name'],
                    'username' => $result['result']['username'] ?? '',
                ];
                $tempToken = $token;
                $tempBotInfo = $_SESSION['tg_temp_bot'];
                $message = '✅ توکن معتبر است — ربات: ' . $tempBotInfo['first_name'] . ' (@' . $tempBotInfo['username'] . ')';
                $messageType = 'success';
                $step = 2;
            }
            break;

        case 'fetch_chat_id':
            $token = $_SESSION['tg_temp_token'] ?? '';
            if (!$token) {
                $message = 'اول توکن را وارد کنید';
                $messageType = 'danger';
                $step = 1;
                break;
            }
            $result = tgApi('getUpdates', ['limit' => 10, 'timeout' => 0], $token);
            if (empty($result['ok'])) {
                $message = '❌ خطا در دریافت: ' . ($result['description'] ?? 'نامشخص');
                $messageType = 'danger';
            } else {
                $updates = $result['result'] ?? [];
                if (empty($updates)) {
                    $message = '⚠️ هنوز پیامی از شما به ربات نرسیده. در تلگرام ربات را باز کنید و دکمه Start را بزنید، سپس دوباره این دکمه را بزنید.';
                    $messageType = 'warning';
                } else {
                    // آخرین چت را انتخاب کن
                    $chatId = null;
                    $chatName = '';
                    $chatType = '';
                    foreach (array_reverse($updates) as $u) {
                        $chat = $u['message']['chat'] ?? $u['callback_query']['message']['chat'] ?? null;
                        if ($chat && isset($chat['id'])) {
                            $chatId = $chat['id'];
                            $chatName = $chat['first_name'] ?? $chat['title'] ?? $chat['username'] ?? 'ناشناس';
                            $chatType = $chat['type'] ?? 'private';
                            break;
                        }
                    }
                    if ($chatId) {
                        $_SESSION['tg_temp_chat_id'] = $chatId;
                        $_SESSION['tg_temp_chat_name'] = $chatName;
                        $_SESSION['tg_temp_chat_type'] = $chatType;
                        $message = "✅ Chat ID پیدا شد: <b>{$chatId}</b> ({$chatName})";
                        $messageType = 'success';
                        $step = 3;
                    } else {
                        $message = '⚠️ پیام‌های دریافتی قابل پردازش نبودند. مطمئن شوید به ربات پیام داده‌اید.';
                        $messageType = 'warning';
                    }
                }
            }
            break;

        case 'save':
            $token = $_SESSION['tg_temp_token'] ?? '';
            $chatId = $_SESSION['tg_temp_chat_id'] ?? '';
            if (!$token || !$chatId) {
                $message = 'اطلاعات ناقص است. مراحل را دوباره انجام دهید.';
                $messageType = 'danger';
            } else {
                $ok1 = setSettingAuto($pdo, 'telegram_bot_token', $token);
                $ok2 = setSettingAuto($pdo, 'telegram_chat_id', $chatId);
                if ($ok1 && $ok2) {
                    $message = '✅ تنظیمات با موفقیت ذخیره شد';
                    $messageType = 'success';
                    $savedToken = $token;
                    $savedChatId = $chatId;
                    unset($_SESSION['tg_temp_token'], $_SESSION['tg_temp_bot'],
                          $_SESSION['tg_temp_chat_id'], $_SESSION['tg_temp_chat_name'],
                          $_SESSION['tg_temp_chat_type']);
                    $step = 4;
                } else {
                    $message = '❌ خطا در ذخیره‌سازی. ساختار جدول settings را بررسی کنید.';
                    $messageType = 'danger';
                }
            }
            break;

        case 'test':
            $token = getSettingAuto($pdo, 'telegram_bot_token', '');
            $chatId = getSettingAuto($pdo, 'telegram_chat_id', '');
            if (!$token || !$chatId) {
                $message = 'ابتدا تنظیمات را ذخیره کنید';
                $messageType = 'danger';
            } else {
                $text = "✅ <b>PartoCMS</b>\nپیام تست با موفقیت ارسال شد.\n🕐 " . date('Y-m-d H:i:s');
                $result = tgApi('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ], $token);
                if (!empty($result['ok'])) {
                    $message = '✅ پیام تست با موفقیت ارسال شد — تلگرام را چک کنید';
                    $messageType = 'success';
                } else {
                    $message = '❌ خطا: ' . ($result['description'] ?? 'نامشخص');
                    $messageType = 'danger';
                }
            }
            break;

        case 'reset':
            unset($_SESSION['tg_temp_token'], $_SESSION['tg_temp_bot'],
                  $_SESSION['tg_temp_chat_id'], $_SESSION['tg_temp_chat_name'],
                  $_SESSION['tg_temp_chat_type']);
            setSettingAuto($pdo, 'telegram_bot_token', '');
            setSettingAuto($pdo, 'telegram_chat_id', '');
            $savedToken = '';
            $savedChatId = '';
            $tempToken = '';
            $tempBotInfo = null;
            $message = '♻️ تنظیمات پاک شد';
            $messageType = 'info';
            $step = 1;
            break;
    }
}

$tempChatId = $_SESSION['tg_temp_chat_id'] ?? '';
$tempChatName = $_SESSION['tg_temp_chat_name'] ?? '';
$tempBotInfo = $_SESSION['tg_temp_bot'] ?? $tempBotInfo;
$tempToken = $_SESSION['tg_temp_token'] ?? $tempToken;

$csrfToken = class_exists('SecurityValidator')
    ? SecurityValidator::generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)));
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = $csrfToken;

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>راه‌اندازی تلگرام — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0088cc,#006699);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.content-card .body{padding:22px}
.step-indicator{display:flex;gap:10px;margin-bottom:25px;flex-wrap:wrap}
.step-item{flex:1;min-width:130px;padding:12px 15px;background:#fff;border-radius:10px;border-right:4px solid #cbd5e1;font-size:13px;text-align:center;color:#64748b}
.step-item.active{border-right-color:#0088cc;color:#0f172a;font-weight:bold;background:#e0f2fe}
.step-item.done{border-right-color:#10b981;color:#065f46;background:#ecfdf5}
.step-item .num{display:inline-block;width:24px;height:24px;line-height:24px;border-radius:50%;background:#cbd5e1;color:#fff;font-size:12px;margin-left:6px}
.step-item.active .num{background:#0088cc}
.step-item.done .num{background:#10b981}
.instruction-list{background:#f0f9ff;border-right:4px solid #0088cc;padding:15px 20px;border-radius:8px;margin-bottom:15px}
.instruction-list ol{margin:0;padding-right:20px;font-size:13px;line-height:2}
.instruction-list code{background:#1e293b;color:#fbbf24;padding:2px 8px;border-radius:5px;font-size:12px;direction:ltr;display:inline-block}
.instruction-list a{color:#0088cc;font-weight:bold}
.btn-tg{padding:12px 22px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px;transition:.2s}
.btn-tg:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
.btn-primary-tg{background:linear-gradient(135deg,#0088cc,#006699);color:#fff}
.btn-success-tg{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-warning-tg{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.btn-danger-tg{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
.btn-secondary-tg{background:linear-gradient(135deg,#64748b,#334155);color:#fff}
.token-input{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:10px;font-family:monospace;font-size:13px;direction:ltr;text-align:left;transition:.2s}
.token-input:focus{outline:none;border-color:#0088cc;box-shadow:0 0 0 3px rgba(0,136,204,.15)}
.bot-info-box{background:#ecfdf5;border:2px solid #10b981;border-radius:10px;padding:15px 20px;margin-top:15px;font-size:14px}
.chat-info-box{background:#e0f2fe;border:2px solid #0088cc;border-radius:10px;padding:15px 20px;margin-top:15px;font-size:14px}
.saved-box{background:#f0fdf4;border:2px solid #10b981;border-radius:10px;padding:15px 20px;margin-bottom:15px;font-size:13px;font-family:monospace;direction:ltr;text-align:left;word-break:break-all}
.external-link{display:inline-flex;align-items:center;gap:5px;background:#fff;padding:6px 12px;border-radius:8px;color:#0088cc;text-decoration:none;font-size:13px;border:1px solid #e2e8f0;margin:5px 5px 5px 0}
.external-link:hover{background:#0088cc;color:#fff}
</style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🤖 راه‌اندازی ربات تلگرام</h1>
        <p>در ۴ مرحله ساده، هشدارهای امنیتی را به تلگرام خود متصل کنید</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message /* HTML مجاز است */ ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ==================== نشانگر مراحل ==================== -->
    <div class="step-indicator">
        <div class="step-item <?= $step==1 ? 'active' : ($step>1 ? 'done' : '') ?>">
            <span class="num">1</span> دریافت توکن
        </div>
        <div class="step-item <?= $step==2 ? 'active' : ($step>2 ? 'done' : '') ?>">
            <span class="num">2</span> تأیید توکن
        </div>
        <div class="step-item <?= $step==3 ? 'active' : ($step>3 ? 'done' : '') ?>">
            <span class="num">3</span> دریافت Chat ID
        </div>
        <div class="step-item <?= $step==4 ? 'active' : '' ?>">
            <span class="num">4</span> تست نهایی
        </div>
    </div>

    <!-- ==================== نمایش تنظیمات ذخیره‌شده ==================== -->
    <?php if ($savedToken && $savedChatId): ?>
    <div class="content-card">
        <div class="header"><h5>✅ تنظیمات فعلی</h5></div>
        <div class="body">
            <div class="saved-box">
                <b>Bot Token:</b> <?= htmlspecialchars(substr($savedToken, 0, 15)) ?>...<?= htmlspecialchars(substr($savedToken, -10)) ?><br>
                <b>Chat ID:</b> <?= htmlspecialchars($savedChatId) ?>
            </div>
            <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="test">
                <button class="btn-tg btn-success-tg"><i class="bi bi-send"></i> تست ارسال پیام</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('تمام تنظیمات پاک شوند؟')">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="reset">
                <button class="btn-tg btn-danger-tg"><i class="bi bi-arrow-counterclockwise"></i> شروع مجدد</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== مرحله ۱: دریافت توکن ==================== -->
    <div class="content-card">
        <div class="header"><h5>📱 مرحله ۱: ساخت ربات و دریافت توکن</h5></div>
        <div class="body">
            <div class="instruction-list">
                <ol>
                    <li>در تلگرام به <a href="https://t.me/BotFather" target="_blank">@BotFather</a> پیام دهید</li>
                    <li>دستور <code>/newbot</code> را بفرستید</li>
                    <li>یک <b>نام</b> برای ربات (مثلاً <code>PartoCMS Security</code>) وارد کنید</li>
                    <li>یک <b>یوزرنیم</b> که به <code>bot</code> ختم می‌شود (مثلاً <code>PartoCMS_Security_Bot</code>) وارد کنید</li>
                    <li>BotFather توکنی شبیه این می‌دهد:<br>
                        <code>7123456789:AAHfGhJkLmNoPqRsTuVwXyZ1234567890</code>
                    </li>
                    <li>توکن را کپی کنید و در کادر زیر بچسبانید</li>
                </ol>
                <a href="https://t.me/BotFather" target="_blank" class="external-link">
                    <i class="bi bi-box-arrow-up-right"></i> باز کردن BotFather در تلگرام
                </a>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="verify_token">
                <label style="font-weight:bold;margin-bottom:8px;display:block;font-size:13px">توکن ربات:</label>
                <input type="text" name="bot_token" class="token-input" 
                       placeholder="7123456789:AAHfGhJkLmNoPqRsTuVwXyZ1234567890"
                       value="<?= htmlspecialchars($tempToken) ?>" autocomplete="off">
                <div style="margin-top:15px">
                    <button type="submit" class="btn-tg btn-primary-tg">
                        <i class="bi bi-shield-check"></i> تأیید توکن
                    </button>
                </div>
            </form>

            <?php if ($tempBotInfo): ?>
            <div class="bot-info-box">
                <i class="bi bi-check-circle-fill text-success"></i>
                <b>ربات تأیید شد:</b> <?= htmlspecialchars($tempBotInfo['first_name']) ?>
                (<code>@<?= htmlspecialchars($tempBotInfo['username']) ?></code>)
                • ID: <?= $tempBotInfo['id'] ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== مرحله ۲: دریافت Chat ID ==================== -->
    <?php if ($tempToken): ?>
    <div class="content-card">
        <div class="header"><h5>💬 مرحله ۲: دریافت Chat ID</h5></div>
        <div class="body">
            <div class="instruction-list">
                <ol>
                    <li>در تلگرام، ربات ساخته‌شده را جستجو کنید:
                        <code>@<?= htmlspecialchars($tempBotInfo['username'] ?? '') ?></code>
                    </li>
                    <li>وارد چت شوید و دکمه <b>Start</b> یا <code>/start</code> را بزنید</li>
                    <li>یک پیام دلخواه بفرستید (مثلاً <code>سلام</code>)</li>
                    <li>سپس روی دکمه زیر کلیک کنید</li>
                </ol>
                <?php if ($tempBotInfo && !empty($tempBotInfo['username'])): ?>
                <a href="https://t.me/<?= htmlspecialchars($tempBotInfo['username']) ?>" target="_blank" class="external-link">
                    <i class="bi bi-telegram"></i> باز کردن ربات در تلگرام
                </a>
                <?php endif; ?>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="fetch_chat_id">
                <button type="submit" class="btn-tg btn-warning-tg">
                    <i class="bi bi-search"></i> دریافت خودکار Chat ID
                </button>
            </form>

            <?php if ($tempChatId): ?>
            <div class="chat-info-box">
                <i class="bi bi-check-circle-fill text-primary"></i>
                <b>Chat ID پیدا شد:</b> <code><?= htmlspecialchars($tempChatId) ?></code>
                <?php if ($tempChatName): ?> • <?= htmlspecialchars($tempChatName) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== مرحله ۳: ذخیره ==================== -->
    <?php if ($tempToken && $tempChatId): ?>
    <div class="content-card">
        <div class="header"><h5>💾 مرحله ۳: ذخیره در دیتابیس</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#475569;margin-bottom:15px">
                توکن و Chat ID در جدول <code>settings</code> ذخیره می‌شوند.
            </p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save">
                <button type="submit" class="btn-tg btn-success-tg">
                    <i class="bi bi-save"></i> ذخیره تنظیمات
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== مرحله ۴: تست ==================== -->
    <?php if ($savedToken && $savedChatId): ?>
    <div class="content-card">
        <div class="header"><h5>🚀 مرحله ۴: تست نهایی</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#475569;margin-bottom:15px">
                برای اطمینان از کارکرد صحیح، یک پیام تست ارسال کنید.
            </p>
            <form method="post" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="test">
                <button type="submit" class="btn-tg btn-primary-tg">
                    <i class="bi bi-send-check"></i> ارسال پیام تست
                </button>
            </form>
            <a href="<?= (defined('ADMIN_URL') ? ADMIN_URL : '.') ?>/security_dashboard.php" class="btn-tg btn-secondary-tg" style="text-decoration:none">
                <i class="bi bi-arrow-left"></i> بازگشت به داشبورد
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

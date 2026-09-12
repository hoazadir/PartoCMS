<?php
/**
 * PartoCMS - Login Page (with Rate Limiting + 2FA)
 */
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// ==================== LOAD SECURITY MODULES ====================
$rlPath = __DIR__ . '/includes/rate_limiter.php';
if (file_exists($rlPath)) require_once $rlPath;

$tgPath = __DIR__ . '/includes/telegram_notifier.php';
if (file_exists($tgPath)) require_once $tgPath;

$totpPath = __DIR__ . '/includes/totp.php';
if (file_exists($totpPath)) require_once $totpPath;

// ==================== SECURITY HEADERS ====================
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");

    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "img-src 'self' data: https:; "
         . "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'; "
         . "form-action 'self'; "
         . "base-uri 'self'; "
         . "object-src 'none';";
    header("Content-Security-Policy: " . $csp);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// ==================== INIT ====================
$pdo = getDB();
$limiter = null;
$telegram = null;

if (class_exists('RateLimiter')) {
    if (class_exists('TelegramNotifier')) {
        $tgToken  = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
        $tgChatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
        if ($tgToken && $tgChatId) {
            $telegram = new TelegramNotifier($tgToken, $tgChatId);
        }
    }
    $limiter = new RateLimiter($pdo, 5, 15, $telegram);
}

$error = '';
$blockedInfo = null;

// ==================== RATE LIMIT CHECK ====================
if ($limiter) {
    try { $blockedInfo = $limiter->check(); } catch (Throwable $e) { $blockedInfo = null; }
}

if ($blockedInfo && !empty($blockedInfo['blocked'])) {
    $mins = $blockedInfo['remaining_min'];
    $error = '⛔ دسترسی شما موقتاً مسدود شده است. لطفاً ' . $mins . ' دقیقه دیگر تلاش کنید.';
}

// ==================== 2FA STATE ====================
// اگر کاربر نام کاربری و رمز را درست وارد کرد ولی 2FA فعال بود،
// اطلاعات کاربر در session موقت ذخیره می‌شود تا کد را وارد کند
$pending2FA = $_SESSION['2fa_pending_user'] ?? null;

// ==================== HANDLE LOGIN ====================
$action = $_POST['action'] ?? 'login';

// ---------- مرحله ۱: نام کاربری و رمز ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login'
    && !($blockedInfo && $blockedInfo['blocked'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'نام کاربری و رمز عبور الزامی است';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name, r.slug as role_slug
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE u.username = ? AND u.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ✅ رمز عبور درست
                // بررسی 2FA
                $stmt2 = $pdo->prepare("SELECT * FROM user_2fa WHERE user_id = ? AND enabled = 1 LIMIT 1");
                $stmt2->execute([$user['id']]);
                $twoFa = $stmt2->fetch();

                if ($twoFa && !empty($twoFa['secret']) && class_exists('TOTP')) {
                    // 🔐 نیاز به کد 2FA
                    $_SESSION['2fa_pending_user'] = [
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'] ?? '',
                        'role_id' => $user['role_id'],
                        'role_slug' => $user['role_slug'],
                        'role_name' => $user['role_name'],
                        'attempted_at' => time(),
                    ];
                    $pending2FA = $_SESSION['2fa_pending_user'];

                    // ثبت لاگ
                    try {
                        $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id) VALUES ('login_fail', ?, ?, ?)")
                            ->execute(['2FA required for: ' . $username, $_SERVER['REMOTE_ADDR'] ?? '?', $user['id']]);
                    } catch (Throwable $e) {}

                    if ($limiter) {
                        try { $limiter->record($username, false); } catch (Throwable $e) {}
                    }
                } else {
                    // بدون 2FA → ورود مستقیم
                    loginUser($user);
                    header('Location: index.php');
                    exit;
                }
            } else {
                // ❌ رمز اشتباه
                if ($limiter) {
                    try { $limiter->record($username, false); } catch (Throwable $e) {}
                }

                if ($limiter) {
                    $afterCheck = $limiter->check();
                    if ($afterCheck && !empty($afterCheck['blocked'])) {
                        $error = '⛔ تعداد تلاش‌های ناموفق بیش از حد مجاز. دسترسی شما برای ' . $afterCheck['remaining_min'] . ' دقیقه مسدود شد.';
                    } else {
                        $remaining = 5;
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                            $stmt->execute([':ip' => RateLimiter::getClientIp()]);
                            $fails = (int) $stmt->fetchColumn();
                            $remaining = max(0, 5 - $fails);
                        } catch (Throwable $e) {}
                        $error = 'نام کاربری یا رمز عبور اشتباه است. ' . $remaining . ' تلاش دیگر باقی مانده.';
                    }
                } else {
                    $error = 'نام کاربری یا رمز عبور اشتباه است';
                }
            }
        } catch (PDOException $e) {
            $error = 'خطا در اتصال به دیتابیس';
        }
    }
}

// ---------- مرحله ۲: تأیید کد 2FA ----------
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'verify_2fa' && $pending2FA) {

    // چک انقضا (5 دقیقه)
    if ((time() - ($pending2FA['attempted_at'] ?? 0)) > 300) {
        unset($_SESSION['2fa_pending_user']);
        $pending2FA = null;
        $error = '⏰ زمان تأیید 2FA منقضی شد. لطفاً دوباره وارد شوید.';
    } else {
        $code = trim($_POST['code'] ?? '');

        // گرفتن Secret کاربر
        $stmt = $pdo->prepare("SELECT secret FROM user_2fa WHERE user_id = ? AND enabled = 1 LIMIT 1");
        $stmt->execute([$pending2FA['user_id']]);
        $secret = $stmt->fetchColumn();

        $verified = false;
        $method = '';

        if ($secret && class_exists('TOTP')) {
            // تلاش ۱: کد TOTP ۶ رقمی
            if (preg_match('/^\d{6}$/', $code)) {
                if (TOTP::verify($secret, $code)) {
                    $verified = true;
                    $method = 'totp';
                }
            }
            // تلاش ۲: کد پشتیبان (10 حرفی)
            elseif (preg_match('/^[A-F0-9]{10}$/i', $code)) {
                $code = strtoupper($code);
                $stmt = $pdo->prepare("SELECT id, code FROM user_2fa_backup WHERE user_id = ? AND used = 0");
                $stmt->execute([$pending2FA['user_id']]);
                $backups = $stmt->fetchAll();

                foreach ($backups as $b) {
                    if (password_verify($code, $b['code'])) {
                        // استفاده از کد پشتیبان
                        $pdo->prepare("UPDATE user_2fa_backup SET used = 1, used_at = NOW() WHERE id = ?")->execute([$b['id']]);
                        $verified = true;
                        $method = 'backup';
                        break;
                    }
                }
            }
        }

        if ($verified) {
            // ✅ کد درست → تکمیل ورود
            $user = [
                'id' => $pending2FA['user_id'],
                'username' => $pending2FA['username'],
                'email' => $pending2FA['email'] ?? '',
                'role_id' => $pending2FA['role_id'],
                'role_slug' => $pending2FA['role_slug'],
                'role_name' => $pending2FA['role_name'],
            ];

            loginUser($user);

            // به‌روزرسانی last_used_at
            try {
                $pdo->prepare("UPDATE user_2fa SET last_used_at = NOW() WHERE user_id = ?")
                    ->execute([$pending2FA['user_id']]);
            } catch (Throwable $e) {}

            // پاک کردن session موقت
            unset($_SESSION['2fa_pending_user']);

            // اگر با کد پشتیبان وارد شد، هشدار بفرست
            if ($method === 'backup' && $telegram) {
                try {
                    $msg = "⚠️ <b>2FA Backup Code Used</b>\n";
                    $msg .= "👤 User: " . htmlspecialchars($pending2FA['username']) . "\n";
                    $msg .= "🌐 IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";
                    $msg .= "🕐 " . date('Y-m-d H:i:s');
                    $telegram->sendMessage($msg);
                } catch (Throwable $e) {}
            }

            if ($limiter) {
                try { $limiter->record($pending2FA['username'], true); } catch (Throwable $e) {}
            }

            header('Location: index.php');
            exit;
        } else {
            // ❌ کد اشتباه
            $pending2FA['attempted_at'] = time();
            $_SESSION['2fa_pending_user'] = $pending2FA;

            // شمارش تلاش‌های ناموفق 2FA
            $attempts = ($pending2FA['failed_attempts'] ?? 0) + 1;
            $_SESSION['2fa_pending_user']['failed_attempts'] = $attempts;

            if ($attempts >= 5) {
                unset($_SESSION['2fa_pending_user']);
                $pending2FA = null;
                $error = '⛔ تعداد تلاش‌های 2FA بیش از حد مجاز. لطفاً دوباره وارد شوید.';

                if ($limiter) {
                    try { $limiter->record($pending2FA['username'] ?? 'unknown', false); } catch (Throwable $e) {}
                }
            } else {
                $remaining = 5 - $attempts;
                $error = '❌ کد نامعتبر است. ' . $remaining . ' تلاش دیگر باقی مانده.';
            }

            // ثبت لاگ
            try {
                $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id) VALUES ('login_fail', ?, ?, ?)")
                    ->execute(['2FA failed code for: ' . $pending2FA['username'], $_SERVER['REMOTE_ADDR'] ?? '?', $pending2FA['user_id']]);
            } catch (Throwable $e) {}

            // هشدار تلگرام
            if ($telegram && $attempts >= 3) {
                try {
                    $msg = "🚨 <b>Failed 2FA Attempts</b>\n";
                    $msg .= "👤 User: " . htmlspecialchars($pending2FA['username']) . "\n";
                    $msg .= "🌐 IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";
                    $msg .= "🔢 Attempts: {$attempts}\n";
                    $msg .= "🕐 " . date('Y-m-d H:i:s');
                    $telegram->sendMessage($msg);
                } catch (Throwable $e) {}
            }
        }
    }
}

// ---------- لغو 2FA ----------
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel_2fa') {
    unset($_SESSION['2fa_pending_user']);
    $pending2FA = null;
    $error = 'ورود لغو شد. لطفاً دوباره تلاش کنید.';
}

// ==================== HELPER: LOGIN USER ====================
function loginUser($user) {
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['email']     = $user['email'] ?? '';
    $_SESSION['role_id']   = $user['role_id'];
    $_SESSION['role']      = $user['role_slug'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['role_slug'] = $user['role_slug'];
    $_SESSION['last_activity'] = time();
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به پنل مدیریت | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
        }
        .login-box {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
        }
        .login-box .logo { text-align: center; font-size: 60px; margin-bottom: 15px; }
        .login-box h1 { color: #2c3e50; margin-bottom: 8px; font-size: 24px; text-align: center; }
        .login-box .subtitle { color: #7f8c8d; margin-bottom: 30px; font-size: 14px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #34495e; font-size: 14px; font-weight: bold; }
        .form-group input {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-family: Tahoma, sans-serif; font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #667eea; outline: none; }
        .form-group input.code-input {
            text-align: center;
            letter-spacing: 10px;
            font-size: 24px;
            font-family: monospace;
            padding: 15px;
            direction: ltr;
        }
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border: none; border-radius: 8px;
            font-size: 16px; font-family: Tahoma, sans-serif;
            font-weight: bold; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .btn-login:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-secondary {
            width: 100%; padding: 12px;
            background: #e2e8f0; color: #475569; border: none; border-radius: 8px;
            font-size: 14px; font-family: Tahoma, sans-serif;
            font-weight: bold; cursor: pointer;
            margin-top: 10px;
        }
        .btn-secondary:hover { background: #cbd5e1; }
        .error {
            background: #f8d7da; color: #721c24; padding: 12px 15px;
            border-radius: 8px; margin-bottom: 20px; font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        .error.blocked {
            background: #fee2e2; color: #991b1b;
            border-color: #fca5a5;
        }
        .info-2fa {
            background: #dbeafe; color: #1e40af;
            padding: 15px; border-radius: 10px;
            font-size: 13px; margin-bottom: 20px;
            border: 1px solid #93c5fd;
            text-align: center;
        }
        .info-2fa .icon {
            font-size: 40px;
            display: block;
            margin-bottom: 8px;
        }
        .hint {
            margin-top: 20px; padding: 12px; background: #e7f3ff;
            border: 1px solid #b3d9ff; border-radius: 8px;
            color: #0066cc; font-size: 12px; text-align: center;
        }
        .lock-info {
            background: #fef3c7; color: #92400e;
            padding: 10px 15px; border-radius: 8px;
            font-size: 13px; margin-bottom: 15px;
            border: 1px solid #fcd34d;
            display: flex; align-items: center; gap: 8px;
        }
        .countdown {
            font-weight: bold;
            font-family: monospace;
        }
        .timer-2fa {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 15px;
            text-align: center;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <?php if ($pending2FA): ?>
            <!-- ==================== مرحله 2FA ==================== -->
            <div class="logo">🔐</div>
            <h1>احراز هویت دو مرحله‌ای</h1>
            <p class="subtitle">کد ۶ رقمی را از اپ Authenticator وارد کنید</p>

            <?php if ($error): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="info-2fa">
                <span class="icon">📱</span>
                کاربر <b><?= htmlspecialchars($pending2FA['username']) ?></b><br>
                اپ Authenticator خود را باز کنید و کد ۶ رقمی را وارد کنید
            </div>

            <div class="timer-2fa" id="timer">
                ⏱ زمان باقی‌مانده: <span id="timer-count">5:00</span>
            </div>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="verify_2fa">
                <div class="form-group">
                    <label for="code">کد ۶ رقمی</label>
                    <input type="text" id="code" name="code" class="code-input"
                           placeholder="000000" maxlength="6" inputmode="numeric"
                           pattern="[0-9]{6}" required autofocus>
                </div>
                <button type="submit" class="btn-login">✅ تأیید و ورود</button>
            </form>

            <form method="post" style="margin-top:10px">
                <input type="hidden" name="action" value="cancel_2fa">
                <button type="submit" class="btn-secondary">← بازگشت</button>
            </form>

            <div class="hint">
                💡 اگر به اپ دسترسی ندارید، از <strong>کد پشتیبان</strong> استفاده کنید
            </div>

            <script>
                // Timer 5 minutes
                (function() {
                    let seconds = 300;
                    const el = document.getElementById('timer-count');
                    function tick() {
                        const m = Math.floor(seconds / 60);
                        const s = seconds % 60;
                        el.textContent = m + ':' + String(s).padStart(2, '0');
                        if (seconds > 0) {
                            seconds--;
                            setTimeout(tick, 1000);
                        } else {
                            location.reload();
                        }
                    }
                    tick();
                })();
            </script>

        <?php else: ?>
            <!-- ==================== مرحله ورود عادی ==================== -->
            <div class="logo">🔐</div>
            <h1>ورود به پنل مدیریت</h1>
            <p class="subtitle">لطفاً اطلاعات حساب خود را وارد کنید</p>

            <?php if ($blockedInfo && !empty($blockedInfo['blocked'])): ?>
                <div class="error blocked">
                    ⛔ دسترسی مسدود شد
                </div>
                <div class="lock-info">
                    <i class="bi bi-clock-history"></i>
                    <span>زمان باقی‌مانده:
                        <span class="countdown" id="countdown"><?= $blockedInfo['remaining_min'] ?>:00</span>
                    </span>
                </div>
            <?php elseif ($error): ?>
                <div class="error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">نام کاربری</label>
                    <input type="text" id="username" name="username"
                           placeholder="نام کاربری خود را وارد کنید"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           <?= ($blockedInfo && $blockedInfo['blocked']) ? 'disabled' : 'required autofocus' ?>>
                </div>
                <div class="form-group">
                    <label for="password">رمز عبور</label>
                    <input type="password" id="password" name="password"
                           placeholder="رمز عبور خود را وارد کنید"
                           <?= ($blockedInfo && $blockedInfo['blocked']) ? 'disabled' : 'required' ?>>
                </div>
                <button type="submit" class="btn-login" <?= ($blockedInfo && $blockedInfo['blocked']) ? 'disabled' : '' ?>>
                    ورود به پنل
                </button>
            </form>

            <div class="hint">
                💡 اطلاعات پیش‌فرض: <strong>admin</strong> / <strong>admin123</strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($blockedInfo && !empty($blockedInfo['blocked'])): ?>
    <script>
        (function() {
            let totalSeconds = <?= (int) $blockedInfo['remaining_min'] * 60 ?>;
            const el = document.getElementById('countdown');
            if (!el) return;
            function update() {
                const m = Math.floor(totalSeconds / 60);
                const s = totalSeconds % 60;
                el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                if (totalSeconds > 0) {
                    totalSeconds--;
                    setTimeout(update, 1000);
                } else {
                    location.reload();
                }
            }
            update();
        })();
    </script>
    <?php endif; ?>
</body>
</html>

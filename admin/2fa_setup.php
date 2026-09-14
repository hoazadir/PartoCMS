<?php
/**
 * PartoCMS - Two-Factor Authentication Setup
 */
require_once __DIR__ . '/auth_check.php';

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();
require_once __DIR__ . '/includes/totp.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'user';

$message = '';
$messageType = '';

$stmt = $pdo->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$stmt->execute([$userId]);
$twoFa = $stmt->fetch();
$isEnabled = $twoFa && $twoFa['enabled'];

// ==================== Actions ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Enable 2FA ----------
    if ($action === 'enable') {
        try {
            $secret = $twoFa['secret'] ?? TOTP::generateSecret();
            $code = $_POST['code'] ?? '';

            if (!TOTP::verify($secret, $code)) {
                $message = '❌ کد وارد شده نامعتبر است. اگر مطمئنید کد درست است، روی "تولید Secret جدید" کلیک کنید.';
                $messageType = 'danger';
            } else {
                $backupCodes = TOTP::generateBackupCodes(8);

                if ($twoFa) {
                    $stmt = $pdo->prepare("UPDATE user_2fa SET enabled = 1, enabled_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO user_2fa (user_id, secret, enabled, enabled_at) VALUES (?, ?, 1, NOW())");
                    $stmt->execute([$userId, $secret]);
                }

                $pdo->prepare("DELETE FROM user_2fa_backup WHERE user_id = ?")->execute([$userId]);
                $stmt = $pdo->prepare("INSERT INTO user_2fa_backup (user_id, code) VALUES (?, ?)");
                foreach ($backupCodes as $c) {
                    $stmt->execute([$userId, password_hash($c, PASSWORD_DEFAULT)]);
                }

                try {
                    $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id) VALUES ('integrity', ?, ?, ?)")
                        ->execute(['2FA enabled for: ' . $username, $_SERVER['REMOTE_ADDR'] ?? '?', $userId]);
                } catch (Throwable $e) {}

                $_SESSION['2fa_just_enabled'] = $backupCodes;
                header('Location: 2fa_setup.php?step=success');
                exit;
            }
        } catch (Throwable $e) {
            $message = 'خطا: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // ---------- Disable 2FA (keep secret) ----------
    elseif ($action === 'disable') {
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($password, $hash)) {
            $message = '❌ رمز عبور اشتباه است.';
            $messageType = 'danger';
        } else {
            // ✅ فقط enabled = 0، Secret حفظ می‌شود
            $pdo->prepare("UPDATE user_2fa SET enabled = 0 WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("DELETE FROM user_2fa_backup WHERE user_id = ?")->execute([$userId]);

            try {
                $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id) VALUES ('integrity', ?, ?, ?)")
                    ->execute(['2FA disabled for: ' . $username, $_SERVER['REMOTE_ADDR'] ?? '?', $userId]);
            } catch (Throwable $e) {}

            $message = '✅ 2FA غیرفعال شد — Secret حفظ شد. برای فعال‌سازی مجدد فقط کد ۶ رقمی جدید را وارد کنید.';
            $messageType = 'success';
            $isEnabled = false;
            // Note: $twoFa حفظ می‌شود تا secret از دست نرود
        }
    }

    // ---------- Regenerate Secret (تولید Secret جدید) ----------
    elseif ($action === 'regenerate') {
        $pdo->prepare("DELETE FROM user_2fa WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_2fa_backup WHERE user_id = ?")->execute([$userId]);
        $twoFa = null;
        $isEnabled = false;

        try {
            $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id) VALUES ('integrity', ?, ?, ?)")
                ->execute(['2FA secret regenerated for: ' . $username, $_SERVER['REMOTE_ADDR'] ?? '?', $userId]);
        } catch (Throwable $e) {}

        $message = '♻️ Secret جدید تولید شد — لطفاً QR زیر را در اپلیکیشن خود مجدداً اسکن کنید (ابتدا Entry قدیمی را حذف کنید)';
        $messageType = 'warning';
    }
}

// ==================== Prepare secret & QR ====================
$newSecret = null;
$qrUrl = null;
$isNewSecret = false;

if (!$isEnabled) {
    // اگر secret در DB هست، استفاده کن؛ در غیر این صورت جدید بساز
    if ($twoFa && !empty($twoFa['secret'])) {
        $newSecret = $twoFa['secret'];
    } else {
        $newSecret = TOTP::generateSecret();
        $isNewSecret = true;

        // ذخیره در DB با enabled=0
        if ($twoFa) {
            $pdo->prepare("UPDATE user_2fa SET secret = ?, enabled = 0 WHERE user_id = ?")
                ->execute([$newSecret, $userId]);
        } else {
            $pdo->prepare("INSERT INTO user_2fa (user_id, secret, enabled) VALUES (?, ?, 0)")
                ->execute([$userId, $newSecret]);
        }
    }

    // ✅ اضافه کردن timestamp به label برای اجبار Google Auth به ساخت Entry جدید
    $label = 'PartoCMS-' . $username . '-' . substr(md5($newSecret), 0, 6);
    $otpUri = TOTP::getOtpAuthUri($newSecret, $label, 'PartoCMS');
    $qrUrl = TOTP::getQrCodeUrl($otpUri, 220);
}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>2FA — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.content-card .body{padding:22px}
.btn-2fa{padding:12px 22px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;font-size:14px;display:inline-flex;align-items:center;gap:8px;margin:4px;text-decoration:none}
.btn-primary-2fa{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff}
.btn-success-2fa{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-danger-2fa{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
.btn-secondary-2fa{background:linear-gradient(135deg,#64748b,#334155);color:#fff}
.btn-warning-2fa{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.status-badge{display:inline-block;padding:8px 18px;border-radius:20px;font-size:14px;font-weight:bold}
.status-active{background:#d1fae5;color:#065f46}
.status-inactive{background:#fee2e2;color:#991b1b}
.qr-box{background:#fff;border:3px solid #1e293b;border-radius:15px;padding:20px;display:inline-block;margin:15px 0}
.secret-box{background:#f1f5f9;border:2px dashed #64748b;padding:15px 20px;border-radius:10px;font-family:monospace;font-size:16px;letter-spacing:3px;direction:ltr;text-align:center;word-break:break-all;font-weight:bold;color:#1e293b}
.code-input{width:100%;max-width:280px;padding:15px;border:3px solid #e2e8f0;border-radius:10px;font-family:monospace;font-size:24px;text-align:center;letter-spacing:8px;direction:ltr}
.code-input:focus{outline:none;border-color:#dc2626;box-shadow:0 0 0 4px rgba(220,38,38,.15)}
.backup-code{background:#1e293b;color:#a78bfa;padding:12px 16px;border-radius:8px;font-family:monospace;font-size:15px;display:inline-block;margin:4px;letter-spacing:1px}
.step-badge{display:inline-block;background:#dc2626;color:#fff;width:32px;height:32px;line-height:32px;border-radius:50%;text-align:center;font-weight:bold;margin-left:10px}
.app-list{display:flex;flex-wrap:wrap;gap:10px;margin:15px 0}
.app-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 15px;display:flex;align-items:center;gap:8px;font-size:13px}
.warning-box{background:#fef3c7;border:2px solid #f59e0b;color:#92400e;padding:15px 20px;border-radius:10px;font-size:13px;margin-bottom:15px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🔐 احراز هویت دو مرحله‌ای (2FA)</h1>
        <p>لایه امنیتی اضافی برای ورود به پنل مدیریت</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="header"><h5>وضعیت فعلی</h5></div>
        <div class="body">
            <p style="margin-bottom:15px">
                وضعیت:
                <?php if ($isEnabled): ?>
                    <span class="status-badge status-active">✅ فعال</span>
                <?php else: ?>
                    <span class="status-badge status-inactive">❌ غیرفعال</span>
                <?php endif; ?>
            </p>
            <?php if ($isEnabled): ?>
                <p style="color:#64748b;font-size:13px">
                    فعال‌شده در: <?= htmlspecialchars($twoFa['enabled_at']) ?><br>
                    آخرین استفاده: <?= $twoFa['last_used_at'] ? htmlspecialchars($twoFa['last_used_at']) : 'هرگز' ?>
                </p>
            <?php else: ?>
                <p style="color:#64748b;font-size:13px">برای فعال‌سازی، مراحل زیر را دنبال کنید.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isEnabled): ?>
    
    <!-- دکمه تولید Secret جدید -->
    <div class="content-card">
        <div class="header"><h5>🔄 تولید Secret جدید</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#475569">
                اگر نمی‌توانید با QR فعلی وارد شوید، روی این دکمه کلیک کنید تا Secret جدید ساخته شود. 
                سپس در Google Authenticator، ابتدا Entry قدیمی <code>PartoCMS-...</code> را حذف کنید و QR جدید را اسکن کنید.
            </p>
            <form method="post" onsubmit="return confirm('Secret جدید تولید شود؟ کدهای قبلی دیگر کار نمی‌کنند.');">
                <input type="hidden" name="action" value="regenerate">
                <button type="submit" class="btn-2fa btn-warning-2fa">
                    <i class="bi bi-arrow-clockwise"></i> تولید Secret جدید
                </button>
            </form>
        </div>
    </div>

    <div class="content-card">
        <div class="header"><h5><span class="step-badge">۱</span> نصب اپلیکیشن Authenticator</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#475569">یکی از اپلیکیشن‌های زیر را روی گوشی خود نصب کنید:</p>
            <div class="app-list">
                <div class="app-item">📱 <b>Google Authenticator</b></div>
                <div class="app-item">📱 <b>Authy</b></div>
                <div class="app-item">📱 <b>Microsoft Authenticator</b></div>
                <div class="app-item">📱 <b>FreeOTP</b></div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="header"><h5><span class="step-badge">۲</span> اسکن QR Code</h5></div>
        <div class="body" style="text-align:center">
            <div class="warning-box" style="text-align:right">
                ⚠️ <b>قبل از اسکن:</b> در Google Authenticator، اگر Entry قبلی با نام <code>PartoCMS-...</code> وجود دارد، 
                آن را حذف کنید تا Entry جدید اضافه شود.
            </div>
            <p style="font-size:13px;color:#475569">اپلیکیشن را باز کنید و این QR Code را اسکن کنید:</p>
            <div class="qr-box">
                <img src="<?= htmlspecialchars($qrUrl) ?>" alt="2FA QR Code" width="220" height="220" style="display:block">
            </div>
            <p style="font-size:12px;color:#94a3b8;margin:15px 0 5px">اگر نمی‌توانید QR را اسکن کنید، این کد را دستی وارد کنید:</p>
            <div class="secret-box"><?= htmlspecialchars(chunk_split($newSecret, 4, ' ')) ?></div>
        </div>
    </div>

    <div class="content-card">
        <div class="header"><h5><span class="step-badge">۳</span> تأیید کد ۶ رقمی</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#475569">کد ۶ رقمی که در اپلیکیشن نمایش داده می‌شود را وارد کنید:</p>
            <form method="post" style="margin-top:15px">
                <input type="hidden" name="action" value="enable">
                <input type="text" name="code" class="code-input"
                       placeholder="000000" maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" required autofocus autocomplete="off">
                <div style="margin-top:15px">
                    <button type="submit" class="btn-2fa btn-success-2fa">
                        <i class="bi bi-shield-check"></i> فعال‌سازی 2FA
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
    <div class="content-card">
        <div class="header"><h5>🔓 غیرفعال‌سازی 2FA</h5></div>
        <div class="body">
            <p style="font-size:13px;color:#991b1b;background:#fee2e2;padding:12px;border-radius:8px;margin-bottom:15px">
                ⚠️ <b>توجه:</b> با غیرفعال کردن، Secret حفظ می‌شود. برای فعال‌سازی مجدد فقط کد ۶ رقمی جدید را وارد کنید.
            </p>
            <form method="post" onsubmit="return confirm('آیا مطمئنید؟');">
                <input type="hidden" name="action" value="disable">
                <label style="font-size:13px;font-weight:bold;display:block;margin-bottom:8px">رمز عبور خود را وارد کنید:</label>
                <input type="password" name="password"
                       style="padding:12px;border:2px solid #e2e8f0;border-radius:8px;width:100%;max-width:300px" required>
                <div style="margin-top:15px">
                    <button type="submit" class="btn-2fa btn-danger-2fa">
                        <i class="bi bi-x-circle"></i> غیرفعال کردن
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['2fa_just_enabled'])): ?>
    <div class="content-card">
        <div class="header" style="background:#fef3c7">
            <h5>🔑 کدهای پشتیبان (فقط یک بار نمایش داده می‌شوند!)</h5>
        </div>
        <div class="body">
            <p style="font-size:13px;color:#92400e;background:#fef3c7;padding:12px;border-radius:8px;margin-bottom:15px">
                ⚠️ <b>مهم:</b> این کدها را در جای امنی ذخیره کنید. هر کد فقط یک‌بار قابل استفاده است.
            </p>
            <div style="text-align:center;padding:20px 0">
                <?php foreach ($_SESSION['2fa_just_enabled'] as $code): ?>
                <span class="backup-code"><?= htmlspecialchars($code) ?></span>
                <?php endforeach; ?>
            </div>
            <div style="text-align:center;margin-top:15px">
                <button onclick="copyAllCodes()" class="btn-2fa btn-primary-2fa">
                    <i class="bi bi-clipboard"></i> کپی همه کدها
                </button>
                <button onclick="hideBackupCodes()" class="btn-2fa btn-secondary-2fa">
                    <i class="bi bi-check2"></i> ذخیره کردم
                </button>
            </div>
        </div>
    </div>
    <script>
    function copyAllCodes() {
        const codes = document.querySelectorAll('.backup-code');
        const text = Array.from(codes).map(c => c.textContent).join('\n');
        navigator.clipboard.writeText(text).then(() => alert('✅ کدها کپی شدند!'));
    }
    function hideBackupCodes() {
        if (confirm('آیا کدها را ذخیره کردید؟')) window.location.href = '2fa_setup.php';
    }
    </script>
    <?php unset($_SESSION['2fa_just_enabled']); endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

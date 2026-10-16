<?php
/**
 * PartoCMS - Newsletter Admin
 * تنظیمات SMTP + عمومی
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Infrastructure\Mailer\SmtpMailer;

$pdo = getDB();
$flash = nl_flash();

// ─── پردازش POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $keys = [
            'newsletter_smtp_host', 'newsletter_smtp_port',
            'newsletter_smtp_username', 'newsletter_smtp_password',
            'newsletter_smtp_encryption', 'newsletter_from_email',
            'newsletter_from_name', 'newsletter_reply_to',
            'newsletter_smtp_timeout',
            'newsletter_double_optin', 'newsletter_items_per_page',
        ];

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            $stmt->execute([$key, (string) $value]);
        }

        nl_flash('تنظیمات ذخیره شد');
        header('Location: ' . nl_admin_url('settings.php'));
        exit;
    }

    if ($action === 'test_smtp') {
        try {
            $host       = (string) ($_POST['newsletter_smtp_host'] ?? '');
            $port       = (int) ($_POST['newsletter_smtp_port'] ?? 587);
            $username   = (string) ($_POST['newsletter_smtp_username'] ?? '');
            $password   = (string) ($_POST['newsletter_smtp_password'] ?? '');
            $encryption = (string) ($_POST['newsletter_smtp_encryption'] ?? 'tls');
            $fromEmail  = (string) ($_POST['newsletter_from_email'] ?? '');
            $fromName   = (string) ($_POST['newsletter_from_name'] ?? '');
            $toEmail    = (string) ($_POST['newsletter_test_email'] ?? '');

            if ($toEmail === '') {
                throw new RuntimeException('ایمیل مقصد تست الزامی است');
            }

            $mailer = new SmtpMailer(
                host: $host,
                port: $port,
                username: $username,
                password: $password,
                encryption: $encryption,
                fromEmail: $fromEmail,
                fromName: $fromName,
                timeout: 15
            );

            $msgId = $mailer->send(
                to: $toEmail,
                toName: 'Test',
                subject: '🧪 تست SMTP از PartoCMS — ' . date('Y/m/d H:i'),
                bodyHtml: '<h2>✅ تست SMTP موفق بود</h2><p>این ایمیل از پنل ادمین PartoCMS ارسال شده است.</p>'
            );

            nl_flash('✅ ایمیل تست ارسال شد — Message-ID: ' . $msgId);
        } catch (Throwable $e) {
            nl_flash('❌ خطا: ' . $e->getMessage(), 'error');
        }

        header('Location: ' . nl_admin_url('settings.php'));
        exit;
    }
}

// ─── بارگذاری تنظیمات ───
$stmt = $pdo->query("
    SELECT setting_key, setting_value FROM settings
    WHERE setting_key LIKE 'newsletter_%'
");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

$defaults = [
    'newsletter_smtp_host' => '',
    'newsletter_smtp_port' => '587',
    'newsletter_smtp_username' => '',
    'newsletter_smtp_password' => '',
    'newsletter_smtp_encryption' => 'tls',
    'newsletter_from_email' => getSetting('admin_email', ''),
    'newsletter_from_name' => getSetting('site_name', 'PartoCMS'),
    'newsletter_reply_to' => '',
    'newsletter_smtp_timeout' => '30',
    'newsletter_double_optin' => '0',
    'newsletter_items_per_page' => '20',
];

foreach ($defaults as $k => $v) {
    $settings[$k] = $settings[$k] ?? $v;
}

// وضعیت SMTP
$smtpConfigured = !empty($settings['newsletter_smtp_host']);
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات خبرنامه | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h4 { margin: 5px 0 0; }
        .card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h5 { margin: 0 0 20px; color: #1e293b; font-size: 15px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 25px; }
        .form-grid.one-col { grid-template-columns: 1fr; }
        .form-row { margin-bottom: 5px; }
        .form-row label { display: block; font-size: 13px; font-weight: bold; color: #334155; margin-bottom: 6px; }
        .form-row label .req { color: #dc2626; }
        .form-row input[type="text"], .form-row input[type="password"], .form-row input[type="number"], .form-row input[type="email"], .form-row select {
            width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box;
            direction: ltr; text-align: left;
        }
        .form-row input:focus, .form-row select:focus { outline: none; border-color: #6366f1; }
        .form-row .hint { font-size: 11px; color: #94a3b8; margin-top: 5px; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-row .info { flex: 1; padding-left: 15px; }
        .toggle-row .info .lbl { font-size: 13px; font-weight: bold; color: #334155; display: block; margin-bottom: 3px; }
        .toggle-row .info .hint { font-size: 11px; color: #94a3b8; }
        .switch { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 28px; transition: 0.3s; }
        .slider:before { content: ""; position: absolute; height: 20px; width: 20px; left: 4px; bottom: 4px; background: #fff; border-radius: 50%; transition: 0.3s; }
        input:checked + .slider { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        input:checked + .slider:before { transform: translateX(24px); }
        .btn { padding: 11px 22px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-info { background: #0ea5e9; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .actions-bar { display: flex; gap: 10px; flex-wrap: wrap; padding: 20px; background: #f8fafc; border-radius: 10px; align-items: center; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; color: #fff; font-weight: bold; }
        .status-ok { background: #16a34a; }
        .status-warn { background: #f59e0b; }
        .test-box { background: #fef3c7; border: 2px solid #fcd34d; border-radius: 8px; padding: 15px; margin-top: 15px; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <div>
            <a href="<?= nl_admin_url() ?>" style="text-decoration:none; color:#6366f1;">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
            <h4>⚙️ تنظیمات خبرنامه</h4>
        </div>
        <div>
            <span class="status-badge <?= $smtpConfigured ? 'status-ok' : 'status-warn' ?>">
                <?= $smtpConfigured ? '✅ SMTP پیکربندی شده' : '⚠️ SMTP پیکربندی نشده' ?>
            </span>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="save">

        <!-- SMTP -->
        <div class="card">
            <h5>📧 SMTP Server</h5>
            <div class="form-grid">
                <div class="form-row">
                    <label>SMTP Host <span class="req">*</span></label>
                    <input type="text" name="newsletter_smtp_host" value="<?= htmlspecialchars($settings['newsletter_smtp_host']) ?>" placeholder="smtp.gmail.com">
                    <div class="hint">مثال: smtp.gmail.com یا smtp-mail.outlook.com</div>
                </div>

                <div class="form-row">
                    <label>SMTP Port <span class="req">*</span></label>
                    <input type="number" name="newsletter_smtp_port" value="<?= (int) $settings['newsletter_smtp_port'] ?>" min="1" max="65535" placeholder="587">
                    <div class="hint">587 (TLS) یا 465 (SSL) یا 25 (بدون رمزنگاری)</div>
                </div>

                <div class="form-row">
                    <label>نام کاربری</label>
                    <input type="text" name="newsletter_smtp_username" value="<?= htmlspecialchars($settings['newsletter_smtp_username']) ?>" placeholder="user@example.com" autocomplete="off">
                </div>

                <div class="form-row">
                    <label>رمز عبور / App Password</label>
                    <input type="password" name="newsletter_smtp_password" value="<?= htmlspecialchars($settings['newsletter_smtp_password']) ?>" placeholder="••••••••" autocomplete="new-password">
                    <div class="hint">برای Gmail باید App Password بسازی (نه رمز اصلی)</div>
                </div>

                <div class="form-row">
                    <label>رمزنگاری</label>
                    <select name="newsletter_smtp_encryption">
                        <option value="tls" <?= $settings['newsletter_smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS (پیشنهاد — پورت 587)</option>
                        <option value="ssl" <?= $settings['newsletter_smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (پورت 465)</option>
                        <option value="none" <?= $settings['newsletter_smtp_encryption'] === 'none' ? 'selected' : '' ?>>بدون رمزنگاری (ناامن)</option>
                    </select>
                </div>

                <div class="form-row">
                    <label>Timeout (ثانیه)</label>
                    <input type="number" name="newsletter_smtp_timeout" value="<?= (int) $settings['newsletter_smtp_timeout'] ?>" min="5" max="120">
                </div>
            </div>
        </div>

        <!-- Sender -->
        <div class="card">
            <h5>👤 اطلاعات فرستنده</h5>
            <div class="form-grid">
                <div class="form-row">
                    <label>ایمیل فرستنده <span class="req">*</span></label>
                    <input type="email" name="newsletter_from_email" value="<?= htmlspecialchars($settings['newsletter_from_email']) ?>" placeholder="noreply@yoursite.com">
                </div>

                <div class="form-row">
                    <label>نام فرستنده</label>
                    <input type="text" name="newsletter_from_name" value="<?= htmlspecialchars($settings['newsletter_from_name']) ?>" placeholder="PartoCMS">
                </div>

                <div class="form-row">
                    <label>Reply-To (اختیاری)</label>
                    <input type="email" name="newsletter_reply_to" value="<?= htmlspecialchars($settings['newsletter_reply_to']) ?>" placeholder="support@yoursite.com">
                    <div class="hint">اگر پاسخ کاربر باید به ایمیل دیگری برود</div>
                </div>
            </div>
        </div>

        <!-- General -->
        <div class="card">
            <h5>⚙️ تنظیمات عمومی</h5>

            <div class="toggle-row">
                <div class="info">
                    <span class="lbl">Double Opt-in</span>
                    <div class="hint">کاربر باید ایمیل خود را تأیید کند (توصیه می‌شود)</div>
                </div>
                <label class="switch">
                    <input type="hidden" name="newsletter_double_optin" value="0">
                    <input type="checkbox" name="newsletter_double_optin" value="1" <?= !empty($settings['newsletter_double_optin']) && $settings['newsletter_double_optin'] === '1' ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="form-row" style="margin-top: 15px;">
                <label>تعداد مشترکین در هر صفحه</label>
                <input type="number" name="newsletter_items_per_page" value="<?= (int) $settings['newsletter_items_per_page'] ?>" min="5" max="100">
            </div>
        </div>

        <div class="actions-bar">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> ذخیره تنظیمات
            </button>
        </div>
    </form>

    <!-- Test SMTP -->
    <?php if ($smtpConfigured): ?>
        <div class="card">
            <h5>🧪 تست SMTP</h5>
            <form method="post">
                <input type="hidden" name="action" value="test_smtp">
                <input type="hidden" name="newsletter_smtp_host" value="<?= htmlspecialchars($settings['newsletter_smtp_host']) ?>">
                <input type="hidden" name="newsletter_smtp_port" value="<?= (int) $settings['newsletter_smtp_port'] ?>">
                <input type="hidden" name="newsletter_smtp_username" value="<?= htmlspecialchars($settings['newsletter_smtp_username']) ?>">
                <input type="hidden" name="newsletter_smtp_password" value="<?= htmlspecialchars($settings['newsletter_smtp_password']) ?>">
                <input type="hidden" name="newsletter_smtp_encryption" value="<?= htmlspecialchars($settings['newsletter_smtp_encryption']) ?>">
                <input type="hidden" name="newsletter_from_email" value="<?= htmlspecialchars($settings['newsletter_from_email']) ?>">
                <input type="hidden" name="newsletter_from_name" value="<?= htmlspecialchars($settings['newsletter_from_name']) ?>">

                <div class="test-box">
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div class="form-row" style="flex: 1; margin: 0;">
                            <label>ایمیل مقصد برای تست</label>
                            <input type="email" name="newsletter_test_email" value="<?= htmlspecialchars($settings['newsletter_from_email']) ?>" required>
                            <div class="hint">یک ایمیل تست به این آدرس ارسال می‌شود</div>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-send"></i> ارسال تست
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Help -->
    <div class="card">
        <h5>💡 راهنمای SMTP</h5>
        <div style="font-size: 13px; line-height: 2; color: #475569;">
            <strong>Gmail:</strong> smtp.gmail.com — port 587 (TLS) — نیاز به App Password<br>
            <strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com — port 587 (TLS)<br>
            <strong>Yahoo:</strong> smtp.mail.yahoo.com — port 465 (SSL)<br>
            <strong>Mailgun:</strong> smtp.mailgun.org — port 587 (TLS)<br>
            <strong>SendGrid:</strong> smtp.sendgrid.net — port 587 (TLS)
        </div>
    </div>

</div>

</body>
</html>

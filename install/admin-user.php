<?php
/**
 * 🆕 PartoCMS Smart Installer — Step 3: Admin User
 *
 * صفحه سوم: ساخت حساب ادمین اصلی
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

session_start();

// بررسی انجام مرحله ۲
if (empty($_SESSION['installer_db'])) {
    header('Location: database.php');
    exit;
}

$errors = [];
$success = '';

$defaults = [
    'username'  => $_SESSION['installer_admin']['username']  ?? 'admin',
    'email'     => $_SESSION['installer_admin']['email']     ?? '',
    'site_name' => $_SESSION['installer_admin']['site_name'] ?? 'وب‌سایت من',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $siteName = trim($_POST['site_name'] ?? '');

    $defaults = compact('username', 'email', 'siteName');
    $defaults['site_name'] = $siteName;

    // ─── اعتبارسنجی ───
    if (strlen($username) < 3 || strlen($username) > 30) {
        $errors[] = 'نام کاربری باید بین ۳ تا ۳۰ کاراکتر باشد';
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        $errors[] = 'نام کاربری فقط حروف انگلیسی، اعداد، _ و -';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل نامعتبر است';
    }

    if (strlen($password) < 8) {
        $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'رمز عبور باید حداقل یک حرف و یک عدد داشته باشد';
    }

    if ($password !== $confirm) {
        $errors[] = 'رمز عبور و تکرار آن یکسان نیستند';
    }

    if ($siteName === '') {
        $errors[] = 'نام سایت الزامی است';
    }

    // ─── ذخیره در session ───
    if (empty($errors)) {
        $_SESSION['installer_admin'] = [
            'username'  => $username,
            'email'     => $email,
            'password'  => $password,  // در install.php hash می‌شود
            'site_name' => $siteName,
        ];

        $success = 'اطلاعات ادمین ذخیره شد';

        // هدایت به مرحله ۴
        header('Location: site-type.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب PartoCMS — مرحله ۳: ادمین</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: Tahoma, 'Vazirmatn', sans-serif;
        padding: 20px;
    }
    .installer-container { max-width: 900px; margin: 0 auto; }
    .installer-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    .installer-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: #fff;
        padding: 30px;
        text-align: center;
    }
    .installer-header h1 { margin: 0 0 10px; font-size: 26px; font-weight: bold; }
    .installer-header p { margin: 0; opacity: 0.9; font-size: 14px; }

    .stepper {
        display: flex;
        justify-content: space-between;
        padding: 20px 30px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .step { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 80px; opacity: 0.5; }
    .step.completed { opacity: 1; }
    .step.active { opacity: 1; }
    .step .circle {
        width: 36px; height: 36px; border-radius: 50%;
        background: #cbd5e1; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; margin-bottom: 8px; font-size: 14px;
    }
    .step.completed .circle { background: #10b981; }
    .step.active .circle { background: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
    .step .label { font-size: 11px; color: #64748b; font-weight: bold; text-align: center; }
    .step.active .label { color: #3b82f6; }

    .installer-body { padding: 30px; }

    .alert {
        padding: 12px 18px; border-radius: 8px; margin-bottom: 20px;
        font-size: 13.5px; display: flex; align-items: flex-start; gap: 10px;
    }
    .alert-error { background: #fee2e2; border-right: 4px solid #dc2626; color: #991b1b; }
    .alert ul { margin: 0; padding-right: 20px; }

    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block; font-weight: bold; margin-bottom: 6px;
        color: #1e293b; font-size: 13.5px;
    }
    .form-group label .required { color: #dc2626; }
    .form-group .hint {
        font-size: 11.5px; color: #64748b; margin-top: 4px; display: block;
    }
    .form-control {
        width: 100%; padding: 10px 14px;
        border: 2px solid #e2e8f0; border-radius: 8px;
        font-size: 14px; font-family: inherit;
        transition: all 0.2s; box-sizing: border-box;
    }
    .form-control:focus {
        outline: none; border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .form-control[dir="ltr"] { direction: ltr; font-family: monospace; }

    .password-strength {
        margin-top: 8px; height: 6px; border-radius: 3px;
        background: #e2e8f0; overflow: hidden;
    }
    .password-strength-bar {
        height: 100%; width: 0%; transition: all 0.3s;
    }
    .password-strength-bar.weak   { background: #dc2626; width: 33%; }
    .password-strength-bar.medium { background: #f59e0b; width: 66%; }
    .password-strength-bar.strong { background: #10b981; width: 100%; }

    .actions {
        padding: 20px 30px; background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff; border: none; padding: 12px 30px;
        border-radius: 8px; font-weight: bold; font-size: 14px;
        text-decoration: none; display: inline-flex;
        align-items: center; gap: 8px; transition: all 0.2s;
        cursor: pointer; font-family: inherit;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59,130,246,0.4); color: #fff;
    }
    .btn-secondary-custom {
        background: #e2e8f0; color: #334155;
        padding: 12px 24px; border-radius: 8px;
        text-decoration: none; font-weight: bold; font-size: 14px;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-secondary-custom:hover { background: #cbd5e1; color: #1e293b; }

    .brand-footer { text-align: center; margin-top: 20px; color: rgba(255,255,255,0.8); font-size: 12px; }

    @media (max-width: 700px) {
        .installer-header h1 { font-size: 20px; }
        .installer-body { padding: 20px; }
    }
</style>
</head>
<body>

<div class="installer-container">
    <div class="installer-card">

        <div class="installer-header">
            <h1>🚀 نصب PartoCMS</h1>
            <p>مرحله ۳ از ۵: حساب ادمین</p>
        </div>

        <div class="stepper">
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">پیش‌نیازها</div>
            </div>
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">دیتابیس</div>
            </div>
            <div class="step active">
                <div class="circle">۳</div>
                <div class="label">ادمین</div>
            </div>
            <div class="step">
                <div class="circle">۴</div>
                <div class="label">نوع سایت</div>
            </div>
            <div class="step">
                <div class="circle">۵</div>
                <div class="label">نصب</div>
            </div>
        </div>

        <div class="installer-body">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <span>❌</span>
                    <div>
                        <strong>خطا:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="alert" style="background:#dbeafe;border-right:4px solid #3b82f6;color:#1e40af;">
                <span>💡</span>
                <div>
                    این حساب <strong>مدیر کل سیستم</strong> خواهد بود.
                    نام کاربری و رمز عبور را جای امنی ذخیره کنید.
                </div>
            </div>

            <form method="post">

                <h3 style="margin-top: 0; font-size: 16px; color: #1e293b;">
                    🌐 اطلاعات سایت
                </h3>

                <div class="form-group">
                    <label>نام سایت <span class="required">*</span></label>
                    <input type="text" name="site_name" class="form-control"
                           value="<?= htmlspecialchars($defaults['site_name']) ?>"
                           placeholder="وب‌سایت من" required>
                </div>

                <h3 style="margin-top: 25px; font-size: 16px; color: #1e293b;">
                    👤 حساب ادمین
                </h3>

                <div class="form-group">
                    <label>نام کاربری <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" dir="ltr"
                           value="<?= htmlspecialchars($defaults['username']) ?>"
                           placeholder="admin" required autocomplete="off">
                    <span class="hint">۳ تا ۳۰ کاراکتر — حروف انگلیسی، اعداد، _ و -</span>
                </div>

                <div class="form-group">
                    <label>ایمیل <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" dir="ltr"
                           value="<?= htmlspecialchars($defaults['email']) ?>"
                           placeholder="admin@example.com" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label>رمز عبور <span class="required">*</span></label>
                    <input type="password" name="password" id="password" class="form-control" dir="ltr"
                           placeholder="••••••••" required minlength="8" autocomplete="new-password">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <span class="hint" id="strengthText">
                        حداقل ۸ کاراکتر، شامل حرف و عدد
                    </span>
                </div>

                <div class="form-group">
                    <label>تکرار رمز عبور <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" dir="ltr"
                           placeholder="••••••••" required autocomplete="new-password">
                </div>

                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-arrow-left-circle"></i> مرحله بعد: نوع سایت
                    </button>
                </div>
            </form>

        </div>

        <div class="actions">
            <a href="database.php" class="btn-secondary-custom">
                <i class="bi bi-arrow-right"></i> مرحله قبل
            </a>
        </div>

    </div>

    <div class="brand-footer">
        PartoCMS Smart Installer v1.0 — © ۲۰۲۶
    </div>
</div>

<script>
const passwordInput = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');

passwordInput?.addEventListener('input', function() {
    const val = this.value;
    let score = 0;

    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    let level = '';
    let text = '';

    if (val.length === 0) {
        level = '';
        text = 'حداقل ۸ کاراکتر، شامل حرف و عدد';
    } else if (score <= 2) {
        level = 'weak';
        text = '⚠️ ضعیف';
    } else if (score <= 3) {
        level = 'medium';
        text = '🟡 متوسط';
    } else {
        level = 'strong';
        text = '✅ قوی';
    }

    strengthBar.className = 'password-strength-bar ' + level;
    strengthText.textContent = text;
});
</script>

</body>
</html>

<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: user/index.php');
    exit;
}

$error = '';
$success = '';

// ═══════════════════════════════════════════════════════
// ─── CSRF ───
// ═══════════════════════════════════════════════════════
if (empty($_SESSION['reg_csrf'])) {
    $_SESSION['reg_csrf'] = bin2hex(random_bytes(32));
}

// ═══════════════════════════════════════════════════════
// ─── Rate Limit (حداکثر ۳ ثبت‌نام در ساعت) ───
// ═══════════════════════════════════════════════════════
$now = time();
$_SESSION['reg_attempts'] = $_SESSION['reg_attempts'] ?? [];
$_SESSION['reg_attempts'] = array_filter($_SESSION['reg_attempts'], fn($t) => $t > $now - 3600);
$isRateLimited = count($_SESSION['reg_attempts']) >= 3;

// ═══════════════════════════════════════════════════════
// ─── لاگ امنیتی ───
// ═══════════════════════════════════════════════════════
function log_security_event(PDO $pdo, string $type, string $message, ?int $userId = null): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$type, $message, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $userId]);
    } catch (Throwable $e) {
        error_log('Security log error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ─── Rate Limit ───
    if ($isRateLimited) {
        $error = 'تعداد تلاش‌های شما بیش از حد مجاز است. لطفاً یک ساعت دیگر تلاش کنید.';
    }
    // ─── CSRF ───
    elseif (!hash_equals($_SESSION['reg_csrf'], $_POST['_csrf'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است. لطفاً صفحه را رفرش کنید.';
    }
    // ─── Honeypot ───
    elseif (!empty($_POST['website'])) {
        $error = 'درخواست مشکوک شناسایی شد.';
        try {
            $pdo = getDB();
            log_security_event($pdo, 'honeypot_triggered', 'ربات در ثبت‌نام شناسایی شد');
        } catch (Throwable $e) {}
    }
    // ─── پردازش اصلی ───
    else {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // اعتبارسنجی
    $errors = [];
    if (empty($username)) $errors[] = __t('fe_username_required', [], 'نام کاربری الزامی است');
    if (strlen($username) < 3) $errors[] = __t('fe_username_min3', [], 'نام کاربری حداقل ۳ کاراکتر');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = __t('fe_username_format', [], 'نام کاربری فقط حروف انگلیسی، عدد و آندرلاین');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = __t('fe_email_invalid', [], 'ایمیل معتبر الزامی است');
    // سیاست رمز عبور قوی: حداقل ۱۰ کاراکتر، حروف بزرگ و کوچک، عدد و نماد
    if (strlen($password) < 10 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'رمز عبور باید حداقل ۱۰ کاراکتر بوده و شامل حروف بزرگ، کوچک، عدد و نمادهای ویژه باشد.';
    }
    if ($password !== $password2) $errors[] = __t('fe_password_mismatch', [], 'رمز عبور و تکرار آن مطابقت ندارند');

    if (empty($errors)) {
        try {
            $pdo = getDB();
            
            // چک تکراری
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = __t('fe_user_exists', [], 'نام کاربری یا ایمیل قبلاً استفاده شده است');
            } else {
                // نقش پیش‌فرض: کاربر عادی (role_id = 4)
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'user' LIMIT 1");
                $stmt->execute();
                $userRoleId = $stmt->fetchColumn() ?: 4;
                
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role_id, is_active, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$username, $email, $hashed, $userRoleId]);

                $newUserId = $pdo->lastInsertId();

                // --- شروع همگام‌سازی کاربر با CRM و فروشگاه ---
                try {
                    $nameParts = explode(' ', trim($username), 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? '';

                    // ثبت در CRM (contacts)
                    $stmtCrm = $pdo->prepare("INSERT INTO contacts (first_name, last_name, email, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $stmtCrm->execute([$firstName, $lastName, $email]);

                    // ثبت در فروشگاه (shop_customers)
                    $stmtShop = $pdo->prepare("INSERT INTO shop_customers (user_id, first_name, last_name, email, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtShop->execute([$newUserId, $firstName, $lastName, $email]);
                } catch (Throwable $e) {
                    error_log("User Sync Error: " . $e->getMessage());
                }
                // --- پایان همگام‌سازی ---

                
                // ─── ثبت موفق: لاگ + Rate Limit ───
                $_SESSION['reg_attempts'][] = $now;
                $_SESSION['reg_csrf'] = null; // یکبارمصرف
                log_security_event($pdo, 'user_registered', 'ثبت‌نام موفق: ' . $username . ' (' . $email . ')', (int)$newUserId);

                $success = __t('fe_account_created', [], 'حساب شما با موفقیت ساخته شد!');
            }
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
    } // end of else (security checks)
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .register-box {
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
        }
        .register-box .logo { text-align: center; font-size: 55px; margin-bottom: 10px; }
        .register-box h1 { color: #2c3e50; margin-bottom: 8px; font-size: 22px; text-align: center; }
        .register-box .subtitle { color: #7f8c8d; margin-bottom: 25px; font-size: 13px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; color: #34495e; font-size: 13px; font-weight: bold; }
        .form-group input {
            width: 100%; padding: 11px 14px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-family: Tahoma; font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #667eea; outline: none; }
        /* کانتینر اختصاصی فیلد رمز عبور */
        .password-field-container {
            display: flex;
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .password-field-container:focus-within {
            border-color: #667eea;
        }
        .password-field-container input {
            flex: 1; /* کادر متن کمی کوچکتر می‌شود تا دکمه جا شود */
            border: none !important;
            padding: 11px 14px;
            font-family: Tahoma;
            font-size: 14px;
            outline: none;
            background: transparent;
        }
        .password-field-container .toggle-password {
            background: #f8f9fa;
            border: none;
            border-right: 1px solid #e0e0e0; /* خط جداکننده در سمت راست (چون RTL است) */
            padding: 0 15px;
            cursor: pointer;
            color: #7f8c8d;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .password-field-container .toggle-password:hover {
            background: #eef0f2;
        }
        .form-group small { font-size: 11px; color: #95a5a6; }
        .btn-register {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #27ae60, #229954);
            color: #fff; border: none; border-radius: 8px;
            font-size: 15px; font-family: Tahoma; font-weight: bold;
            cursor: pointer; transition: transform 0.2s;
            margin-top: 10px;
        }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(39, 174, 96, 0.4); }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .login-link { text-align: center; margin-top: 20px; font-size: 13px; color: #7f8c8d; }
        .login-link a { color: #3498db; text-decoration: none; font-weight: bold; }
    
    /* ==================== DIRECTION SUPPORT ==================== */
    /* RTL پیش‌فرض — Bootstrap RTL خودش کار می‌کند */

    /* LTR — Bootstrap LTR خودش کار می‌کند */

    /* اصلاحات اضافی برای عناصر خاص */
    html[dir="ltr"] body { direction: ltr; text-align: left; }
    html[dir="rtl"] body { direction: rtl; text-align: right; }

    /* منوی اصلی */
    html[dir="ltr"] .main-nav { flex-direction: row; }
    html[dir="rtl"] .main-nav { flex-direction: row-reverse; }

    /* زیرمنو در LTR */
    html[dir="ltr"] .submenu { right: auto; left: 100%; }

    /* pagination */
    html[dir="ltr"] .article .content table th { text-align: left; }
    html[dir="rtl"] .article .content table th { text-align: right; }

    /* blockquote */
    html[dir="ltr"] .article .content blockquote { border-right: none; border-left: 4px solid #3498db; border-radius: 8px 0 0 8px; }

</style>
</head>
<body>
    <div class="register-box">
        <div class="logo">📝</div>
        <h1>ایجاد حساب کاربری</h1>
        <p class="subtitle">به <?= htmlspecialchars($siteName) ?> بپیوندید</p>

        <?php if ($error): ?>
            <div class="error">❌ <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?>
                <br><a href="login.php" style="color:#155724;font-weight:bold;">→ ورود به حساب</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['reg_csrf'] ?? '') ?>">
            <!-- Honeypot: برای ربات‌ها — کاربر واقعی این را نمی‌بیند -->
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="form-group">
                <label>نام کاربری *</label>
                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                <small>فقط حروف انگلیسی، عدد و آندرلاین</small>
            </div>
            <div class="form-group">
                <label>ایمیل *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>رمز عبور *</label>
                <div class="password-field-container">
                    <input type="password" id="password" name="password" required minlength="10">
                    <button type="button" class="toggle-password" data-target="password" title="نمایش/مخفی">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                
                <!-- لیست داینامیک قوانین رمز عبور -->
                <div id="password-rules" style="margin-top: 10px; font-size: 12px;">
                    <div class="rule text-muted" id="rule-length" style="margin-bottom: 4px; transition: 0.3s;">
                        <i class="bi bi-x-circle-fill text-danger"></i> حداقل ۱۰ کاراکتر
                    </div>
                    <div class="rule text-muted" id="rule-lower" style="margin-bottom: 4px; transition: 0.3s;">
                        <i class="bi bi-x-circle-fill text-danger"></i> شامل حروف کوچک انگلیسی (a-z)
                    </div>
                    <div class="rule text-muted" id="rule-upper" style="margin-bottom: 4px; transition: 0.3s;">
                        <i class="bi bi-x-circle-fill text-danger"></i> شامل حروف بزرگ انگلیسی (A-Z)
                    </div>
                    <div class="rule text-muted" id="rule-number" style="margin-bottom: 4px; transition: 0.3s;">
                        <i class="bi bi-x-circle-fill text-danger"></i> شامل عدد (0-9)
                    </div>
                    <div class="rule text-muted" id="rule-special" style="margin-bottom: 4px; transition: 0.3s;">
                        <i class="bi bi-x-circle-fill text-danger"></i> شامل نمادهای ویژه (@, #, $, %, ...)
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>تکرار رمز عبور *</label>
                <div class="password-field-container">
                    <input type="password" id="password2" name="password2" required minlength="10">
                    <button type="button" class="toggle-password" data-target="password2" title="نمایش/مخفی">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-register">
                <i class="bi bi-person-plus"></i> ایجاد حساب
            </button>
        </form>
        <?php endif; ?>

        <div class="login-link">
            قبلاً ثبت‌نام کردید؟ <a href="login.php">وارد شوید</a>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ─── دکمه نمایش/مخفی رمز عبور ───
    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });

    // ─── اعتبارسنجی داینامیک قوانین رمز عبور ───
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            
            const rules = {
                'rule-length':  val.length >= 10,
                'rule-lower':   /[a-z]/.test(val),
                'rule-upper':   /[A-Z]/.test(val),
                'rule-number':  /[0-9]/.test(val),
                'rule-special': /[^A-Za-z0-9]/.test(val)
            };

            for (const [id, isMet] of Object.entries(rules)) {
                const el = document.getElementById(id);
                if (!el) continue;
                const icon = el.querySelector('i');
                
                if (isMet) {
                    // تیک سبز
                    icon.classList.remove('bi-x-circle-fill', 'text-danger');
                    icon.classList.add('bi-check-circle-fill', 'text-success');
                    el.classList.remove('text-muted');
                    el.classList.add('text-success');
                } else {
                    // ضربدر قرمز
                    icon.classList.remove('bi-check-circle-fill', 'text-success');
                    icon.classList.add('bi-x-circle-fill', 'text-danger');
                    el.classList.remove('text-success');
                    el.classList.add('text-muted');
                }
            }
        });
    }
});
</script>
</body>
</html>

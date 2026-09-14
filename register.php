<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: user/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if (strlen($password) < 6) $errors[] = __t('fe_password_min6', [], 'رمز عبور حداقل ۶ کاراکتر');
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
                
                $success = __t('fe_account_created', [], 'حساب شما با موفقیت ساخته شد!');
            }
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
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
                <input type="password" name="password" required>
                <small>حداقل ۶ کاراکتر</small>
            </div>
            <div class="form-group">
                <label>تکرار رمز عبور *</label>
                <input type="password" name="password2" required>
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
</body>
</html>

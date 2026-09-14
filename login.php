<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    $roleSlug = $_SESSION['role'] ?? 'user';
    if (in_array($roleSlug, ['admin', 'editor', 'author'])) {
        header('Location: admin/index.php');
    } else {
        header('Location: user/index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = __t('fe_req_credentials', [], 'نام کاربری و رمز عبور الزامی است');
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name, r.slug as role_slug
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role'] = $user['role_slug'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role_slug'] = $user['role_slug'];

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                if (in_array($user['role_slug'], ['admin', 'editor', 'author'])) {
                    header('Location: admin/index.php');
                } else {
                    header('Location: user/index.php');
                }
                exit;
            } else {
                $error = __t('fe_wrong_credentials', [], 'نام کاربری یا رمز عبور اشتباه است');
            }
        } catch (PDOException $e) {
            $error = __t('fe_db_error', [], 'خطا در اتصال') . ': ' . $e->getMessage();
        }
    }
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$welcomeMsg = str_replace('{site}', htmlspecialchars($siteName), __t('fe_login_welcome', [], 'به {site} خوش آمدید'));
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __t('fe_login', [], 'ورود') ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 20px; margin: 0;
        }
        .login-box {
            background: #fff; padding: 40px; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%; max-width: 420px;
        }
        .login-box .logo { text-align: center; font-size: 55px; margin-bottom: 10px; }
        .login-box h1 { color: #2c3e50; margin-bottom: 8px; font-size: 22px; text-align: center; }
        .subtitle { color: #7f8c8d; margin-bottom: 25px; font-size: 13px; text-align: center; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; color: #34495e; font-size: 13px; font-weight: bold; }
        .form-group input {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-family: Tahoma; font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #667eea; outline: none; }
        .btn-login {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border: none; border-radius: 8px;
            font-size: 15px; font-family: Tahoma; font-weight: bold;
            cursor: pointer; transition: transform 0.2s;
            margin-top: 5px;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .register-link { text-align: center; margin-top: 20px; font-size: 13px; color: #7f8c8d; }
        .register-link a { color: #27ae60; text-decoration: none; font-weight: bold; }

        html[dir="ltr"] body { direction: ltr; text-align: left; }
        html[dir="rtl"] body { direction: rtl; text-align: right; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">🔐</div>
        <h1><?= __t('fe_login_heading', [], 'ورود به حساب') ?></h1>
        <p class="subtitle"><?= $welcomeMsg ?></p>

        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label><?= __t('fe_username_or_email', [], 'نام کاربری یا ایمیل') ?></label>
                <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label><?= __t('fe_password', [], 'رمز عبور') ?></label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-left"></i> <?= __t('fe_login', [], 'ورود') ?>
            </button>
        </form>

        <div class="register-link">
            <?= __t('fe_no_account', [], 'حساب کاربری ندارید؟') ?>
            <a href="register.php"><?= __t('fe_register_now', [], 'ثبت‌نام کنید') ?></a>
        </div>
    </div>
</body>
</html>

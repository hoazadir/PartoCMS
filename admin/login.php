<?php
require_once __DIR__ . '/../config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'نام کاربری و رمز عبور الزامی است';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name, r.slug as role_slug 
                FROM users u 
                LEFT JOIN roles r ON r.id = u.role_id 
                WHERE u.username = ? AND u.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ذخیره اطلاعات در session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role'] = $user['role_slug']; // برای سازگاری با کدهای قدیمی
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role_slug'] = $user['role_slug'];

                header('Location: index.php');
                exit;
            } else {
                $error = 'نام کاربری یا رمز عبور اشتباه است';
            }
        } catch (PDOException $e) {
            $error = 'خطا در اتصال به دیتابیس: ' . $e->getMessage();
        }
    }
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
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border: none; border-radius: 8px;
            font-size: 16px; font-family: Tahoma, sans-serif;
            font-weight: bold; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); }
        .error {
            background: #f8d7da; color: #721c24; padding: 12px 15px;
            border-radius: 8px; margin-bottom: 20px; font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        .hint {
            margin-top: 20px; padding: 12px; background: #e7f3ff;
            border: 1px solid #b3d9ff; border-radius: 8px;
            color: #0066cc; font-size: 12px; text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">🔐</div>
        <h1>ورود به پنل مدیریت</h1>
        <p class="subtitle">لطفاً اطلاعات حساب خود را وارد کنید</p>

        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="username">نام کاربری</label>
                <input type="text" id="username" name="username"
                       placeholder="نام کاربری خود را وارد کنید"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password"
                       placeholder="رمز عبور خود را وارد کنید" required>
            </div>
            <button type="submit" class="btn-login">ورود به پنل</button>
        </form>

        <div class="hint">
            💡 اطلاعات پیش‌فرض: <strong>admin</strong> / <strong>admin123</strong>
        </div>
    </div>
</body>
</html>

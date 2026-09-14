<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'رمز عبور فعلی اشتباه است';
    } elseif (strlen($new) < 6) {
        $error = 'رمز جدید حداقل ۶ کاراکتر';
    } elseif ($new !== $confirm) {
        $error = 'تکرار رمز مطابقت ندارد';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
        $success = 'رمز عبور با موفقیت تغییر کرد';
    }
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <title>تغییر رمز | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; max-width: 600px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; border-radius: 10px 10px 0 0 !important; padding: 15px 20px; font-weight: bold; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><h5>👤 پنل کاربری</h5></div>
    <a href="index.php"><i class="bi bi-speedometer2"></i> <span><?= __t('fe_dashboard', [], 'داشبورد') ?></span></a>
    <a href="profile.php"><i class="bi bi-person"></i> <span><?= __t('fe_profile', [], 'پروفایل') ?></span></a>
    <a href="password.php" class="active"><i class="bi bi-key"></i> <span><?= __t('fe_change_password', [], 'تغییر رمز') ?></span></a>
    <a href="posts.php"><i class="bi bi-file-text"></i> <span><?= __t('fe_my_posts', [], 'مقالات من') ?></span></a>
    <a href="comments.php"><i class="bi bi-chat-dots"></i> <span><?= __t('fe_my_comments', [], 'دیدگاه‌های من') ?></span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span><?= __t('fe_logout', [], 'خروج') ?></span></a>
</div>

<div class="main">
    <h4 class="mb-4">🔑 تغییر رمز عبور</h4>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-key-fill"></i> رمز عبور</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">رمز فعلی *</label>
                    <input type="password" name="current" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">رمز جدید *</label>
                    <input type="password" name="new" class="form-control" required>
                    <small class="text-muted">حداقل ۶ کاراکتر</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">تکرار رمز جدید *</label>
                    <input type="password" name="confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> تغییر رمز</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

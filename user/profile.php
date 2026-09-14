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

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');

    $errors = [];
    if (empty($username)) $errors[] = 'نام کاربری الزامی است';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر الزامی است';

    // چک تکراری
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'نام کاربری یا ایمیل قبلاً استفاده شده';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET username = ?, email = ?, bio = ?, phone = ?, website = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $bio ?: null, $phone ?: null, $website ?: null, $userId]);

            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            $success = 'پروفایل با موفقیت ذخیره شد';
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'پروفایل من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; border-radius: 10px 10px 0 0 !important; padding: 15px 20px; font-weight: bold; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar .brand small, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><h5>👤 پنل کاربری</h5><small><?= htmlspecialchars($siteName) ?></small></div>
    <a href="index.php"><i class="bi bi-speedometer2"></i> <span><?= __t('fe_dashboard', [], 'داشبورد') ?></span></a>
    <a href="profile.php" class="active"><i class="bi bi-person"></i> <span><?= __t('fe_profile', [], 'پروفایل') ?></span></a>
    <a href="password.php"><i class="bi bi-key"></i> <span><?= __t('fe_change_password', [], 'تغییر رمز') ?></span></a>
    <a href="posts.php"><i class="bi bi-file-text"></i> <span><?= __t('fe_my_posts', [], 'مقالات من') ?></span></a>
    <a href="comments.php"><i class="bi bi-chat-dots"></i> <span><?= __t('fe_my_comments', [], 'دیدگاه‌های من') ?></span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span><?= __t('fe_logout', [], 'خروج') ?></span></a>
</div>

<div class="main">
    <h4 class="mb-4">👤 پروفایل من</h4>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-pencil"></i> اطلاعات شخصی</div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نام کاربری *</label>
                        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ایمیل *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __t('fe_phone', [], 'شماره تماس') ?></label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __t('fe_website', [], 'وب‌سایت') ?></label>
                        <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($user['website'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">درباره من</label>
                    <textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> ذخیره</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

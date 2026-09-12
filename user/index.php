<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];

// اطلاعات کاربر
$stmt = $pdo->prepare("
    SELECT u.*, r.name as role_name, r.slug as role_slug 
    FROM users u 
    LEFT JOIN roles r ON r.id = u.role_id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// آمار کاربر
$stats = [
    'posts' => (int) $pdo->query("SELECT COUNT(*) FROM content_items WHERE author_id = {$userId}")->fetchColumn(),
    'comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE user_id = {$userId}")->fetchColumn(),
];

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'پنل کاربری';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar .brand h5 { margin: 0; font-size: 15px; }
        .sidebar .brand small { opacity: 0.6; font-size: 11px; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .profile-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border-radius: 15px; padding: 30px;
            margin-bottom: 25px; text-align: center;
        }
        .profile-avatar {
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 40px; margin: 0 auto 15px;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .stat-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; }
        .stat-card .num { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-card .label { font-size: 12px; color: #7f8c8d; }
        .action-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-decoration: none; color: #2c3e50; display: block; transition: all 0.2s; }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); color: #3498db; }
        .action-card .icon { font-size: 32px; margin-bottom: 10px; color: #3498db; }
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
    <div class="brand">
        <h5>👤 پنل کاربری</h5>
        <small><?= htmlspecialchars($siteName) ?></small>
    </div>
    <a href="index.php" class="active"><i class="bi bi-speedometer2"></i> <span>داشبورد</span></a>
    <a href="profile.php"><i class="bi bi-person"></i> <span>پروفایل</span></a>
    <a href="password.php"><i class="bi bi-key"></i> <span>تغییر رمز</span></a>
    <a href="posts.php"><i class="bi bi-file-text"></i> <span>مقالات من</span></a>
    <a href="comments.php"><i class="bi bi-chat-dots"></i> <span>دیدگاه‌های من</span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'editor', 'author'])): ?>
        <a href="../admin/index.php"><i class="bi bi-speedometer"></i> <span>پنل مدیریت</span></a>
    <?php endif; ?>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span>خروج</span></a>
</div>

<div class="main">
    <div class="profile-card">
        <div class="profile-avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars($user['avatar']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
            <?php else: ?>
                <?= mb_substr($user['username'], 0, 1) ?>
            <?php endif; ?>
        </div>
        <h3 style="margin:0 0 5px;"><?= htmlspecialchars($user['username']) ?></h3>
        <p style="margin:0;opacity:0.9;font-size:13px;">
            <?= htmlspecialchars($user['email']) ?>
            | <?= htmlspecialchars($user['role_name'] ?? 'کاربر') ?>
        </p>
        <?php if (!empty($user['last_login'])): ?>
            <small style="opacity:0.7;font-size:11px;">آخرین ورود: <?= date('Y/m/d H:i', strtotime($user['last_login'])) ?></small>
        <?php endif; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#667eea,#764ba2);"><i class="bi bi-file-text"></i></div>
                <div>
                    <div class="num"><?= $stats['posts'] ?></div>
                    <div class="label">مقاله</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#11998e,#38ef7d);"><i class="bi bi-chat-dots"></i></div>
                <div>
                    <div class="num"><?= $stats['comments'] ?></div>
                    <div class="label">دیدگاه</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-3">
            <a href="profile.php" class="action-card">
                <div class="icon"><i class="bi bi-person-circle"></i></div>
                <h5>پروفایل</h5>
                <small class="text-muted">ویرایش اطلاعات شخصی</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="password.php" class="action-card">
                <div class="icon"><i class="bi bi-key-fill"></i></div>
                <h5>تغییر رمز</h5>
                <small class="text-muted">بروزرسانی رمز عبور</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="posts.php" class="action-card">
                <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                <h5>مقالات من</h5>
                <small class="text-muted"><?= $stats['posts'] ?> مقاله</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="comments.php" class="action-card">
                <div class="icon"><i class="bi bi-chat-square-text"></i></div>
                <h5>دیدگاه‌های من</h5>
                <small class="text-muted"><?= $stats['comments'] ?> دیدگاه</small>
            </a>
        </div>
    </div>
</div>

</body>
</html>

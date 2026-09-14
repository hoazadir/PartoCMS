<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$siteName = getSetting('site_name', 'وب‌سایت من');

$posts = $pdo->prepare("
    SELECT ci.*, ct.name as type_name, ct.icon as type_icon, c.name as category_name, c.color as category_color
    FROM content_items ci
    LEFT JOIN content_types ct ON ct.id = ci.type_id
    LEFT JOIN categories c ON c.id = ci.category_id
    WHERE ci.author_id = ?
    ORDER BY ci.created_at DESC
");
$posts->execute([$userId]);
$posts = $posts->fetchAll();
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <title>مقالات من | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
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
    <a href="password.php"><i class="bi bi-key"></i> <span><?= __t('fe_change_password', [], 'تغییر رمز') ?></span></a>
    <a href="posts.php" class="active"><i class="bi bi-file-text"></i> <span><?= __t('fe_my_posts', [], 'مقالات من') ?></span></a>
    <a href="comments.php"><i class="bi bi-chat-dots"></i> <span><?= __t('fe_my_comments', [], 'دیدگاه‌های من') ?></span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span><?= __t('fe_logout', [], 'خروج') ?></span></a>
</div>

<div class="main">
    <h4 class="mb-4">📄 مقالات من (<?= count($posts) ?>)</h4>

    <?php if (empty($posts)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            هنوز مقاله‌ای نساخته‌اید
            <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'editor', 'author'])): ?>
                <br><a href="../modules/content/admin.php" class="btn btn-primary mt-3">➕ ساخت مقاله جدید</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($posts as $p): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-<?= ['published'=>'success','draft'=>'warning','archived'=>'secondary'][$p['status']] ?? 'secondary' ?>">
                                    <?= ['published'=>'منتشر','draft'=>'پیش‌نویس','archived'=>'بایگانی'][$p['status']] ?? $p['status'] ?>
                                </span>
                                <small class="text-muted"><?= date('Y/m/d', strtotime($p['created_at'])) ?></small>
                            </div>
                            <h5 style="color:#2c3e50;"><?= htmlspecialchars($p['title']) ?></h5>
                            <?php if ($p['category_name']): ?>
                                <span class="badge" style="background:<?= htmlspecialchars($p['category_color']) ?>;">
                                    <?= htmlspecialchars($p['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            <div class="mt-2">
                                <a href="../post.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><?= __t('fe_view', [], 'مشاهده') ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

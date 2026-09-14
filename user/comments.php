<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$siteName = getSetting('site_name', 'وب‌سایت من');

$comments = $pdo->prepare("
    SELECT c.*, ci.title as post_title, ci.id as post_id
    FROM comments c
    LEFT JOIN content_items ci ON ci.id = c.content_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$comments->execute([$userId]);
$comments = $comments->fetchAll();

$statusLabels = [
    'pending' => ['label' => 'در انتظار', 'class' => 'warning'],
    'approved' => ['label' => 'تایید شده', 'class' => 'success'],
    'spam' => ['label' => 'اسپم', 'class' => 'danger'],
    'rejected' => ['label' => 'رد شده', 'class' => 'secondary'],
];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <title>دیدگاه‌های من | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .comment-card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
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
    <a href="posts.php"><i class="bi bi-file-text"></i> <span><?= __t('fe_my_posts', [], 'مقالات من') ?></span></a>
    <a href="comments.php" class="active"><i class="bi bi-chat-dots"></i> <span><?= __t('fe_my_comments', [], 'دیدگاه‌های من') ?></span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span><?= __t('fe_logout', [], 'خروج') ?></span></a>
</div>

<div class="main">
    <h4 class="mb-4">💬 دیدگاه‌های من (<?= count($comments) ?>)</h4>

    <?php if (empty($comments)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-chat-square fs-1 d-block mb-2"></i>
            هنوز دیدگاهی ثبت نکرده‌اید
        </div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => 'secondary']; ?>
            <div class="comment-card">
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <strong><?= htmlspecialchars($c['post_title'] ?? 'مقاله حذف شده') ?></strong>
                        <?php if ($c['post_id']): ?>
                            <a href="../post.php?id=<?= $c['post_id'] ?>" target="_blank" class="ms-2 small">مشاهده مقاله</a>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span>
                </div>
                <div style="background:#f8f9fa;padding:12px;border-radius:8px;line-height:1.8;font-size:14px;">
                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                </div>
                <small class="text-muted d-block mt-2">📅 <?= date('Y/m/d H:i', strtotime($c['created_at'])) ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>

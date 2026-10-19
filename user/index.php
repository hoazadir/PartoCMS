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


<?php require_once 'includes/header.php'; ?>


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
                <h5><?= __t('fe_profile', [], 'پروفایل') ?></h5>
                <small class="text-muted"><?= __t('fe_edit_profile', [], 'ویرایش اطلاعات شخصی') ?></small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="password.php" class="action-card">
                <div class="icon"><i class="bi bi-key-fill"></i></div>
                <h5><?= __t('fe_change_password', [], 'تغییر رمز') ?></h5>
                <small class="text-muted">بروزرسانی رمز عبور</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="posts.php" class="action-card">
                <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
                <h5><?= __t('fe_my_posts', [], 'مقالات من') ?></h5>
                <small class="text-muted"><?= $stats['posts'] ?> مقاله</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="comments.php" class="action-card">
                <div class="icon"><i class="bi bi-chat-square-text"></i></div>
                <h5><?= __t('fe_my_comments', [], 'دیدگاه‌های من') ?></h5>
                <small class="text-muted"><?= $stats['comments'] ?> دیدگاه</small>
            </a>
        </div>
    </div>
</div>



</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

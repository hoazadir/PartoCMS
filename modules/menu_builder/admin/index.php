<?php
require_once dirname(__DIR__, 3) . '/config.php';
if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }
$pdo = getDB();
$menus = $pdo->query("SELECT m.*, (SELECT COUNT(*) FROM menu_items WHERE menu_id=m.id) as items_count FROM menus m ORDER BY m.id")->fetchAll();
$siteName = getSetting('site_name', 'وب‌سایت من');
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>
<div class="main-content">
    <h2 class="mb-4">🎨 منوساز حرفه‌ای</h2>
    <div class="row g-3">
        <?php foreach ($menus as $m): ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body text-center">
                        <h5 class="fw-bold"><?= htmlspecialchars($m['name']) ?></h5>
                        <p class="text-muted mb-2">موقعیت: <?= htmlspecialchars($m['location']) ?></p>
                        <p class="text-muted mb-3"><small><?= $m['items_count'] ?> آیتم</small></p>
                        <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-primary w-100">✏️ ویرایش منو</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>

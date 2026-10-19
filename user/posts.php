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


<?php require_once 'includes/header.php'; ?>


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



</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

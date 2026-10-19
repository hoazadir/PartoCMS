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


<?php require_once 'includes/header.php'; ?>


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



</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

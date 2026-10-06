<?php
/**
 * PartoCMS - Reviews Admin
 * لیست همه نظرات
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ReviewManager.php';
require_once __DIR__ . '/../includes/ReviewRenderer.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$manager = new ReviewManager($pdo);

$status = $_GET['status'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reviewId = (int) ($_POST['review_id'] ?? 0);

    if ($reviewId > 0) {
        if ($action === 'approve') {
            $manager->updateStatus($reviewId, 'approved', $_SESSION['user_id'] ?? null);
            $message = 'نظر تأیید شد';
        } elseif ($action === 'reject') {
            $manager->updateStatus($reviewId, 'rejected', $_SESSION['user_id'] ?? null);
            $message = 'نظر رد شد';
        } elseif ($action === 'spam') {
            $manager->updateStatus($reviewId, 'spam', $_SESSION['user_id'] ?? null);
            $message = 'به عنوان اسپم علامت خورد';
        } elseif ($action === 'delete') {
            $manager->delete($reviewId);
            $message = 'نظر حذف شد';
        }
    }
}

$where = [];
$params = [];

if ($status !== 'all' && in_array($status, ReviewManager::ALLOWED_STATUS)) {
    $where[] = "r.status = ?";
    $params[] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r {$whereSql}");
$countStmt->execute($params);
$totalReviews = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalReviews / $perPage));

$sql = "SELECT r.*, u.username AS user_username
        FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id
        {$whereSql}
        ORDER BY r.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'all'      => (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'pending'  => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
    'approved' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn(),
    'rejected' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'rejected'")->fetchColumn(),
    'spam'     => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'spam'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نظرات — PartoCMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<style>
body { background: #f1f5f9; font-family: Tahoma, sans-serif; margin: 0; padding: 25px; }
.wrap { max-width: 1200px; margin: 0 auto; }
h1 { font-size: 22px; color: #1e293b; margin: 0 0 20px; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
.stat { background: #fff; border-radius: 10px; padding: 15px; text-align: center; text-decoration: none; color: inherit; border: 2px solid transparent; }
.stat:hover { border-color: #3b82f6; }
.stat.active { border-color: #3b82f6; background: #eff6ff; }
.stat .num { font-size: 24px; font-weight: bold; }
.stat .lbl { font-size: 12px; color: #64748b; }
.card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.hdr { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; gap: 15px; flex-wrap: wrap; }
.author { display: flex; align-items: center; gap: 12px; }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
.b-pending { background: #fef3c7; color: #92400e; }
.b-approved { background: #dcfce7; color: #166534; }
.b-rejected { background: #fee2e2; color: #991b1b; }
.b-spam { background: #f3e8ff; color: #6b21a8; }
.meta { display: flex; gap: 15px; font-size: 12px; color: #94a3b8; margin-top: 10px; flex-wrap: wrap; }
.content { color: #475569; line-height: 1.7; margin: 12px 0; }
.actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
.btn { padding: 6px 14px; border-radius: 6px; border: none; font-size: 12px; font-weight: bold; cursor: pointer; }
.b-approve { background: #16a34a; color: #fff; }
.b-reject  { background: #dc2626; color: #fff; }
.b-spam    { background: #9333ea; color: #fff; }
.b-delete  { background: #f1f5f9; color: #991b1b; }
.empty { background: #fff; border-radius: 12px; padding: 50px; text-align: center; color: #94a3b8; }
.pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; }
.pagination a, .pagination span { padding: 8px 14px; background: #fff; color: #334155; text-decoration: none; border-radius: 6px; font-size: 13px; border: 1px solid #e2e8f0; }
.pagination .active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.msg { background: #dcfce7; color: #166534; padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>⭐ مدیریت نظرات</h1>

    <?php if ($message): ?>
        <div class="msg">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="stats">
        <a href="?status=all" class="stat <?= $status === 'all' ? 'active' : '' ?>">
            <div class="num"><?= $stats['all'] ?></div>
            <div class="lbl">همه</div>
        </a>
        <a href="?status=pending" class="stat <?= $status === 'pending' ? 'active' : '' ?>">
            <div class="num" style="color:#d97706;"><?= $stats['pending'] ?></div>
            <div class="lbl">⏳ در انتظار</div>
        </a>
        <a href="?status=approved" class="stat <?= $status === 'approved' ? 'active' : '' ?>">
            <div class="num" style="color:#16a34a;"><?= $stats['approved'] ?></div>
            <div class="lbl">✅ تأیید</div>
        </a>
        <a href="?status=rejected" class="stat <?= $status === 'rejected' ? 'active' : '' ?>">
            <div class="num" style="color:#dc2626;"><?= $stats['rejected'] ?></div>
            <div class="lbl">❌ رد</div>
        </a>
        <a href="?status=spam" class="stat <?= $status === 'spam' ? 'active' : '' ?>">
            <div class="num" style="color:#9333ea;"><?= $stats['spam'] ?></div>
            <div class="lbl">🚫 اسپم</div>
        </a>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="empty">
            <div style="font-size: 50px;">💭</div>
            <h3>هیچ نظری یافت نشد</h3>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <?php
            $authorName = htmlspecialchars($r['author_name'] ?? '');
            $initial = mb_substr($authorName, 0, 1);
            $rating = (int) ($r['rating'] ?? 0);
            $statusLabels = [
                'pending' => '⏳ در انتظار',
                'approved' => '✅ تأیید شده',
                'rejected' => '❌ رد شده',
                'spam' => '🚫 اسپم',
            ];
            ?>
            <div class="card">
                <div class="hdr">
                    <div class="author">
                        <div class="avatar"><?= $initial ?></div>
                        <div>
                            <strong><?= $authorName ?></strong>
                            <?php if ($rating > 0): ?>
                                <div style="color:#fbbf24;"><?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge b-<?= htmlspecialchars($r['status']) ?>">
                        <?= $statusLabels[$r['status']] ?? $r['status'] ?>
                    </span>
                </div>

                <div class="meta">
                    <span>📦 <?= htmlspecialchars($r['entity_type']) ?> #<?= (int) $r['entity_id'] ?></span>
                    <span>📅 <?= date('Y/m/d H:i', strtotime($r['created_at'])) ?></span>
                    <?php if (!empty($r['author_email'])): ?>
                        <span>✉️ <?= htmlspecialchars($r['author_email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>

                <div class="actions">
                    <?php if ($r['status'] !== 'approved'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                            <button class="btn b-approve">✅ تأیید</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($r['status'] !== 'rejected'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                            <button class="btn b-reject">❌ رد</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($r['status'] !== 'spam'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="spam">
                            <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                            <button class="btn b-spam">🚫 اسپم</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" style="display:inline;" onsubmit="return confirm('مطمئنید؟');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                        <button class="btn b-delete">🗑 حذف</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?status=<?= urlencode($status) ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

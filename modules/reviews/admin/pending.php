<?php
/**
 * PartoCMS - Reviews Admin - Pending Reviews
 * مدیریت نظرات در انتظار تأیید (نسخه حرفه‌ای)
 *
 * @author Hooman Oliaei
 * @version 1.0.0
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

$message = '';
$error = '';

// ─── پردازش اکشن‌ها ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ids = [];

    // اکشن گروهی
    if (!empty($_POST['bulk_ids']) && is_array($_POST['bulk_ids'])) {
        $ids = array_map('intval', $_POST['bulk_ids']);
    }
    // اکشن تک
    elseif (!empty($_POST['review_id'])) {
        $ids = [(int) $_POST['review_id']];
    }

    $ids = array_filter($ids, fn($id) => $id > 0);

    if (!empty($ids)) {
        try {
            $count = 0;
            foreach ($ids as $id) {
                if ($action === 'approve') {
                    $manager->updateStatus($id, 'approved', $_SESSION['user_id'] ?? null);
                    $count++;
                } elseif ($action === 'reject') {
                    $manager->updateStatus($id, 'rejected', $_SESSION['user_id'] ?? null);
                    $count++;
                } elseif ($action === 'spam') {
                    $manager->updateStatus($id, 'spam', $_SESSION['user_id'] ?? null);
                    $count++;
                } elseif ($action === 'delete') {
                    $manager->delete($id);
                    $count++;
                }
            }

            $actionLabels = [
                'approve' => 'تأیید',
                'reject'  => 'رد',
                'spam'    => 'علامت‌گذاری به‌عنوان اسپم',
                'delete'  => 'حذف',
            ];
            $message = $count . ' نظر با موفقیت ' . ($actionLabels[$action] ?? 'پردازش') . ' شد';
        } catch (Throwable $e) {
            $error = 'خطا در پردازش: ' . $e->getMessage();
        }
    }
}

// ─── صفحه‌بندی ───
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// ─── دریافت نظرات در انتظار ───
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
$countStmt->execute();
$totalPending = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalPending / $perPage));

$sql = "SELECT r.*, u.username AS user_username
        FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── آمار کلی ───
$stats = [
    'pending'  => $totalPending,
    'today'    => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending' AND DATE(created_at) = CURDATE()")->fetchColumn(),
    'approved' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn(),
    'avg_time' => $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, approved_at))
        FROM reviews
        WHERE status = 'approved' AND approved_at IS NOT NULL
    ")->fetchColumn(),
];
$stats['avg_time'] = $stats['avg_time'] ? round((float) $stats['avg_time']) : null;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>در انتظار تأیید — PartoCMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    * { box-sizing: border-box; }
    body { background: #f1f5f9; font-family: Tahoma, 'Vazirmatn', sans-serif; margin: 0; padding: 25px; color: #1e293b; }
    .wrap { max-width: 1100px; margin: 0 auto; }

    /* ─── Header ─── */
    .page-hdr {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
        border-radius: 14px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-hdr h1 { margin: 0 0 8px; font-size: 24px; font-weight: bold; }
    .page-hdr p { margin: 0; opacity: 0.9; font-size: 13.5px; }
    .hdr-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-hdr {
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: 2px solid rgba(255,255,255,0.4);
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 13.5px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-hdr:hover { background: rgba(255,255,255,0.3); color: #fff; border-color: #fff; }

    /* ─── Stats ─── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 18px 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 15px;
        border-right: 4px solid #f59e0b;
        transition: all 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #fef3c7;
        color: #d97706;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }
    .stat-info .num { font-size: 22px; font-weight: bold; color: #1e293b; line-height: 1.2; }
    .stat-info .lbl { font-size: 12px; color: #64748b; margin-top: 3px; }

    /* ─── Bulk Actions Bar ─── */
    .bulk-bar {
        background: #fff;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: sticky;
        top: 15px;
        z-index: 10;
        border: 2px solid #e2e8f0;
        transition: all 0.2s;
    }
    .bulk-bar.active {
        border-color: #3b82f6;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
    }
    .bulk-bar .selected-count {
        font-weight: bold;
        color: #1e293b;
        font-size: 14px;
        padding: 6px 14px;
        background: #eff6ff;
        color: #1e40af;
        border-radius: 8px;
    }
    .btn-bulk {
        padding: 8px 16px;
        border-radius: 8px;
        border: none;
        font-weight: bold;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
        font-family: inherit;
    }
    .btn-bulk:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .btn-bulk-approve { background: #16a34a; color: #fff; }
    .btn-bulk-reject  { background: #dc2626; color: #fff; }
    .btn-bulk-spam    { background: #9333ea; color: #fff; }
    .btn-bulk-delete  { background: #475569; color: #fff; }
    .select-all-btn {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #e2e8f0;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 12.5px;
        cursor: pointer;
        font-family: inherit;
    }

    /* ─── Review Card ─── */
    .review-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border-right: 4px solid #f59e0b;
        transition: all 0.2s;
        position: relative;
    }
    .review-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
    .review-card.selected { border-right-color: #3b82f6; background: #f0f9ff; }

    .card-hdr {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-bottom: 15px;
    }
    .checkbox-wrap {
        padding-top: 10px;
        flex-shrink: 0;
    }
    .checkbox-wrap input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #3b82f6;
    }
    .avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 20px;
        flex-shrink: 0;
    }
    .author-info { flex: 1; min-width: 0; }
    .author-info .name { font-weight: bold; color: #1e293b; font-size: 15px; }
    .author-info .email { color: #64748b; font-size: 12.5px; margin-top: 2px; font-family: monospace; direction: ltr; text-align: right; }
    .card-actions-top {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .stars { color: #fbbf24; font-size: 16px; letter-spacing: 2px; }

    .meta-row {
        display: flex;
        gap: 18px;
        font-size: 12.5px;
        color: #94a3b8;
        margin: 12px 0;
        flex-wrap: wrap;
        padding: 10px 12px;
        background: #f8fafc;
        border-radius: 8px;
    }
    .meta-row span { display: inline-flex; align-items: center; gap: 5px; }

    .review-content {
        color: #334155;
        line-height: 1.8;
        font-size: 14px;
        padding: 15px 0;
        border-top: 1px dashed #e2e8f0;
        border-bottom: 1px dashed #e2e8f0;
        margin: 15px 0;
    }
    .review-content.empty-content { color: #94a3b8; font-style: italic; }

    .btn-action {
        padding: 8px 16px;
        border-radius: 8px;
        border: none;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
        font-family: inherit;
    }
    .btn-action:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,0.12); }
    .btn-approve { background: #16a34a; color: #fff; }
    .btn-reject  { background: #dc2626; color: #fff; }
    .btn-spam    { background: #9333ea; color: #fff; }
    .btn-delete  { background: #f1f5f9; color: #991b1b; border: 1px solid #fecaca; }

    /* ─── Empty State ─── */
    .empty {
        background: #fff;
        border-radius: 14px;
        padding: 60px 30px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .empty .icon {
        font-size: 70px;
        margin-bottom: 15px;
        display: block;
    }
    .empty h2 { color: #1e293b; font-size: 20px; margin: 0 0 10px; }
    .empty p { color: #64748b; margin: 0; font-size: 14px; }

    /* ─── Pagination ─── */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 6px;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        padding: 10px 16px;
        background: #fff;
        color: #334155;
        text-decoration: none;
        border-radius: 8px;
        font-size: 13.5px;
        border: 1px solid #e2e8f0;
        transition: all 0.15s;
    }
    .pagination a:hover { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
    .pagination .active { background: #3b82f6; color: #fff; border-color: #3b82f6; font-weight: bold; }

    /* ─── Messages ─── */
    .alert {
        padding: 14px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .alert-success { background: #dcfce7; color: #166534; border-right: 4px solid #16a34a; }
    .alert-error   { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }

    /* ─── Mobile ─── */
    @media (max-width: 700px) {
        body { padding: 15px; }
        .page-hdr { padding: 20px; }
        .page-hdr h1 { font-size: 20px; }
        .bulk-bar { position: static; }
        .btn-action { flex: 1; justify-content: center; }
    }
</style>
</head>
<body>
<div class="wrap">

    <!-- ═══ Header ═══ -->
    <div class="page-hdr">
        <div>
            <h1>⏳ نظرات در انتظار تأیید</h1>
            <p>بررسی و تأیید نظرات کاربران قبل از انتشار</p>
        </div>
        <div class="hdr-actions">
            <a href="index.php" class="btn-hdr"><i class="bi bi-list"></i> همه نظرات</a>
            <a href="<?= ADMIN_URL ?>/index.php" class="btn-hdr"><i class="bi bi-house"></i> داشبورد</a>
        </div>
    </div>

    <!-- ═══ Messages ═══ -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ═══ Stats ═══ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['pending']) ?></div>
                <div class="lbl">در انتظار تأیید</div>
            </div>
        </div>
        <div class="stat-card" style="border-right-color: #3b82f6;">
            <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">📅</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['today']) ?></div>
                <div class="lbl">امروز</div>
            </div>
        </div>
        <div class="stat-card" style="border-right-color: #16a34a;">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['approved']) ?></div>
                <div class="lbl">کل تأییدشده‌ها</div>
            </div>
        </div>
        <?php if ($stats['avg_time'] !== null): ?>
        <div class="stat-card" style="border-right-color: #8b5cf6;">
            <div class="stat-icon" style="background: #f3e8ff; color: #8b5cf6;">⚡</div>
            <div class="stat-info">
                <div class="num"><?= $stats['avg_time'] ?> دقیقه</div>
                <div class="lbl">میانگین زمان تأیید</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($reviews)): ?>

        <!-- ═══ Empty State ═══ -->
        <div class="empty">
            <span class="icon">🎉</span>
            <h2>هیچ نظر در انتظاری نیست!</h2>
            <p>همه نظرات بررسی شده‌اند. کار خوبی انجام دادی 👏</p>
            <div style="margin-top: 25px;">
                <a href="index.php" style="background: #3b82f6; color: #fff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;">
                    <i class="bi bi-list"></i> مشاهده همه نظرات
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- ═══ Bulk Actions Bar ═══ -->
        <form method="post" id="bulkForm">
            <input type="hidden" name="action" id="bulkAction" value="">

            <div class="bulk-bar" id="bulkBar">
                <button type="button" class="select-all-btn" onclick="toggleSelectAll()" id="selectAllBtn">
                    <i class="bi bi-check2-square"></i> انتخاب همه
                </button>

                <span class="selected-count" id="selectedCount" style="display: none;">
                    0 مورد انتخاب شده
                </span>

                <div style="display: flex; gap: 8px; margin-right: auto;" id="bulkActions" hidden>
                    <button type="button" class="btn-bulk btn-bulk-approve" onclick="bulkAction('approve')">
                        <i class="bi bi-check-lg"></i> تأیید همه
                    </button>
                    <button type="button" class="btn-bulk btn-bulk-reject" onclick="bulkAction('reject')">
                        <i class="bi bi-x-lg"></i> رد همه
                    </button>
                    <button type="button" class="btn-bulk btn-bulk-spam" onclick="bulkAction('spam')">
                        <i class="bi bi-shield-x"></i> اسپم
                    </button>
                    <button type="button" class="btn-bulk btn-bulk-delete" onclick="bulkAction('delete')">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>

            <!-- ═══ Reviews List ═══ -->
            <?php foreach ($reviews as $r): ?>
                <?php
                $authorName = htmlspecialchars($r['author_name'] ?? 'ناشناس');
                $initial = mb_substr($authorName, 0, 1);
                $rating = (int) ($r['rating'] ?? 0);
                $createdAt = $r['created_at'] ?? '';
                $date = $createdAt ? date('Y/m/d H:i', strtotime($createdAt)) : '';

                // زمان گذشته
                $diff = $createdAt ? (time() - strtotime($createdAt)) : 0;
                if ($diff < 60)          $timeAgo = 'همین الان';
                elseif ($diff < 3600)    $timeAgo = round($diff / 60) . ' دقیقه پیش';
                elseif ($diff < 86400)   $timeAgo = round($diff / 3600) . ' ساعت پیش';
                elseif ($diff < 2592000) $timeAgo = round($diff / 86400) . ' روز پیش';
                else                     $timeAgo = date('Y/m/d', strtotime($createdAt));
                ?>
                <div class="review-card" data-review-card>
                    <div class="card-hdr">
                        <div class="checkbox-wrap">
                            <input type="checkbox" name="bulk_ids[]" value="<?= (int) $r['id'] ?>"
                                   class="review-checkbox" onchange="updateBulkState()">
                        </div>

                        <div class="avatar"><?= $initial ?></div>

                        <div class="author-info">
                            <div class="name"><?= $authorName ?></div>
                            <?php if (!empty($r['author_email'])): ?>
                                <div class="email"><i class="bi bi-envelope"></i> <?= htmlspecialchars($r['author_email']) ?></div>
                            <?php endif; ?>
                            <?php if ($rating > 0): ?>
                                <div class="stars" style="margin-top: 5px;">
                                    <?= str_repeat('★', $rating) ?><?= str_repeat('☆', 5 - $rating) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions-top">
                            <button type="button" class="btn-action btn-approve"
                                    onclick="singleAction(<?= (int) $r['id'] ?>, 'approve')">
                                <i class="bi bi-check-lg"></i> تأیید
                            </button>
                            <button type="button" class="btn-action btn-reject"
                                    onclick="singleAction(<?= (int) $r['id'] ?>, 'reject')">
                                <i class="bi bi-x-lg"></i> رد
                            </button>
                            <button type="button" class="btn-action btn-spam"
                                    onclick="singleAction(<?= (int) $r['id'] ?>, 'spam')">
                                <i class="bi bi-shield-x"></i>
                            </button>
                            <button type="button" class="btn-action btn-delete"
                                    onclick="singleAction(<?= (int) $r['id'] ?>, 'delete')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="meta-row">
                        <span><i class="bi bi-box"></i> <?= htmlspecialchars($r['entity_type']) ?> #<?= (int) $r['entity_id'] ?></span>
                        <span><i class="bi bi-clock"></i> <?= $timeAgo ?></span>
                        <span><i class="bi bi-calendar"></i> <?= $date ?></span>
                        <?php if (!empty($r['author_ip'])): ?>
                            <span><i class="bi bi-globe"></i> <?= htmlspecialchars($r['author_ip']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($r['language'])): ?>
                            <span><i class="bi bi-translate"></i> <?= htmlspecialchars($r['language']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="review-content">
                        <?php if (!empty($r['title'])): ?>
                            <strong style="color: #1e293b; display: block; margin-bottom: 6px;">
                                <?= htmlspecialchars($r['title']) ?>
                            </strong>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($r['content'] ?? '')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </form>

        <!-- ═══ Pagination ═══ -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-right"></i> قبلی</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);

                if ($start > 1) echo '<a href="?page=1">1</a>';
                if ($start > 2) echo '<span>...</span>';

                for ($i = $start; $i <= $end; $i++):
                    ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $totalPages - 1) echo '<span>...</span>'; ?>
                <?php if ($end < $totalPages) echo '<a href="?page=' . $totalPages . '">' . $totalPages . '</a>'; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>">بعدی <i class="bi bi-chevron-left"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
// ─── اکشن تک ───
function singleAction(reviewId, action) {
    const labels = {
        'approve': 'تأیید',
        'reject': 'رد',
        'spam': 'اسپم',
        'delete': 'حذف'
    };

    if (action === 'delete' && !confirm('این نظر حذف شود؟')) return;
    if (action === 'spam' && !confirm('به‌عنوان اسپم علامت زده شود؟')) return;

    const form = document.getElementById('bulkForm');
    document.getElementById('bulkAction').value = action;

    // حذف چک‌باکس‌های دیگر
    form.querySelectorAll('.review-checkbox').forEach(cb => {
        cb.checked = false;
        cb.disabled = true;
    });

    // چک کردن فقط این نظر
    const targetCheckbox = form.querySelector('input[value="' + reviewId + '"]');
    if (targetCheckbox) {
        targetCheckbox.checked = true;
        targetCheckbox.disabled = false;
    }

    form.submit();
}

// ─── اکشن گروهی ───
function bulkAction(action) {
    const selected = document.querySelectorAll('.review-checkbox:checked');
    if (selected.length === 0) {
        alert('لطفاً حداقل یک نظر انتخاب کنید');
        return;
    }

    const messages = {
        'approve': selected.length + ' نظر تأیید شود؟',
        'reject':  selected.length + ' نظر رد شود؟',
        'spam':    selected.length + ' نظر به‌عنوان اسپم علامت زده شود؟',
        'delete':  selected.length + ' نظر برای همیشه حذف شود؟'
    };

    if (!confirm(messages[action])) return;

    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkForm').submit();
}

// ─── انتخاب همه ───
let allSelected = false;
function toggleSelectAll() {
    allSelected = !allSelected;
    document.querySelectorAll('.review-checkbox').forEach(cb => {
        cb.checked = allSelected;
    });
    updateBulkState();
}

// ─── بروزرسانی وضعیت ───
function updateBulkState() {
    const checked = document.querySelectorAll('.review-checkbox:checked');
    const bar = document.getElementById('bulkBar');
    const countEl = document.getElementById('selectedCount');
    const actions = document.getElementById('bulkActions');
    const selectAllBtn = document.getElementById('selectAllBtn');

    if (checked.length > 0) {
        bar.classList.add('active');
        countEl.style.display = 'inline-block';
        countEl.textContent = checked.length + ' مورد انتخاب شده';
        actions.hidden = false;
    } else {
        bar.classList.remove('active');
        countEl.style.display = 'none';
        actions.hidden = true;
    }

    // بروزرسانی دکمه "انتخاب همه"
    const all = document.querySelectorAll('.review-checkbox');
    allSelected = (checked.length === all.length && all.length > 0);
    selectAllBtn.innerHTML = allSelected
        ? '<i class="bi bi-x-square"></i> لغو انتخاب'
        : '<i class="bi bi-check2-square"></i> انتخاب همه';
}
</script>

</body>
</html>

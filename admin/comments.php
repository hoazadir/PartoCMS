<?php
require_once __DIR__ . '/../config.php';

// اول چک کن لاگین هست یا نه
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
if (!$perm->can($_SESSION['user_id'], 'content.view')) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';

// تغییر وضعیت
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $status = $_GET['status'];
    if (in_array($status, ['pending', 'approved', 'spam', 'rejected'])) {
        $pdo->prepare("UPDATE comments SET status = ? WHERE id = ?")->execute([$status, $id]);
        header('Location: comments.php?msg=updated');
        exit;
    }
}

// حذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: comments.php?msg=deleted');
    exit;
}

// پاسخ ادمین
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reply_text'])) {
    $parentId = (int) $_POST['parent_id'];
    $text = trim($_POST['reply_text']);
    $contentId = (int) $_POST['content_id'];

    if ($text) {
        $pdo->prepare("
            INSERT INTO comments (content_id, parent_id, author_name, author_email, comment, status, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, 'approved', ?, NOW())
        ")->execute([
            $contentId,
            $parentId,
            $_SESSION['username'],
            $_SESSION['email'] ?? 'admin@site.com',
            $text,
            $_SESSION['user_id']
        ]);
        header('Location: comments.php?msg=replied');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['updated' => 'وضعیت تغییر کرد', 'deleted' => 'حذف شد', 'replied' => 'پاسخ ثبت شد'];
    $success = $msgs[$_GET['msg']] ?? '';
}

// فیلتر
$filter = $_GET['filter'] ?? 'all';
$where = '';
$params = [];
if (in_array($filter, ['pending', 'approved', 'spam', 'rejected'])) {
    $where = "WHERE c.status = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT c.*, ci.title as post_title, ci.id as post_id
    FROM comments c
    LEFT JOIN content_items ci ON ci.id = c.content_id
    $where
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$comments = $stmt->fetchAll();

// آمار
$stats = [];
foreach (['all', 'pending', 'approved', 'spam'] as $s) {
    if ($s === 'all') {
        $stats[$s] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    } else {
        $st = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE status = ?");
        $st->execute([$s]);
        $stats[$s] = $st->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دیدگاه‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .stat-btn { background: #fff; border-radius: 10px; padding: 12px 18px; margin-bottom: 15px; text-decoration: none; color: #2c3e50; display: block; border: 2px solid transparent; transition: all 0.2s; }
        .stat-btn:hover { border-color: #3498db; }
        .stat-btn.active { border-color: #3498db; background: #e7f1ff; }
        .stat-btn .num { font-size: 22px; font-weight: bold; }
        .stat-btn .label { font-size: 12px; opacity: 0.7; }
        .comment-box { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-right: 4px solid #ccc; }
        .comment-box.pending { border-right-color: #f39c12; }
        .comment-box.approved { border-right-color: #27ae60; }
        .comment-box.spam { border-right-color: #e74c3c; }
        .comment-box.rejected { border-right-color: #95a5a6; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">💬 مدیریت دیدگاه‌ها</h4>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <a href="?filter=all" class="stat-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <div class="num"><?= $stats['all'] ?></div>
                <div class="label">همه</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?filter=pending" class="stat-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                <div class="num" style="color:#f39c12;"><?= $stats['pending'] ?></div>
                <div class="label">در انتظار تأیید</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?filter=approved" class="stat-btn <?= $filter === 'approved' ? 'active' : '' ?>">
                <div class="num" style="color:#27ae60;"><?= $stats['approved'] ?></div>
                <div class="label">تأیید شده</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="?filter=spam" class="stat-btn <?= $filter === 'spam' ? 'active' : '' ?>">
                <div class="num" style="color:#e74c3c;"><?= $stats['spam'] ?></div>
                <div class="label">اسپم</div>
            </a>
        </div>
    </div>

    <?php if (empty($comments)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-chat-square fs-1 d-block mb-3"></i>
            هنوز دیدگاهی ثبت نشده است
        </div>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="comment-box <?= htmlspecialchars($c['status']) ?>">
                <div style="display:flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <strong style="color:#2c3e50; font-size:15px;"><?= htmlspecialchars($c['author_name']) ?></strong>
                        <div style="font-size:12px; color:#7f8c8d; margin-top:3px;">
                            <?= htmlspecialchars($c['author_email']) ?>
                            | IP: <code><?= htmlspecialchars($c['author_ip'] ?? '-') ?></code>
                        </div>
                        <?php if ($c['post_title']): ?>
                            <div style="font-size:12px; margin-top:5px;">
                                📄 <a href="../post.php?id=<?= $c['post_id'] ?>" target="_blank" style="color:#3498db;"><?= htmlspecialchars($c['post_title']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php
                        $badges = [
                            'pending' => '<span class="badge bg-warning">⏳ در انتظار</span>',
                            'approved' => '<span class="badge bg-success">✅ تأیید شده</span>',
                            'spam' => '<span class="badge bg-danger">🚫 اسپم</span>',
                            'rejected' => '<span class="badge bg-secondary">❌ رد شده</span>',
                        ];
                        echo $badges[$c['status']] ?? '';
                        ?>
                        <div style="font-size:11px; color:#95a5a6; margin-top:5px;"><?= date('Y/m/d H:i', strtotime($c['created_at'])) ?></div>
                    </div>
                </div>

                <div style="background:#f8f9fa; padding:15px; border-radius:8px; line-height:1.8; font-size:14px; color:#333; margin-bottom:12px;">
                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                </div>

                <div style="display:flex; gap:5px; flex-wrap: wrap;">
                    <?php if ($c['status'] !== 'approved'): ?>
                        <a href="?id=<?= $c['id'] ?>&status=approved&filter=<?= $filter ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-check-circle"></i> تأیید
                        </a>
                    <?php endif; ?>
                    <?php if ($c['status'] !== 'pending'): ?>
                        <a href="?id=<?= $c['id'] ?>&status=pending&filter=<?= $filter ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-clock"></i> در انتظار
                        </a>
                    <?php endif; ?>
                    <?php if ($c['status'] !== 'spam'): ?>
                        <a href="?id=<?= $c['id'] ?>&status=spam&filter=<?= $filter ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-x-octagon"></i> اسپم
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('reply-<?= $c['id'] ?>').style.display='block'">
                        <i class="bi bi-reply"></i> پاسخ
                    </button>
                    <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف دیدگاه؟')">
                        <i class="bi bi-trash"></i> حذف
                    </a>
                </div>

                <div id="reply-<?= $c['id'] ?>" style="display:none; margin-top:12px;">
                    <form method="post">
                        <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="content_id" value="<?= $c['content_id'] ?>">
                        <textarea name="reply_text" class="form-control mb-2" rows="3" placeholder="پاسخ شما..." required></textarea>
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-send"></i> ارسال</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('reply-<?= $c['id'] ?>').style.display='none'">انصراف</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>

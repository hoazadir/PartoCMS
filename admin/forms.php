<?php
require_once __DIR__ . '/../config.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('content.view');

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// حذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM forms WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: forms.php?msg=deleted');
    exit;
}

// فعال/غیرفعال
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE forms SET is_active = NOT is_active WHERE id = ?")->execute([$_GET['toggle']]);
    header('Location: forms.php?msg=toggled');
    exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['deleted' => 'فرم حذف شد', 'toggled' => 'وضعیت تغییر کرد', 'created' => 'فرم ساخته شد', 'updated' => 'فرم ویرایش شد'];
    $success = $msgs[$_GET['msg']] ?? '';
}

// لیست فرم‌ها
$forms = $pdo->query("
    SELECT f.*, u.username as creator,
        (SELECT COUNT(*) FROM form_submissions WHERE form_id = f.id) as submissions_count,
        (SELECT COUNT(*) FROM form_submissions WHERE form_id = f.id AND status = 'new') as new_count
    FROM forms f
    LEFT JOIN users u ON u.id = f.created_by
    ORDER BY f.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت فرم‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .form-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border-right: 4px solid #3498db; }
        .form-card.inactive { border-right-color: #95a5a6; opacity: 0.7; }
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
        <h4 style="margin:0;">📋 مدیریت فرم‌ها</h4>
        <a href="form-edit.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> فرم جدید</a>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($forms)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-ui-checks fs-1 d-block mb-3"></i>
            <h5>هنوز فرمی ساخته نشده</h5>
            <a href="form-edit.php" class="btn btn-primary mt-3">➕ ساخت اولین فرم</a>
        </div>
    <?php else: ?>
        <?php foreach ($forms as $f): ?>
            <div class="form-card <?= !$f['is_active'] ? 'inactive' : '' ?>">
                <div style="flex: 1;">
                    <h5 style="margin: 0 0 8px; color: #2c3e50;">
                        📋 <?= htmlspecialchars($f['name']) ?>
                        <?php if (!$f['is_active']): ?>
                            <span class="badge bg-secondary">غیرفعال</span>
                        <?php endif; ?>
                        <?php if ($f['new_count'] > 0): ?>
                            <span class="badge bg-danger"><?= $f['new_count'] ?> جدید</span>
                        <?php endif; ?>
                    </h5>
                    <div style="font-size: 12px; color: #7f8c8d; line-height: 1.7;">
                        <code><?= htmlspecialchars($f['slug']) ?></code>
                        <?php if ($f['description']): ?>
                            | <?= htmlspecialchars($f['description']) ?>
                        <?php endif; ?>
                        <br>
                        👤 سازنده: <?= htmlspecialchars($f['creator'] ?? 'ناشناس') ?>
                        | 📅 <?= date('Y/m/d', strtotime($f['created_at'])) ?>
                        | 📨 <?= $f['submissions_count'] ?> ارسال
                    </div>
                </div>
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <a href="form-edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="form-submissions.php?form_id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-info" title="مشاهده ارسال‌ها">
                        <i class="bi bi-inbox"></i> <?= $f['submissions_count'] ?>
                    </a>
                    <a href="../form.php?slug=<?= urlencode($f['slug']) ?>" class="btn btn-sm btn-outline-success" target="_blank" title="پیش‌نمایش">
                        <i class="bi bi-eye"></i>
                    </a>
                    <a href="?toggle=<?= $f['id'] ?>" class="btn btn-sm btn-outline-warning" title="فعال/غیرفعال">
                        <i class="bi bi-<?= $f['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                    </a>
                    <a href="?delete=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف فرم و همه ارسال‌هایش؟')" title="حذف">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>

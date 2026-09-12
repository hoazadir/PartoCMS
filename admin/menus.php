<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('content.view');

$pdo = getDB();
$mm = getMenuManager();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';

// حذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM menus WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: menus.php?msg=deleted');
    exit;
}

// فعال/غیرفعال
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE menus SET is_active = NOT is_active WHERE id = ?")->execute([$_GET['toggle']]);
    header('Location: menus.php?msg=toggled');
    exit;
}

// ایجاد منوی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_name'])) {
    $name = trim($_POST['new_name']);
    $slug = trim($_POST['new_slug'] ?? '');
    $location = $_POST['location'] ?? 'header';
    $description = trim($_POST['description'] ?? '');

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO menus (name, slug, location, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $location, $description]);
        header('Location: menu-edit.php?id=' . $pdo->lastInsertId());
        exit;
    } catch (PDOException $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['deleted' => 'منو حذف شد', 'toggled' => 'وضعیت تغییر کرد', 'saved' => 'منو ذخیره شد'];
    $success = $msgs[$_GET['msg']] ?? '';
}

$menus = $mm->getAll();
$locations = [
    'header' => '🔝 هدر (بالای سایت)',
    'footer' => '🔻 فوتر (پاورقی)',
    'sidebar' => '📑 سایدبار',
    'mobile' => '📱 موبایل',
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت منوها | <?= htmlspecialchars($siteName) ?></title>
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
        .menu-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border-right: 4px solid #3498db; }
        .menu-card.inactive { border-right-color: #95a5a6; opacity: 0.7; }
        .location-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background: #e7f1ff; color: #2980b9; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">📋 مدیریت منوها</h4>
        <button class="btn btn-primary" onclick="document.getElementById('newMenuForm').style.display='block'; this.style.display='none';">
            <i class="bi bi-plus-lg"></i> منوی جدید
        </button>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- فرم ساخت منو -->
    <div class="card mb-4" id="newMenuForm" style="display:none;">
        <div class="card-header" style="background:#fff;border-bottom:1px solid #eee;padding:15px 20px;border-radius:10px 10px 0 0;font-weight:bold;">
            ➕ ساخت منوی جدید
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نام منو <span class="text-danger">*</span></label>
                        <input type="text" name="new_name" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نامک (slug)</label>
                        <input type="text" name="new_slug" class="form-control" placeholder="خودکار تولید می‌شود">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">مکان نمایش</label>
                        <select name="location" class="form-select">
                            <?php foreach ($locations as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">توضیحات</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> ساخت منو</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newMenuForm').style.display='none';">انصراف</button>
            </form>
        </div>
    </div>

    <!-- لیست منوها -->
    <?php if (empty($menus)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-list-nested fs-1 d-block mb-3"></i>
            هنوز منویی ساخته نشده
        </div>
    <?php else: ?>
        <?php foreach ($menus as $m): ?>
            <div class="menu-card <?= !$m['is_active'] ? 'inactive' : '' ?>">
                <div style="flex: 1;">
                    <h5 style="margin: 0 0 8px; color: #2c3e50;">
                        📋 <?= htmlspecialchars($m['name']) ?>
                        <?php if (!$m['is_active']): ?>
                            <span class="badge bg-secondary">غیرفعال</span>
                        <?php endif; ?>
                    </h5>
                    <div style="font-size: 12px; color: #7f8c8d;">
                        <code><?= htmlspecialchars($m['slug']) ?></code>
                        <span class="location-badge"><?= $locations[$m['location']] ?? $m['location'] ?></span>
                        | 🎯 <?= $m['items_count'] ?> آیتم
                        | 📅 <?= date('Y/m/d', strtotime($m['created_at'])) ?>
                    </div>
                </div>
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                    <a href="menu-edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil"></i> ویرایش
                    </a>
                    <a href="?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-<?= $m['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                    </a>
                    <a href="?delete=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف منو و همه آیتم‌هایش؟')">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>

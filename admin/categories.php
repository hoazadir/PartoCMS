<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
// چک لاگین
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
$error = '';

// ==================== حذف ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // بررسی محتوای متصل
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_items WHERE category_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $error = "این دسته به {$count} محتوا متصل است و قابل حذف نیست";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        header('Location: categories.php?msg=deleted');
        exit;
    }
}

// ==================== ذخیره ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $isNew = empty($id);

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '📁');
    $color = trim($_POST['color'] ?? '#3498db');
    $parentId = $_POST['parent_id'] ? (int) $_POST['parent_id'] : null;
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $error = 'نام دسته الزامی است';
    } else {
        // تولید slug خودکار از نام
        if (empty($slug)) {
            $slug = str_replace([' ', '،', '.', '?', '!'], '-', $name);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        }

        try {
            $sql = "SELECT id FROM categories WHERE slug = ?";
            $params = [$slug];
            if (!$isNew) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $error = 'این نامک قبلاً استفاده شده است';
            } else {
                if ($isNew) {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, icon, color, parent_id, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $description, $icon, $color, $parentId, $sortOrder, $isActive]);
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, icon=?, color=?, parent_id=?, sort_order=?, is_active=? WHERE id=?");
                    $stmt->execute([$name, $slug, $description, $icon, $color, $parentId, $sortOrder, $isActive, $id]);
                }
                header('Location: categories.php?msg=saved');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'deleted' ? 'دسته حذف شد' : 'دسته ذخیره شد';
}

// ==================== داده‌ها ====================
$categories = $pdo->query("
    SELECT c.*, 
        (SELECT COUNT(*) FROM content_items WHERE category_id = c.id) as content_count,
        p.name as parent_name
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت دسته‌بندی‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; margin-bottom: 20px; }
        .cat-card { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; border-right: 4px solid #3498db; }
        .cat-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .cat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar .brand small, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">🏷 مدیریت دسته‌بندی‌ها</h4>
        <button class="btn btn-primary" onclick="showForm()"><i class="bi bi-plus-lg"></i> دسته جدید</button>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- فرم -->
    <div class="card" id="formCard" style="display: <?= $edit ? 'block' : 'none' ?>;">
        <div class="card-header" style="padding:15px 20px;border-bottom:1px solid #eee;background:#fff;border-radius:10px 10px 0 0;">
            <strong><?= $edit ? '✏️ ویرایش دسته' : '➕ دسته جدید' ?></strong>
        </div>
        <div class="card-body">
            <form method="post">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نام دسته <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نامک (slug)</label>
                        <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($edit['slug'] ?? '') ?>" placeholder="خودکار تولید می‌شود">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">آیکون (ایموجی)</label>
                        <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($edit['icon'] ?? '📁') ?>" maxlength="4">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">رنگ</label>
                        <input type="color" name="color" class="form-control form-control-color" value="<?= htmlspecialchars($edit['color'] ?? '#3498db') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">دسته والد</label>
                        <select name="parent_id" class="form-select">
                            <option value="">— بدون والد —</option>
                            <?php foreach ($categories as $c): ?>
                                <?php if ($edit && $c['id'] == $edit['id']) continue; ?>
                                <option value="<?= $c['id'] ?>" <?= ($edit['parent_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">ترتیب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= $edit['sort_order'] ?? 0 ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">توضیحات</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">دسته فعال باشد</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> ذخیره</button>
                <button type="button" class="btn btn-secondary" onclick="hideForm()">انصراف</button>
            </form>
        </div>
    </div>

    <!-- لیست -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($categories)): ?>
                <p class="text-center text-muted py-5">هیچ دسته‌ای وجود ندارد</p>
            <?php else: ?>
                <?php foreach ($categories as $c): ?>
                    <div class="cat-card" style="border-right-color: <?= htmlspecialchars($c['color']) ?>;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="cat-icon" style="background: <?= htmlspecialchars($c['color']) ?>;">
                                <?= htmlspecialchars($c['icon']) ?>
                            </div>
                            <div>
                                <h6 style="margin:0;color:#2c3e50;">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <?php if (!$c['is_active']): ?><span class="badge bg-secondary">غیرفعال</span><?php endif; ?>
                                </h6>
                                <small class="text-muted">
                                    <code><?= htmlspecialchars($c['slug']) ?></code>
                                    <?php if ($c['parent_name']): ?>
                                        | والد: <?= htmlspecialchars($c['parent_name']) ?>
                                    <?php endif; ?>
                                    | 📝 <?= $c['content_count'] ?> محتوا
                                </small>
                            </div>
                        </div>
                        <div>
                            <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <?php if ($c['slug'] !== 'uncategorized'): ?>
                                <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('حذف؟')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showForm() { document.getElementById('formCard').style.display = 'block'; window.scrollTo({top:0,behavior:'smooth'}); }
function hideForm() { window.location.href = 'categories.php'; }
<?php if ($edit): ?>window.onload = () => window.scrollTo({top:0,behavior:'smooth'});<?php endif; ?>
</script>
</body>
</html>

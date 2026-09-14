<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('roles.manage');

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');

$success = '';
$error = '';

// ==================== حذف نقش ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $roleId = (int) $_GET['delete'];

    $stmt = $pdo->prepare("SELECT is_system, name FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();

    if (!$role) {
        $error = 'نقش یافت نشد';
    } elseif ($role['is_system']) {
        $error = 'نقش‌های سیستمی قابل حذف نیستند';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $userCount = $stmt->fetchColumn();

        if ($userCount > 0) {
            $error = "این نقش به {$userCount} کاربر متصل است و قابل حذف نیست";
        } else {
            try {
                $pdo->prepare("DELETE FROM role_modules WHERE role_id = ?")->execute([$roleId]);
            } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);
            header('Location: roles.php?msg=deleted');
            exit;
        }
    }
}

// ==================== ذخیره نقش ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $isNew = empty($id);

    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $modules = $_POST['modules'] ?? [];

    // اعتبارسنجی
    if (empty($name)) {
        $error = 'نام نقش الزامی است';
    } elseif (empty($slug)) {
        $error = 'نامک (slug) الزامی است';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
        $error = 'نامک فقط می‌تواند شامل حروف کوچک انگلیسی، اعداد، خط تیره و آندرلاین باشد';
    } else {
        try {
            $checkSql = "SELECT id FROM roles WHERE slug = ?" . ($id ? " AND id != ?" : "");
            $checkParams = $id ? [$slug, $id] : [$slug];
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute($checkParams);

            if ($stmt->fetch()) {
                $error = 'این نامک قبلاً استفاده شده است';
            } else {
                $pdo->beginTransaction();

                if ($isNew) {
                    $stmt = $pdo->prepare("INSERT INTO roles (name, slug, description, is_system) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$name, $slug, $description]);
                    $roleId = $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE roles SET name = ?, slug = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $id]);
                    $roleId = $id;

                    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
                }

                // افزودن دسترسی‌های جدید (permissions)
                if (!empty($permissions)) {
                    $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    foreach ($permissions as $permId) {
                        $stmt->execute([$roleId, (int) $permId]);
                    }
                }

                // ذخیره دسترسی ماژول‌ها
                $mm = getModuleManager();
                $mm->setRoleModules($roleId, $modules);

                $pdo->commit();
                header('Location: roles.php?msg=saved');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'خطا: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'deleted' ? 'نقش حذف شد' : ($_GET['msg'] === 'saved' ? 'نقش ذخیره شد' : '');
}

// ==================== داده‌ها ====================
$roles = $pdo->query("
    SELECT r.*,
        (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count,
        (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as perm_count
    FROM roles r
    ORDER BY r.is_system DESC, r.id ASC
")->fetchAll();

// گروه‌بندی دسترسی‌ها
$permissions = $pdo->query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
$permissionsByModule = [];
foreach ($permissions as $p) {
    $permissionsByModule[$p['module']][] = $p;
}

$moduleLabels = [
    'dashboard' => '📊 داشبورد',
    'users' => '👥 کاربران',
    'roles' => '🛡 نقش‌ها',
    'content' => '📝 محتوا',
    'templates' => '🎨 قالب‌ها',
    'modules' => '🧩 ماژول‌ها',
    'forms' => '📋 فرم‌ها',
    'settings' => '⚙️ تنظیمات',
];

// ✅ اصلاح باگ: مقداردهی اولیه
$edit = null;
$editPermissions = [];
$editModules = [];
$allModules = [];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();

    if ($edit) {
        $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$edit['id']]);
        $editPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // ✅ دریافت ماژول‌ها فقط اگه edit داریم
        try {
            $editModules = getModuleManager()->getRoleModules($edit['id']);
        } catch (Exception $e) {
            $editModules = [];
        }
    }
}

// ✅ دریافت همه ماژول‌ها (همیشه)
try {
    $allModules = getModuleManager()->getAll();
} catch (Exception $e) {
    $allModules = [];
}

// گروه‌بندی ماژول‌ها
$moduleGroups = [];
foreach ($allModules as $mod) {
    $g = $mod['menu_group'] ?? 'other';
    $moduleGroups[$g][] = $mod;
}

$moduleGroupLabels = [
    'content' => '📝 مدیریت محتوا',
    'tools' => '🧩 ابزارها',
    'appearance' => '🎨 ظاهر سایت',
    'reports' => '📈 گزارش‌گیری',
    'seo' => '🔍 SEO',
    'admin' => '⚙️ سیستم',
    'other' => '📦 سایر',
];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت نقش‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .top-bar h4 { margin: 0; color: #2c3e50; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; padding: 15px 20px; border-radius: 12px 12px 0 0 !important; font-weight: bold; color: #2c3e50; }
        .role-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 15px; border-right: 4px solid #3498db; transition: all 0.2s; }
        .role-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .role-card.system { border-right-color: #e74c3c; }
        .role-card .role-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; }
        .role-card .role-name { font-size: 16px; font-weight: bold; color: #2c3e50; margin: 0 0 5px 0; }
        .role-card .role-slug { font-family: monospace; background: #ecf0f1; padding: 2px 8px; border-radius: 4px; font-size: 11px; color: #7f8c8d; }
        .role-card .role-desc { color: #7f8c8d; font-size: 13px; margin: 8px 0 0 0; }
        .role-stats { display: flex; gap: 15px; margin-top: 10px; font-size: 12px; color: #95a5a6; }
        .role-stats span { display: flex; align-items: center; gap: 4px; }
        .permission-group { margin-bottom: 20px; }
        .permission-group .group-title { background: #2c3e50; color: #fff; padding: 8px 15px; border-radius: 6px; font-size: 13px; font-weight: bold; margin-bottom: 10px; }
        .permission-item { display: flex; align-items: center; gap: 10px; padding: 10px 15px; background: #f8f9fa; border-radius: 6px; margin-bottom: 6px; cursor: pointer; transition: background 0.2s; border: 2px solid transparent; }
        .permission-item:hover { background: #e9ecef; }
        .permission-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .permission-item.checked { background: #d4edda; border-color: #27ae60; }
        .permission-item .perm-name { flex-grow: 1; font-size: 13px; }
        .permission-item .perm-slug { font-family: monospace; font-size: 10px; color: #95a5a6; background: #fff; padding: 2px 6px; border-radius: 4px; }
        .badge-system { background: #e74c3c; }
        .badge-custom { background: #3498db; }

        /* ==================== بخش ماژول‌ها ==================== */
        .modules-section { margin-top: 30px; border-top: 2px solid #f0f0f0; padding-top: 25px; }
        .modules-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .modules-section-header h5 { margin: 0; color: #2c3e50; font-size: 15px; }
        .module-group-box {
            background: #f8fafc; border-radius: 10px; padding: 15px;
            margin-bottom: 15px; border: 1px solid #e2e8f0;
        }
        .module-group-box h6 {
            margin: 0 0 12px; color: #475569; font-size: 13px;
            padding-bottom: 8px; border-bottom: 1px solid #e2e8f0;
            font-weight: bold;
        }
        .module-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; background: #fff; border-radius: 6px;
            margin-bottom: 6px; cursor: pointer; transition: all 0.2s;
            border: 2px solid #e2e8f0; font-size: 13px;
        }
        .module-item:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .module-item.checked { background: #dcfce7; border-color: #10b981; }
        .module-item.disabled { opacity: 0.5; cursor: not-allowed; }
        .module-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .module-item .module-icon { font-size: 18px; }
        .module-item .module-name { flex-grow: 1; font-weight: 500; }
        .module-item .module-badge {
            font-size: 9px; padding: 2px 8px; border-radius: 10px;
            font-weight: bold;
        }
        .badge-core { background: #dbeafe; color: #1e40af; }
        .badge-off { background: #f1f5f9; color: #64748b; }

        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h4>🛡 مدیریت نقش‌ها و دسترسی‌ها</h4>
        <button class="btn btn-primary" onclick="showForm()">
            <i class="bi bi-plus-lg"></i> نقش جدید
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- فرم نقش -->
    <div class="card" id="formCard" style="display: <?= $edit ? 'block' : 'none' ?>;">
        <div class="card-header">
            <i class="bi bi-shield-plus"></i>
            <?= $edit ? 'ویرایش نقش: ' . htmlspecialchars($edit['name']) : 'ایجاد نقش جدید' ?>
        </div>
        <div class="card-body">
            <form method="post" id="roleForm">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نام نقش <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= htmlspecialchars($edit['name'] ?? '') ?>"
                               placeholder="مثلاً: مدیر فروش">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">نامک (slug) <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required
                               pattern="[a-z0-9_-]+"
                               value="<?= htmlspecialchars($edit['slug'] ?? '') ?>"
                               placeholder="مثلاً: sales_manager"
                               <?= $edit && $edit['is_system'] ? 'readonly' : '' ?>>
                        <small class="text-muted">فقط حروف کوچک انگلیسی، عدد، خط تیره</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">توضیحات</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= htmlspecialchars($edit['description'] ?? '') ?>"
                               placeholder="توضیح کوتاه">
                    </div>
                </div>

                <!-- ==================== بخش ۱: دسترسی‌های دقیق ==================== -->
                <h5 class="mt-4 mb-3" style="color:#2c3e50;">
                    <i class="bi bi-key"></i> دسترسی‌های دقیق
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllPerms()">
                        انتخاب / لغو همه
                    </button>
                </h5>

                <div class="row">
                    <?php foreach ($permissionsByModule as $module => $perms): ?>
                        <div class="col-md-6 permission-group">
                            <div class="group-title">
                                <?= $moduleLabels[$module] ?? $module ?>
                            </div>
                            <?php foreach ($perms as $p): ?>
                                <label class="permission-item <?= in_array($p['id'], $editPermissions) ? 'checked' : '' ?>">
                                    <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>"
                                           <?= in_array($p['id'], $editPermissions) ? 'checked' : '' ?>
                                           onchange="this.closest('.permission-item').classList.toggle('checked', this.checked)">
                                    <span class="perm-name"><?= htmlspecialchars($p['name']) ?></span>
                                    <span class="perm-slug"><?= htmlspecialchars($p['slug']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ==================== بخش ۲: دسترسی به ماژول‌ها ==================== -->
                <?php if (!empty($moduleGroups)): ?>
                <div class="modules-section">
                    <div class="modules-section-header">
                        <h5>
                            <i class="bi bi-grid-3x3-gap"></i> دسترسی به ماژول‌ها
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllModules()">
                            انتخاب / لغو همه
                        </button>
                    </div>

                    <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">
                        انتخاب کن این نقش به چه ماژول‌هایی دسترسی داشته باشه. 
                        <strong>توجه:</strong> ماژول‌های غیرفعال سیستم، حتی اگه اینجا انتخاب بشن، نمایش داده نمی‌شن.
                    </p>

                    <div class="row">
                        <?php foreach ($moduleGroups as $groupKey => $mods): ?>
                            <div class="col-md-6 mb-3">
                                <div class="module-group-box">
                                    <h6><?= $moduleGroupLabels[$groupKey] ?? $groupKey ?></h6>
                                    <?php foreach ($mods as $mod): ?>
                                        <?php 
                                        $hasAccess = isset($editModules[$mod['slug']]) && $editModules[$mod['slug']] == 1;
                                        $isCoreMod = (int) $mod['is_core'] === 1;
                                        $isEnabled = (int) $mod['is_enabled'] === 1;
                                        ?>
                                        <label class="module-item <?= $hasAccess ? 'checked' : '' ?> <?= !$isEnabled ? 'disabled' : '' ?>">
                                            <input type="checkbox" 
                                                   name="modules[]" 
                                                   value="<?= htmlspecialchars($mod['slug']) ?>"
                                                   <?= $hasAccess ? 'checked' : '' ?>
                                                   <?= !$isEnabled ? 'disabled' : '' ?>
                                                   onchange="this.closest('.module-item').classList.toggle('checked', this.checked)">
                                            <span class="module-icon"><?= htmlspecialchars($mod['icon'] ?: '🧩') ?></span>
                                            <span class="module-name"><?= htmlspecialchars($mod['name']) ?></span>
                                            <?php if ($isCoreMod): ?>
                                                <span class="module-badge badge-core">🔒 اصلی</span>
                                            <?php endif; ?>
                                            <?php if (!$isEnabled): ?>
                                                <span class="module-badge badge-off">غیرفعال</span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-4">
                    ⚠️ جدول `role_modules` خالی است یا ماژولی ثبت نشده. 
                    لطفاً اول از <a href="modules.php">صفحه ماژول‌ها</a> استفاده کن.
                </div>
                <?php endif; ?>

                <hr class="mt-4">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-save"></i> ذخیره نقش
                </button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="hideForm()">انصراف</button>
            </form>
        </div>
    </div>

    <!-- لیست نقش‌ها -->
    <div class="row">
        <?php foreach ($roles as $role): ?>
            <div class="col-md-6">
                <div class="role-card <?= $role['is_system'] ? 'system' : '' ?>">
                    <div class="role-header">
                        <div style="flex:1;min-width:200px;">
                            <h5 class="role-name">
                                <?= htmlspecialchars($role['name']) ?>
                                <?php if ($role['is_system']): ?>
                                    <span class="badge badge-system">سیستمی</span>
                                <?php else: ?>
                                    <span class="badge badge-custom">سفارشی</span>
                                <?php endif; ?>
                            </h5>
                            <span class="role-slug"><?= htmlspecialchars($role['slug']) ?></span>
                            <?php if ($role['description']): ?>
                                <p class="role-desc"><?= htmlspecialchars($role['description']) ?></p>
                            <?php endif; ?>
                            <div class="role-stats">
                                <span><i class="bi bi-people"></i> <?= $role['user_count'] ?> کاربر</span>
                                <span><i class="bi bi-key"></i> <?= $role['perm_count'] ?> دسترسی</span>
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:5px;">
                            <a href="?edit=<?= $role['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> ویرایش
                            </a>
                            <?php if (!$role['is_system']): ?>
                                <a href="?delete=<?= $role['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('حذف نقش «<?= htmlspecialchars($role['name']) ?>»؟')">
                                    <i class="bi bi-trash"></i> حذف
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
function showForm() {
    document.getElementById('formCard').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function hideForm() {
    window.location.href = 'roles.php';
}
function toggleAllPerms() {
    const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
    const allChecked = Array.from(checkboxes).every(c => c.checked);
    checkboxes.forEach(c => {
        c.checked = !allChecked;
        c.closest('.permission-item').classList.toggle('checked', !allChecked);
    });
}
function toggleAllModules() {
    const checkboxes = document.querySelectorAll('input[name="modules[]"]:not(:disabled)');
    const allChecked = Array.from(checkboxes).every(c => c.checked);
    checkboxes.forEach(c => {
        c.checked = !allChecked;
        c.closest('.module-item').classList.toggle('checked', !allChecked);
    });
}
<?php if ($edit): ?>
window.onload = () => window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>

</body>
</html>

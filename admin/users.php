<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('users.manage');

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$currentUserId = $_SESSION['user_id'];

$success = '';
$error = '';

// ==================== حذف کاربر ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int) $_GET['delete'];

    if ($userId === $currentUserId) {
        $error = 'نمی‌توانید حساب خودتان را حذف کنید';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        header('Location: users.php?msg=deleted');
        exit;
    }
}

// ==================== تغییر وضعیت ====================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $userId = (int) $_GET['toggle'];

    if ($userId === $currentUserId) {
        $error = 'نمی‌توانید حساب خودتان را غیرفعال کنید';
    } else {
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
        header('Location: users.php?msg=toggled');
        exit;
    }
}

// ==================== ذخیره کاربر ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $isNew = empty($id);

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // اعتبارسنجی
    $errors = [];
    if (empty($username)) $errors[] = 'نام کاربری الزامی است';
    if (empty($email)) $errors[] = 'ایمیل الزامی است';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر نیست';
    if ($isNew && empty($password)) $errors[] = 'رمز عبور برای کاربر جدید الزامی است';
    if (!$isNew && !empty($password) && strlen($password) < 6) $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد';
    if ($roleId <= 0) $errors[] = 'نقش کاربر را انتخاب کنید';

    // بررسی تکراری نبودن
    if (empty($errors)) {
        $sql = "SELECT id FROM users WHERE (username = ? OR email = ?)";
        $params = [$username, $email];
        if (!$isNew) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'نام کاربری یا ایمیل قبلاً استفاده شده است';
        }
    }

    if (empty($errors)) {
        try {
            if ($isNew) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role_id, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $email, $hashed, $roleId, $isActive]);
                header('Location: users.php?msg=created');
                exit;
            } else {
                // جلوگیری از تغییر نقش خودت
                if ($id == $currentUserId && $roleId != ($_SESSION['role_id'] ?? 0)) {
                    $error = 'نمی‌توانید نقش خودتان را تغییر دهید';
                } else {
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users SET username = ?, email = ?, password = ?, role_id = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $hashed, $roleId, $isActive, $id]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users SET username = ?, email = ?, role_id = ?, is_active = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $email, $roleId, $isActive, $id]);
                    }
                    header('Location: users.php?msg=updated');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

if (isset($_GET['msg'])) {
    $messages = [
        'deleted' => 'کاربر حذف شد',
        'created' => 'کاربر جدید ایجاد شد',
        'updated' => 'کاربر ویرایش شد',
        'toggled' => 'وضعیت کاربر تغییر کرد',
    ];
    $success = $messages[$_GET['msg']] ?? '';
}

// ==================== داده‌ها ====================
$roles = $pdo->query("
    SELECT r.*,
        (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count
    FROM roles r
    ORDER BY r.is_system DESC, r.id ASC
")->fetchAll();

$users = $pdo->query("
    SELECT u.*,
        r.name as role_name,
        r.slug as role_slug,
        r.is_system as role_is_system
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    ORDER BY u.created_at DESC
")->fetchAll();

$stats = [
    'total' => count($users),
    'active' => count(array_filter($users, fn($u) => $u['is_active'])),
    'inactive' => count(array_filter($users, fn($u) => !$u['is_active'])),
    'roles_count' => count($roles),
];

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar .brand h5 { margin: 0; font-size: 16px; }
        .sidebar .brand small { opacity: 0.6; font-size: 11px; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; transition: background 0.2s; }
        .sidebar a:hover { background: #34495e; }
        .sidebar a.active { background: #3498db; border-right: 4px solid #fff; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .top-bar h4 { margin: 0; color: #2c3e50; }
        .stat-card { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; }
        .stat-card .icon.blue { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card .icon.green { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .stat-card .icon.red { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-card .icon.orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card .num { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-card .label { color: #7f8c8d; font-size: 13px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; padding: 15px 20px; border-radius: 10px 10px 0 0 !important; font-weight: bold; color: #2c3e50; }
        .role-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; }
        .role-badge.system { background: #fee; color: #c0392b; border: 1px solid #fcc; }
        .role-badge.custom { background: #e7f1ff; color: #2980b9; border: 1px solid #b8daff; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 15px; flex-shrink: 0; }
        .table { font-size: 13px; }
        .table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; }
        .table td { vertical-align: middle; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar .brand small, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; padding: 14px 5px; }
            .main { margin-right: 60px; padding: 15px; }
            .table { font-size: 11px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h4>👥 مدیریت کاربران</h4>
        <button class="btn btn-primary" onclick="showForm()">
            <i class="bi bi-person-plus"></i> کاربر جدید
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <!-- آمار -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon blue"><i class="bi bi-people"></i></div>
                <div>
                    <div class="num"><?= $stats['total'] ?></div>
                    <div class="label">کل کاربران</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="num"><?= $stats['active'] ?></div>
                    <div class="label">فعال</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon red"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="num"><?= $stats['inactive'] ?></div>
                    <div class="label">غیرفعال</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon orange"><i class="bi bi-shield-check"></i></div>
                <div>
                    <div class="num"><?= $stats['roles_count'] ?></div>
                    <div class="label">نقش‌ها</div>
                </div>
            </div>
        </div>
    </div>

    <!-- فرم کاربر -->
    <div class="card" id="formCard" style="display: <?= $edit ? 'block' : 'none' ?>;">
        <div class="card-header">
            <i class="bi bi-person-plus"></i>
            <?= $edit ? 'ویرایش کاربر: ' . htmlspecialchars($edit['username']) : 'ایجاد کاربر جدید' ?>
        </div>
        <div class="card-body">
            <form method="post" autocomplete="off">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نام کاربری <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= htmlspecialchars($edit['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ایمیل <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            رمز عبور
                            <?php if (!$edit): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <input type="password" name="password" class="form-control"
                               <?= !$edit ? 'required' : '' ?>
                               placeholder="<?= $edit ? 'برای تغییر پر کنید، در غیر این صورت خالی بگذارید' : '' ?>">
                        <?php if ($edit): ?>
                            <small class="text-muted">خالی = بدون تغییر</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نقش کاربر <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">— انتخاب نقش —</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"
                                    <?= ($edit['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['name']) ?>
                                    (<?= htmlspecialchars($role['slug']) ?>)
                                    <?= $role['is_system'] ? '🔒' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            <?= count($roles) ?> نقش در سیستم موجود است
                        </small>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                               <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">
                            <strong>حساب فعال باشد</strong>
                            <small class="text-muted d-block">کاربران غیرفعال نمی‌توانند وارد شوند</small>
                        </label>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> ذخیره
                </button>
                <button type="button" class="btn btn-secondary" onclick="hideForm()">انصراف</button>
            </form>
        </div>
    </div>

    <!-- لیست کاربران -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul"></i> لیست کاربران (<?= count($users) ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>کاربر</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>وضعیت</th>
                            <th>تاریخ عضویت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    هیچ کاربری وجود ندارد
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <?php
                                $colors = [
                                    'admin' => '#e74c3c',
                                    'editor' => '#f39c12',
                                    'author' => '#3498db',
                                    'user' => '#95a5a6',
                                ];
                                $avatarColor = $colors[$u['role_slug'] ?? 'user'] ?? '#7f8c8d';
                                $initial = mb_substr($u['username'], 0, 1);
                                ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar" style="background:<?= $avatarColor ?>;">
                                                <?= htmlspecialchars($initial) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                <?php if ($u['id'] == $currentUserId): ?>
                                                    <span class="badge bg-info" style="font-size:9px;">شما</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['role_name']): ?>
                                            <span class="role-badge <?= $u['role_is_system'] ? 'system' : 'custom' ?>">
                                                <i class="bi bi-<?= $u['role_is_system'] ? 'shield-lock' : 'shield-check' ?>"></i>
                                                <?= htmlspecialchars($u['role_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">— بدون نقش —</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success">فعال</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('Y/m/d', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($u['id'] != $currentUserId): ?>
                                            <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning"
                                               title="<?= $u['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>">
                                                <i class="bi bi-<?= $u['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                            </a>
                                            <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger"
                                               title="حذف"
                                               onclick="return confirm('حذف کاربر «<?= htmlspecialchars($u['username']) ?>»؟')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function showForm() {
    document.getElementById('formCard').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function hideForm() {
    window.location.href = 'users.php';
}
<?php if ($edit): ?>
window.onload = () => window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>

</body>
</html>

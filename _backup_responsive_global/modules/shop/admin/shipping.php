<?php
/**
 * PartoCMS - Shop Admin - Shipping Management
 * مدیریت روش‌های ارسال
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ShopManager.php';
require_once __DIR__ . '/../includes/ShippingManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$shop = new ShopManager($pdo);
$shippingManager = new ShippingManager($pdo);

// ذخیره (افزودن/ویرایش)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'cost' => (float) ($_POST['cost'] ?? 0),
            'free_over' => !empty($_POST['free_over']) ? (float) $_POST['free_over'] : null,
            'min_days' => (int) ($_POST['min_days'] ?? 1),
            'max_days' => (int) ($_POST['max_days'] ?? 7),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ];

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $shippingManager->update($id, $data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'روش ارسال به‌روزرسانی شد'];
        } else {
            $shippingManager->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'روش ارسال ایجاد شد'];
        }
    }
    header('Location: shipping.php');
    exit;
}

// حذف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (getSecurity()->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $shippingManager->delete((int) $_POST['delete_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'روش ارسال حذف شد'];
    }
    header('Location: shipping.php');
    exit;
}

$methods = $shippingManager->getAll(false);
$pageTitle = 'مدیریت روش‌های ارسال';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | PartoCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: Tahoma, sans-serif; }
        .main { margin-right: 260px; padding: 20px; }
        @media (max-width: 900px) { .main { margin-right: 0; } }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-truck"></i> مدیریت روش‌های ارسال</h1>
        <button class="btn btn-primary" onclick="showAddForm()">
            <i class="bi bi-plus"></i> روش ارسال جدید
        </button>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- فرم افزودن/ویرایش -->
    <div class="card mb-4" id="formCard" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0" id="formTitle">روش ارسال جدید</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                <input type="hidden" name="save" value="1">
                <input type="hidden" name="id" id="formId" value="0">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نام روش <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="formName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">هزینه (تومان) <span class="text-danger">*</span></label>
                        <input type="number" name="cost" id="formCost" class="form-control" step="0.01" required value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ارسال رایگان از (تومان)</label>
                        <input type="number" name="free_over" id="formFreeOver" class="form-control" step="0.01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">حداقل روز</label>
                        <input type="number" name="min_days" id="formMinDays" class="form-control" value="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">حداکثر روز</label>
                        <input type="number" name="max_days" id="formMaxDays" class="form-control" value="7">
                    </div>
                    <div class="col-12">
                        <label class="form-label">توضیحات</label>
                        <textarea name="description" id="formDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ترتیب</label>
                        <input type="number" name="sort_order" id="formSortOrder" class="form-control" value="0">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="formIsActive" class="form-check-input" checked>
                            <label class="form-check-label" for="formIsActive">فعال</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> ذخیره
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideForm()">
                            <i class="bi bi-x-circle"></i> انصراف
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- لیست روش‌ها -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($methods)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-truck fs-1"></i>
                    <h5 class="mt-3">هنوز روش ارسالی وجود ندارد</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>نام</th>
                                <th>هزینه</th>
                                <th>ارسال رایگان از</th>
                                <th>زمان تحویل</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methods as $m): ?>
                                <tr>
                                    <td><?= (int) $m['id'] ?></td>
                                    <td><?= htmlspecialchars($m['name']) ?></td>
                                    <td>
                                        <?= $m['cost'] == 0 ? '<span class="text-success">رایگان</span>' : $shop->formatPrice((float) $m['cost']) ?>
                                    </td>
                                    <td>
                                        <?= $m['free_over'] ? $shop->formatPrice((float) $m['free_over']) : '—' ?>
                                    </td>
                                    <td><?= (int) $m['min_days'] ?> - <?= (int) $m['max_days'] ?> روز</td>
                                    <td>
                                        <?php if ($m['is_active']): ?>
                                            <span class="badge bg-success">فعال</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">غیرفعال</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                            onclick='editMethod(<?= json_encode($m, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                                            <input type="hidden" name="csrf_token" value="<?= getSecurity()->csrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= (int) $m['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showAddForm() {
    document.getElementById('formCard').style.display = 'block';
    document.getElementById('formTitle').textContent = 'روش ارسال جدید';
    document.getElementById('formId').value = '0';
    document.getElementById('formName').value = '';
    document.getElementById('formCost').value = '0';
    document.getElementById('formFreeOver').value = '';
    document.getElementById('formMinDays').value = '1';
    document.getElementById('formMaxDays').value = '7';
    document.getElementById('formDescription').value = '';
    document.getElementById('formSortOrder').value = '0';
    document.getElementById('formIsActive').checked = true;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function hideForm() {
    document.getElementById('formCard').style.display = 'none';
}

function editMethod(m) {
    document.getElementById('formCard').style.display = 'block';
    document.getElementById('formTitle').textContent = 'ویرایش روش ارسال';
    document.getElementById('formId').value = m.id;
    document.getElementById('formName').value = m.name || '';
    document.getElementById('formCost').value = m.cost || 0;
    document.getElementById('formFreeOver').value = m.free_over || '';
    document.getElementById('formMinDays').value = m.min_days || 1;
    document.getElementById('formMaxDays').value = m.max_days || 7;
    document.getElementById('formDescription').value = m.description || '';
    document.getElementById('formSortOrder').value = m.sort_order || 0;
    document.getElementById('formIsActive').checked = m.is_active == 1;
    window.scrollTo({top: 0, behavior: 'smooth'});
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

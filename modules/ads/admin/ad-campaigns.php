<?php
/**
 * PartoCMS - Ads Admin - Campaigns
 * مدیریت کمپین‌های تبلیغاتی
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/AdManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$adm = new AdManager($pdo);

$success = '';
$error = '';

// افزودن/ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $data = [
            'name'             => trim($_POST['name'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'advertiser_name'  => trim($_POST['advertiser_name'] ?? ''),
            'advertiser_email' => trim($_POST['advertiser_email'] ?? ''),
            'advertiser_phone' => trim($_POST['advertiser_phone'] ?? ''),
            'budget'           => (float) ($_POST['budget'] ?? 0),
            'start_date'       => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date'         => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'status'           => $_POST['status'] ?? 'draft',
        ];

        if (empty($data['name'])) {
            throw new Exception('نام کمپین اجباری است');
        }

        if ($_POST['action'] === 'add') {
            $data['created_by'] = $_SESSION['user_id'] ?? null;
            $stmt = $pdo->prepare("
                INSERT INTO ad_campaigns
                (name, description, advertiser_name, advertiser_email, advertiser_phone,
                 budget, start_date, end_date, status, created_by)
                VALUES
                (:name, :description, :advertiser_name, :advertiser_email, :advertiser_phone,
                 :budget, :start_date, :end_date, :status, :created_by)
            ");
            $stmt->execute($data);
            $success = 'کمپین جدید اضافه شد';
        } else {
            $id = (int) $_POST['id'];
            $data['id'] = $id;
            $stmt = $pdo->prepare("
                UPDATE ad_campaigns SET
                    name = :name, description = :description,
                    advertiser_name = :advertiser_name, advertiser_email = :advertiser_email,
                    advertiser_phone = :advertiser_phone, budget = :budget,
                    start_date = :start_date, end_date = :end_date, status = :status
                WHERE id = :id
            ");
            $stmt->execute($data);
            $success = 'کمپین به‌روزرسانی شد';
        }
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

// حذف
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    try {
        $pdo->prepare("UPDATE ads SET campaign_id = NULL WHERE campaign_id = ?")->execute([(int) $_GET['id']]);
        $pdo->prepare("DELETE FROM ad_campaigns WHERE id = ?")->execute([(int) $_GET['id']]);
        header('Location: ad-campaigns.php?msg=deleted');
        exit;
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

$campaigns = $adm->getCampaigns();

$pageTitle = 'کمپین‌های تبلیغاتی';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);

$msg = $_GET['msg'] ?? '';

$statusLabels = [
    'draft'     => ['label' => 'پیش‌نویس', 'class' => 'secondary'],
    'active'    => ['label' => 'فعال',     'class' => 'success'],
    'paused'    => ['label' => 'متوقف',    'class' => 'warning'],
    'completed' => ['label' => 'تکمیل',    'class' => 'info'],
    'cancelled' => ['label' => 'لغو شده',  'class' => 'danger'],
];
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
        <h1><i class="bi bi-diagram-3"></i> کمپین‌های تبلیغاتی</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal" onclick="resetForm()">
            <i class="bi bi-plus-circle"></i> کمپین جدید
        </button>
    </div>

    <?php if ($msg === 'deleted'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> کمپین حذف شد</div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($campaigns)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-diagram-3" style="font-size:48px;"></i>
                    <p class="mt-3">هنوز کمپینی ثبت نشده</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus"></i> افزودن اولین کمپین
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>نام</th>
                                <th>تبلیغ‌دهنده</th>
                                <th>بودجه</th>
                                <th>تاریخ شروع</th>
                                <th>تاریخ پایان</th>
                                <th>وضعیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $c): ?>
                                <?php $st = $statusLabels[$c['status']] ?? ['label' => $c['status'], 'class' => 'secondary']; ?>
                                <tr>
                                    <td><?= (int) $c['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['advertiser_name'] ?: '—') ?></td>
                                    <td>
                                        <?php if (!empty($c['budget'])): ?>
                                            <?= number_format((float) $c['budget']) ?> تومان
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= $c['start_date'] ?: '—' ?></small></td>
                                    <td><small class="text-muted"><?= $c['end_date'] ?: '—' ?></small></td>
                                    <td>
                                        <span class="badge bg-<?= $st['class'] ?>"><?= $st['label'] ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary"
                                                    onclick='editCampaign(<?= json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?action=delete&id=<?= (int) $c['id'] ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('حذف این کمپین؟')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
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

<!-- Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" id="campaignForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">کمپین جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="mb-3">
                        <label class="form-label">نام کمپین <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="formName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea name="description" id="formDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">نام تبلیغ‌دهنده</label>
                            <input type="text" name="advertiser_name" id="formAdvertiserName" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ایمیل</label>
                            <input type="email" name="advertiser_email" id="formAdvertiserEmail" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">تلفن</label>
                            <input type="text" name="advertiser_phone" id="formAdvertiserPhone" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-0">
                        <div class="col-md-4">
                            <label class="form-label">بودجه (تومان)</label>
                            <input type="number" name="budget" id="formBudget" class="form-control" min="0" step="1000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">تاریخ شروع</label>
                            <input type="datetime-local" name="start_date" id="formStartDate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">تاریخ پایان</label>
                            <input type="datetime-local" name="end_date" id="formEndDate" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">وضعیت</label>
                        <select name="status" id="formStatus" class="form-select">
                            <option value="draft">پیش‌نویس</option>
                            <option value="active">فعال</option>
                            <option value="paused">متوقف</option>
                            <option value="completed">تکمیل شده</option>
                            <option value="cancelled">لغو شده</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> ذخیره
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('modalTitle').textContent = 'کمپین جدید';
    document.getElementById('campaignForm').reset();
}

function editCampaign(c) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = c.id;
    document.getElementById('modalTitle').textContent = 'ویرایش کمپین';
    document.getElementById('formName').value = c.name || '';
    document.getElementById('formDescription').value = c.description || '';
    document.getElementById('formAdvertiserName').value = c.advertiser_name || '';
    document.getElementById('formAdvertiserEmail').value = c.advertiser_email || '';
    document.getElementById('formAdvertiserPhone').value = c.advertiser_phone || '';
    document.getElementById('formBudget').value = c.budget || 0;
    document.getElementById('formStartDate').value = (c.start_date || '').replace(' ', 'T');
    document.getElementById('formEndDate').value = (c.end_date || '').replace(' ', 'T');
    document.getElementById('formStatus').value = c.status || 'draft';

    new bootstrap.Modal(document.getElementById('addModal')).show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

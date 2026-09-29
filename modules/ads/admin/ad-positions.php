<?php
/**
 * PartoCMS - Ads Admin - Positions
 * مدیریت موقعیت‌های تبلیغاتی
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

// افزودن/ویرایش موقعیت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $data = [
                'slug'        => trim($_POST['slug'] ?? ''),
                'title'       => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'width'       => (int) ($_POST['width'] ?? 0),
                'height'      => (int) ($_POST['height'] ?? 0),
                'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
                'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
            ];

            if (empty($data['slug']) || empty($data['title'])) {
                throw new Exception('slug و عنوان اجباری هستند');
            }

            if ($_POST['action'] === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO ad_positions (slug, title, description, width, height, is_active, sort_order)
                    VALUES (:slug, :title, :description, :width, :height, :is_active, :sort_order)
                ");
                $stmt->execute($data);
                $success = 'موقعیت جدید اضافه شد';
            } else {
                $id = (int) $_POST['id'];
                $data['id'] = $id;
                $stmt = $pdo->prepare("
                    UPDATE ad_positions SET
                        slug = :slug, title = :title, description = :description,
                        width = :width, height = :height, is_active = :is_active, sort_order = :sort_order
                    WHERE id = :id
                ");
                $stmt->execute($data);
                $success = 'موقعیت به‌روزرسانی شد';
            }
        }
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

// حذف
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    try {
        $pdo->prepare("DELETE FROM ad_positions WHERE id = ?")->execute([(int) $_GET['id']]);
        header('Location: ad-positions.php?msg=deleted');
        exit;
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

$positions = $adm->getPositions();

$pageTitle = 'موقعیت‌های تبلیغاتی';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);

$msg = $_GET['msg'] ?? '';
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
        .position-box {
            background: #e2e8f0;
            border: 2px dashed #94a3b8;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-layout-text-window"></i> موقعیت‌های تبلیغاتی</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> موقعیت جدید
        </button>
    </div>

    <?php if ($msg === 'deleted'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> موقعیت حذف شد</div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($positions as $pos): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-geo-alt"></i>
                            <strong><?= htmlspecialchars($pos['title']) ?></strong>
                        </span>
                        <span class="badge bg-<?= $pos['is_active'] ? 'success' : 'secondary' ?>">
                            <?= $pos['is_active'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="position-box">
                            <div class="text-muted small">
                                <code><?= htmlspecialchars($pos['slug']) ?></code>
                            </div>
                            <div class="mt-2">
                                <strong><?= (int) $pos['width'] ?> × <?= (int) $pos['height'] ?></strong> پیکسل
                            </div>
                        </div>
                        <?php if (!empty($pos['description'])): ?>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($pos['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-fill"
                                    onclick='editPosition(<?= json_encode($pos, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'>
                                <i class="bi bi-pencil"></i> ویرایش
                            </button>
                            <a href="?action=delete&id=<?= (int) $pos['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('حذف این موقعیت؟')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal افزودن/ویرایش -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="positionForm">
                <div class="modal-header">
                    <h5 class="modal-title">موقعیت جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId" value="">

                    <div class="mb-3">
                        <label class="form-label">Slug (نامک) <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="formSlug" class="form-control"
                               placeholder="header, sidebar, ..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">عنوان <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="formTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea name="description" id="formDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">عرض (px)</label>
                            <input type="number" name="width" id="formWidth" class="form-control" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">ارتفاع (px)</label>
                            <input type="number" name="height" id="formHeight" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">ترتیب</label>
                        <input type="number" name="sort_order" id="formSortOrder" class="form-control" value="0">
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="formIsActive" value="1" checked>
                        <label class="form-check-label" for="formIsActive">فعال</label>
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
function editPosition(pos) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = pos.id;
    document.getElementById('formSlug').value = pos.slug;
    document.getElementById('formTitle').value = pos.title;
    document.getElementById('formDescription').value = pos.description || '';
    document.getElementById('formWidth').value = pos.width || '';
    document.getElementById('formHeight').value = pos.height || '';
    document.getElementById('formSortOrder').value = pos.sort_order || 0;
    document.getElementById('formIsActive').checked = pos.is_active == 1;

    new bootstrap.Modal(document.getElementById('addModal')).show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

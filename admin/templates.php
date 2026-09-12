<?php
// admin/templates.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$templates = $pdo->query("SELECT * FROM templates ORDER BY created_at DESC")->fetchAll();
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت قالب‌ها | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; }
        .table th { background: #f8fafc; font-weight: 600; font-size: 12px; color: #64748b; }
        .badge-active { background: #27ae60; }
        .badge-inactive { background: #95a5a6; }
        .btn-action { margin: 0 2px; }
        .posts-badge { background: #ffc107; color: #000; font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-right: 5px; }
        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 style="margin:0; color:#1e293b;">📦 مدیریت قالب‌ها</h4>
        <a href="editor.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> قالب جدید
        </a>
    </div>

    <div class="alert alert-info d-flex align-items-center" style="font-size: 13px;">
        <i class="bi bi-info-circle fs-4 me-3"></i>
        <div>
            <strong>راهنما:</strong>
            با کلیک روی دکمه ⭐ یک قالب را به‌عنوان <strong>«قالب مقالات»</strong> تنظیم کنید.
            مقالات سایت با این قالب نمایش داده می‌شوند.
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام قالب</th>
                            <th>توضیحات</th>
                            <th>وضعیت</th>
                            <th>پیش‌فرض</th>
                            <th>قالب مقالات</th>
                            <th>تاریخ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['name']) ?></strong>
                                <?php if (!empty($t['use_for_posts'])): ?>
                                    <span class="posts-badge">⭐ قالب مقالات</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(mb_substr($t['description'] ?? '', 0, 60)) ?></td>
                            <td>
                                <span class="badge <?= $t['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $t['is_active'] ? 'فعال' : 'غیرفعال' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['is_default']): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i></span>
                                <?php else: ?>
                                    <a href="#" onclick="setDefault(<?= $t['id'] ?>)" class="text-muted"><i class="bi bi-star"></i></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($t['use_for_posts'])): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> فعال</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('Y/m/d', strtotime($t['created_at'])) ?></td>
                            <td>
                                <a href="editor.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="ویرایش">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if (!empty($t['use_for_posts'])): ?>
                                    <a href="#" onclick="unuseForPosts(<?= $t['id'] ?>)" class="btn btn-sm btn-warning btn-action" title="این قالب برای مقالات فعال است">
                                        <i class="bi bi-star-fill"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="useForPosts(<?= $t['id'] ?>)" class="btn btn-sm btn-outline-secondary btn-action" title="تنظیم به‌عنوان قالب مقالات">
                                        <i class="bi bi-star"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="#" onclick="toggleTemplate(<?= $t['id'] ?>)" class="btn btn-sm btn-outline-warning btn-action" title="تغییر وضعیت">
                                    <i class="bi bi-toggle-on"></i>
                                </a>
                                <a href="#" onclick="deleteTemplate(<?= $t['id'] ?>)" class="btn btn-sm btn-outline-danger btn-action" title="حذف">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <a href="../api/export-zip.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-info btn-action" title="دانلود ZIP">
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                هنوز قالبی ایجاد نشده است
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    async function toggleTemplate(id) {
        const res = await fetch('../api/templates.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
    }

    async function setDefault(id) {
        const res = await fetch('../api/templates.php?action=set_default', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
    }

    async function deleteTemplate(id) {
        if (!confirm('آیا از حذف این قالب مطمئن هستید؟')) return;
        const res = await fetch('../api/templates.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
    }

    async function useForPosts(id) {
        if (!confirm('این قالب به‌عنوان «قالب مقالات» تنظیم شود؟')) return;
        const res = await fetch('../api/templates.php?action=use_for_posts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
        else alert('خطا: ' + (data.message || 'ناشناخته'));
    }

    async function unuseForPosts(id) {
        if (!confirm('علامت «قالب مقالات» از این قالب برداشته شود؟')) return;
        const res = await fetch('../api/templates.php?action=unuse_for_posts', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.status === 'success') location.reload();
        else alert('خطا: ' + (data.message || 'ناشناخته'));
    }
</script>

</body>
</html>

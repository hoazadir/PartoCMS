<?php
/**
 * PartoCMS — Team Admin
 * لیست اعضای تیم
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/** @var \PartoCMS\Team\Domain\Service\TeamService $service */
$service = $GLOBALS['team_service'];

// ─── POST actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!team_csrf_verify($_POST['_csrf'] ?? null)) {
        team_redirect(team_admin_url('index.php'), 'error', 'توکن امنیتی نامعتبر است');
    }

    $action = (string) ($_POST['action'] ?? '');
    $singleId = (int) ($_POST['id'] ?? 0);
    $bulkIds = array_map('intval', $_POST['ids'] ?? []);

    try {
        if ($singleId > 0) {
            switch ($action) {
                case 'publish':
                    $service->publish($singleId);
                    team_redirect(team_admin_url('index.php'), 'success', 'عضو تیم منتشر شد');
                case 'hide':
                    $service->hide($singleId);
                    team_redirect(team_admin_url('index.php'), 'success', 'عضو تیم مخفی شد');
                case 'feature':
                    $service->toggleFeatured($singleId);
                    team_redirect(team_admin_url('index.php'), 'success', 'وضعیت ویژه تغییر کرد');
                case 'delete':
                    $service->delete($singleId);
                    team_redirect(team_admin_url('index.php'), 'success', 'عضو تیم حذف شد');
            }
        } elseif (!empty($bulkIds)) {
            switch ($action) {
                case 'bulk_publish':
                    $n = $service->bulkPublish($bulkIds);
                    team_redirect(team_admin_url('index.php'), 'success', "$n عضو منتشر شد");
                case 'bulk_hide':
                    $n = $service->bulkHide($bulkIds);
                    team_redirect(team_admin_url('index.php'), 'success', "$n عضو مخفی شد");
                case 'bulk_delete':
                    $n = $service->bulkDelete($bulkIds);
                    team_redirect(team_admin_url('index.php'), 'success', "$n عضو حذف شد");
            }
        }
    } catch (\Throwable $e) {
        team_redirect(team_admin_url('index.php'), 'error', 'خطا: ' . $e->getMessage());
    }

    team_redirect(team_admin_url('index.php'));
}

// ─── Filters + pagination ───
$page = max(1, (int) ($_GET['page'] ?? 1));
$status = (string) ($_GET['status'] ?? 'all');
$search = trim((string) ($_GET['q'] ?? ''));
$lang = (string) ($_GET['lang'] ?? '');
$dept = trim((string) ($_GET['dept'] ?? ''));
$perPage = 20;

$filters = [];
if ($status !== 'all' && in_array($status, ['published', 'hidden'], true)) {
    $filters['status'] = $status;
}
if ($search !== '') $filters['search'] = $search;
if ($lang !== '')   $filters['language'] = $lang;
if ($dept !== '')   $filters['department'] = $dept;

$total = $service->getCount($filters);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);

$filters['limit'] = $perPage;
$filters['offset'] = ($page - 1) * $perPage;
$filters['order_by'] = 'sort_order';
$filters['order_dir'] = 'ASC';

$items = $service->getAll($filters);
$stats = $service->getStats();
$flash = team_flash();
$siteName = getSetting('site_name', 'وب‌سایت من');

$buildUrl = function (array $override = []) use ($status, $search, $lang, $dept, $page) {
    $params = array_filter([
        'status' => $status !== 'all' ? $status : null,
        'q'      => $search ?: null,
        'lang'   => $lang ?: null,
        'dept'   => $dept ?: null,
        'page'   => $page > 1 ? $page : null,
    ], fn($v) => $v !== null && $v !== '');
    $params = array_merge($params, $override);
    $qs = http_build_query($params);
    return team_admin_url('index.php') . ($qs ? "?$qs" : '');
};
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تیم ما | <?= t_esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h2 { margin: 0; font-size: 20px; color: #1e293b; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 18px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 12px; }
        .stat-card .icon { font-size: 28px; }
        .stat-card .info { display: flex; flex-direction: column; }
        .stat-card .num { font-size: 22px; font-weight: bold; color: #1e293b; }
        .stat-card .label { font-size: 12px; color: #64748b; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filters-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; align-items: center; }
        .filters-bar input, .filters-bar select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-family: inherit; }
        .filters-bar input[type=text] { min-width: 200px; }
        .team-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .team-table th { background: #f1f5f9; padding: 12px; text-align: right; color: #334155; font-weight: bold; border-bottom: 2px solid #e2e8f0; }
        .team-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .team-table tr:hover { background: #f8fafc; }
        .team-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: #e0e7ff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold; color: #6366f1; }
        .action-btn { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-primary { background: #dbeafe; color: #1e40af; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-warning { background: #fef3c7; color: #92400e; }
        .btn-success { background: #dcfce7; color: #166534; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .pagination { display: flex; gap: 6px; margin-top: 20px; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 14px; background: #fff; border-radius: 6px; text-decoration: none; color: #334155; font-size: 13px; border: 1px solid #e2e8f0; }
        .pagination .current { background: #6366f1; color: #fff; border-color: #6366f1; }
        .bulk-actions { display: none; padding: 12px; background: #eff6ff; border-radius: 6px; margin-bottom: 10px; align-items: center; gap: 10px; }
        .bulk-actions.active { display: flex; flex-wrap: wrap; }
        .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; display: block; margin-bottom: 10px; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h2>👥 اعضای تیم</h2>
        <a href="<?= t_esc(team_admin_url('create.php')) ?>" class="action-btn btn-success" style="padding:10px 18px;">
            <i class="bi bi-plus-circle"></i> عضو جدید
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= t_esc($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card"><div class="icon">👥</div><div class="info"><span class="num"><?= (int) $stats['total'] ?></span><span class="label">کل اعضا</span></div></div>
        <div class="stat-card"><div class="icon">✅</div><div class="info"><span class="num"><?= (int) $stats['published'] ?></span><span class="label">منتشرشده</span></div></div>
        <div class="stat-card"><div class="icon">🙈</div><div class="info"><span class="num"><?= (int) $stats['hidden'] ?></span><span class="label">مخفی</span></div></div>
        <div class="stat-card"><div class="icon">⭐</div><div class="info"><span class="num"><?= (int) $stats['featured'] ?></span><span class="label">ویژه</span></div></div>
    </div>

    <div class="card">

        <!-- Filters -->
        <form method="get" action="" class="filters-bar">
            <input type="text" name="q" value="<?= t_esc($search) ?>" placeholder="جستجو (نام، سمت، بیو)...">
            <input type="text" name="dept" value="<?= t_esc($dept) ?>" placeholder="دپارتمان">
            <select name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>همه وضعیت‌ها</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>✅ منتشرشده</option>
                <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>🙈 مخفی</option>
            </select>
            <select name="lang">
                <option value="">همه زبان‌ها</option>
                <?php foreach (['fa-IR', 'en-US', 'ar-SA'] as $code): ?>
                    <option value="<?= t_esc($code) ?>" <?= $lang === $code ? 'selected' : '' ?>><?= t_esc($code) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="action-btn btn-primary" style="padding:8px 16px;"><i class="bi bi-search"></i> فیلتر</button>
            <?php if ($search !== '' || $status !== 'all' || $lang !== '' || $dept !== ''): ?>
                <a href="<?= t_esc(team_admin_url('index.php')) ?>" class="action-btn btn-warning">پاک کردن</a>
            <?php endif; ?>
        </form>

        <form method="post" action="" id="bulk-form">
            <?= team_csrf_field() ?>

            <div class="bulk-actions" id="bulk-actions">
                <span id="bulk-count">۰</span> مورد انتخاب شده:
                <button type="submit" name="action" value="bulk_publish" class="action-btn btn-success"><i class="bi bi-check-circle"></i> انتشار</button>
                <button type="submit" name="action" value="bulk_hide" class="action-btn btn-warning"><i class="bi bi-eye-slash"></i> مخفی</button>
                <button type="submit" name="action" value="bulk_delete" class="action-btn btn-danger" onclick="return confirm('حذف موارد انتخاب‌شده؟')"><i class="bi bi-trash"></i> حذف</button>
            </div>

            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h3>هیچ عضوی یافت نشد</h3>
                    <p>اولین عضو تیم را اضافه کنید.</p>
                </div>
            <?php else: ?>
                <table class="team-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="select-all"></th>
                            <th style="width: 60px;">عکس</th>
                            <th>نام</th>
                            <th>سمت</th>
                            <th>دپارتمان</th>
                            <th>وضعیت</th>
                            <th>ویژه</th>
                            <th style="width: 200px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $m): ?>
                            <?php
                            $photo = $m->getPhotoUrl();
                            $initial = mb_substr($m->getName(), 0, 1);
                            ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= (int) $m->getId() ?>" class="row-checkbox"></td>
                                <td>
                                    <?php if ($photo): ?>
                                        <img src="<?= t_esc($photo) ?>" alt="<?= t_esc($m->getName()) ?>" class="team-avatar">
                                    <?php else: ?>
                                        <div class="team-avatar"><?= t_esc($initial) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= t_esc($m->getName()) ?></strong></td>
                                <td><?= t_esc($m->getPosition() ?? '—') ?></td>
                                <td><?= t_esc($m->getDepartment() ?? '—') ?></td>
                                <td><?= team_status_badge($m->getStatus()) ?></td>
                                <td><?php if ($m->isFeatured()): ?><span class="badge bg-warning text-dark">⭐</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                                <td>
                                    <a href="<?= t_esc(team_admin_url('edit.php?id=' . $m->getId())) ?>" class="action-btn btn-primary btn-sm" title="ویرایش"><i class="bi bi-pencil"></i></a>
                                    <?php if ($m->getStatus() === 'hidden'): ?>
                                        <button type="submit" name="action" value="publish" form="single-form-<?= $m->getId() ?>" class="action-btn btn-success btn-sm" title="انتشار"><i class="bi bi-check"></i></button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="feature" form="single-form-<?= $m->getId() ?>" class="action-btn btn-warning btn-sm" title="تغییر ویژه"><i class="bi bi-star"></i></button>
                                    <button type="submit" name="action" value="delete" form="single-form-<?= $m->getId() ?>" class="action-btn btn-danger btn-sm" title="حذف" onclick="return confirm('حذف این عضو؟')"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </form>

        <?php foreach ($items as $m): ?>
            <form method="post" action="" id="single-form-<?= $m->getId() ?>" style="display:none;">
                <?= team_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m->getId() ?>">
            </form>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="<?= t_esc($buildUrl(['page' => $page - 1])) ?>">« قبلی</a><?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
                    <?php else: ?><a href="<?= t_esc($buildUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="<?= t_esc($buildUrl(['page' => $page + 1])) ?>">بعدی »</a><?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('select-all')?.addEventListener('change', function () {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const bar = document.getElementById('bulk-actions');
    const count = document.getElementById('bulk-count');
    if (bar && count) {
        count.textContent = checked.toLocaleString('fa-IR');
        bar.classList.toggle('active', checked > 0);
    }
}
document.querySelectorAll('.row-checkbox').forEach(cb => cb.addEventListener('change', updateBulkBar));
</script>
</body>
</html>

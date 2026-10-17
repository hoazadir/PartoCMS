<?php
/**
 * PartoCMS - FAQ Admin
 * لیست سوالات متداول
 */

require_once __DIR__ . '/_bootstrap.php';

/** @var FaqService $faq_service */
$faq_service = $GLOBALS['faq_service'];

// فیلترها
$page   = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';

$filters = [];
if ($search !== '') $filters['search'] = $search;
if ($status === 'active') $filters['is_active'] = 1;
if ($status === 'inactive') $filters['is_active'] = 0;

$list  = $faq_service->getPaginated($page, 20, $filters);
$flash = faq_flash();

// Handle POST actions (delete / toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            if ($action === 'delete') {
                $faq_service->delete($id);
                faq_flash('سوال با موفقیت حذف شد.');
            } elseif ($action === 'toggle') {
                $faq_service->toggleStatus($id);
                faq_flash('وضعیت سوال تغییر کرد.');
            }
        } catch (Throwable $e) {
            faq_flash('خطا: ' . $e->getMessage(), 'error');
        }
    }
    header('Location: ' . faq_url('index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سوالات متداول | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .faq-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .faq-table th { background: #f1f5f9; padding: 12px; text-align: right; color: #334155; font-weight: bold; border-bottom: 2px solid #e2e8f0; }
        .faq-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .faq-table tr:hover { background: #f8fafc; }
        .action-btn { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-primary { background: #dbeafe; color: #1e40af; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-warning { background: #fef3c7; color: #92400e; }
        .btn-success { background: #dcfce7; color: #166534; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .pagination { display: flex; gap: 6px; margin-top: 20px; justify-content: center; }
        .pagination a, .pagination span { padding: 8px 14px; background: #fff; border-radius: 6px; text-decoration: none; color: #334155; font-size: 13px; }
        .pagination .current { background: #6366f1; color: #fff; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">❓ مدیریت سوالات متداول</h4>
        <div>
            <a href="<?= faq_url('edit.php') ?>" class="action-btn btn-success" style="padding:10px 18px;">
                <i class="bi bi-plus-circle"></i> سوال جدید
            </a>
            <a href="<?= faq_url() ?>?status=active" class="action-btn btn-primary">فعال</a>
            <a href="<?= faq_url() ?>?status=inactive" class="action-btn btn-warning">غیرفعال</a>
            <a href="<?= faq_url() ?>" class="action-btn btn-primary">همه</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="get" style="margin-bottom:15px; display:flex; gap:10px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="جستجو در سوال یا پاسخ..." style="flex:1; padding:10px 15px; border:2px solid #e2e8f0; border-radius:8px; font-family:inherit;">
            <button type="submit" class="action-btn btn-primary" style="padding:10px 20px;"><i class="bi bi-search"></i> جستجو</button>
        </form>

        <?php if (empty($list['items'])): ?>
            <div style="text-align:center; padding:50px; color:#94a3b8;">
                <i class="bi bi-inbox" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                هیچ سوالی یافت نشد.
            </div>
        <?php else: ?>
            <table class="faq-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>سوال</th>
                        <th>دسته</th>
                        <th>ترتیب</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['items'] as $item): ?>
                        <tr>
                            <td><?= $item->id() ?></td>
                            <td><?= htmlspecialchars($item->question()) ?></td>
                            <td><?= htmlspecialchars($item->category() ?? '-') ?></td>
                            <td><?= $item->sortOrder() ?></td>
                            <td>
                                <?php if ($item->isActive()): ?>
                                    <span class="action-btn btn-success">فعال</span>
                                <?php else: ?>
                                    <span class="action-btn btn-warning">غیرفعال</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex; gap:5px;">
                                <a href="<?= faq_url('edit.php?id=' . $item->id()) ?>" class="action-btn btn-primary"><i class="bi bi-pencil"></i></a>
                                
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $item->id() ?>">
                                    <button type="submit" class="action-btn btn-warning"><i class="bi bi-toggle-on"></i></button>
                                </form>

                                <form method="post" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item->id() ?>">
                                    <button type="submit" class="action-btn btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($list['pages'] > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $list['pages']; $i++): ?>
                        <?php if ($i == $list['current_page']): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

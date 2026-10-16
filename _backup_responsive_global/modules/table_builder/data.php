<?php
/**
 * PartoCMS - Table Data Viewer
 */
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/includes/TableBuilder.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$tb = new TableBuilder($pdo);
$table = trim($_GET['table'] ?? '');

if (empty($table) || !$tb->tableExists($table)) {
    header('Location: table_builder.php');
    exit;
}

$message = '';
$messageType = '';

// حذف ردیف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_row') {
    $pkCol = $_POST['pk_col'] ?? '';
    $pkVal = $_POST['pk_val'] ?? '';
    try {
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pkCol` = ? LIMIT 1");
        $stmt->execute([$pkVal]);
        $message = '✅ ردیف حذف شد';
        $messageType = 'success';
    } catch (Throwable $e) {
        $message = '❌ خطا: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$columns = $tb->getColumns($table);
$totalRows = $tb->getRowCount($table);
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$orderBy = $_GET['order'] ?? ($columns[0]['Field'] ?? '');
$orderDir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$rows = $tb->getRows($table, $perPage, $offset, $orderBy, $orderDir);

// شناسایی کلید اصلی
$pkCol = null;
foreach ($columns as $c) {
    if (!empty($c['Key']) && $c['Key'] === 'PRI') { $pkCol = $c['Field']; break; }
}
if (!$pkCol && !empty($columns[0]['Field'])) $pkCol = $columns[0]['Field'];

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>داده‌های <?= htmlspecialchars($table) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.page-header h1{margin:0;font-size:22px}
.page-header .table-name{font-family:monospace;color:#fde68a;font-size:14px}

.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.card .header h5{margin:0;font-size:15px}

.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-primary-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-sm{padding:5px 9px;font-size:11px}

.data-table{width:100%;border-collapse:collapse;font-size:12px}
.data-table th{background:#334155;color:#fff;padding:10px 8px;text-align:right;font-size:11px;white-space:nowrap;cursor:pointer;user-select:none}
.data-table th:hover{background:#475569}
.data-table th .sort-icon{opacity:0.5;font-size:10px;margin-right:4px}
.data-table td{padding:9px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top;max-width:300px;overflow:hidden;text-overflow:ellipsis}
.data-table tr:hover td{background:#f8fafc}
.cell-null{color:#cbd5e1;font-style:italic}
.cell-binary{color:#94a3b8;font-size:10px}
.pk-badge{display:inline-block;padding:1px 5px;background:#fef3c7;color:#92400e;border-radius:5px;font-size:9px;font-weight:bold;margin-right:3px}
.col-type{display:block;font-size:9px;color:#94a3b8;margin-top:2px;font-family:monospace}

.pagination .page-link{color:#0891b2;font-size:13px}
.pagination .active .page-link{background:#0891b2;border-color:#0891b2;color:#fff}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <div>
            <h1>📊 مشاهده داده‌ها</h1>
            <p class="table-name"><?= htmlspecialchars($table) ?></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="/admin/table_data.php?table=<?= urlencode($table) ?>" class="btn-a btn-warning-a btn-sm">
                <i class="bi bi-arrow-clockwise"></i> رفرش
            </a>
            <a href="/admin/table_row_edit.php?table=<?= urlencode($table) ?>" class="btn-a btn-success-a btn-sm">
                <i class="bi bi-plus-circle"></i> افزودن ردیف
            </a>
            <a href="/admin/table_export.php?table=<?= urlencode($table) ?>" class="btn-a btn-primary-a btn-sm">
                <i class="bi bi-download"></i> Export
            </a>
            <a href="/admin/table_builder.php" class="btn-a btn-secondary-a btn-sm">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="header">
            <h5>📋 <?= number_format($totalRows) ?> ردیف — <?= number_format(count($columns)) ?> ستون</h5>
            <span style="font-size:12px;color:#64748b">صفحه <?= $page ?> از <?= $totalPages ?></span>
        </div>

        <?php if (empty($rows)): ?>
            <div style="text-align:center;padding:50px;color:#64748b">
                <i class="bi bi-inbox" style="font-size:60px;display:block;margin-bottom:15px;color:#cbd5e1"></i>
                <h5>جدول خالی است</h5>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $c): ?>
                            <th onclick="sortBy('<?= htmlspecialchars($c['Field']) ?>')">
                                <?php if ($c['Key'] === 'PRI'): ?>
                                    <span class="pk-badge">PK</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($c['Field']) ?>
                                <span class="col-type"><?= htmlspecialchars($c['Type']) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th style="width:60px">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php foreach ($columns as $c): ?>
                                <?php
                                $val = $r[$c['Field']] ?? null;
                                $display = '';
                                if ($val === null) {
                                    $display = '<span class="cell-null">NULL</span>';
                                } elseif (strlen($val) > 100) {
                                    $display = htmlspecialchars(mb_substr($val, 0, 100)) . '...';
                                } else {
                                    $display = htmlspecialchars($val);
                                }
                                ?>
                                <td title="<?= htmlspecialchars(mb_substr($val ?? '', 0, 200)) ?>"><?= $display ?></td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($pkCol && isset($r[$pkCol])): ?>
                                <a href="/admin/table_row_edit.php?table=<?= urlencode($table) ?>&pk=<?= urlencode($r[$pkCol]) ?>" class="btn-a btn-primary-a btn-sm" title="ویرایش">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" style="display:inline" onsubmit="return confirm('حذف این ردیف؟')">
                                    <input type="hidden" name="action" value="delete_row">
                                    <input type="hidden" name="pk_col" value="<?= htmlspecialchars($pkCol) ?>">
                                    <input type="hidden" name="pk_val" value="<?= htmlspecialchars($r[$pkCol]) ?>">
                                    <button type="submit" class="btn-a btn-danger-a btn-sm" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="padding:15px 22px;border-top:1px solid #e2e8f0">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?table=<?= urlencode($table) ?>&page=<?= $i ?>&order=<?= urlencode($orderBy) ?>&dir=<?= $orderDir ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function sortBy(col) {
    const url = new URL(window.location.href);
    const currentOrder = url.searchParams.get('order');
    const currentDir = url.searchParams.get('dir') || 'DESC';
    url.searchParams.set('order', col);
    url.searchParams.set('dir', (currentOrder === col && currentDir === 'DESC') ? 'ASC' : 'DESC');
    window.location.href = url.toString();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
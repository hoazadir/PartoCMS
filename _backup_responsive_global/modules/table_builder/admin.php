<?php
/**
 * PartoCMS - Table Builder (Main Page)
 * لیست جدول‌ها + عملیات
 *
 * @version 1.1
 * @date 2026-09-17
 */
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/includes/TableBuilder.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$tb = new TableBuilder($pdo);
$message = '';
$messageType = '';

// پیام از redirect
if (!empty($_GET['created'])) {
    $message = htmlspecialchars($_GET['created']);
    $messageType = 'success';
}

// ============================================================
//   عملیات
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'drop_table') {
        $table = trim($_POST['table'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');

        if ($confirm !== $table) {
            $message = "❌ برای تأیید، نام جدول را دقیقاً وارد کن";
            $messageType = 'danger';
        } else {
            $r = $tb->dropTable($table);
            $message = $r['ok'] ? "✅ جدول `$table` حذف شد" : "❌ خطا: " . ($r['error'] ?? '?');
            $messageType = $r['ok'] ? 'success' : 'danger';
        }
    }
}

// ============================================================
//   لیست جدول‌ها
// ============================================================
$tables = $tb->getTables();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $tables = array_filter($tables, fn($t) => stripos($t['name'], $search) !== false);
}

$sort = $_GET['sort'] ?? 'name';
usort($tables, function($a, $b) use ($sort) {
    return match($sort) {
        'rows'  => ($b['rows'] ?? 0) <=> ($a['rows'] ?? 0),
        'size'  => ($b['size_kb'] ?? 0) <=> ($a['size_kb'] ?? 0),
        default => strcmp($a['name'], $b['name']),
    };
});

$protectedTables = [
    'users','roles','permissions','languages','settings',
    'content_items','categories','comments','modules',
    'content_types','content_translations','content_tags',
    'role_permissions','role_modules','menus','menu_items',
    'security_logs','login_attempts','login_blocks','user_2fa',
    'user_2fa_backup','file_hashes','backup_settings','backups',
    'translation_keys','translations','user_preferences',
    'translation_cache','translation_versions','provider_stats',
    'category_translations','module_files','translation_queue',
    'activity_log','seo_settings','rate_limits','tags','views',
    'form_submissions','forms','media','projects','templates',
];

$totalTables = count($tables);
$totalRows = array_sum(array_column($tables, 'rows'));
$totalSize = array_sum(array_column($tables, 'size_kb'));

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>جدول‌ساز — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);text-align:center}
.stat-card .value{font-size:24px;font-weight:bold;margin-bottom:5px;color:#7c3aed}
.stat-card .label{font-size:12px;color:#64748b}

.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.card .header h5{margin:0;font-size:15px;color:#1e293b}
.card .body{padding:20px}

.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;transition:.2s;text-decoration:none}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-info-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-sm{padding:5px 10px;font-size:11px}

.search-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px}
.search-row input,.search-row select{padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px}
.search-row input:focus,.search-row select:focus{outline:none;border-color:#7c3aed}

.table-list{width:100%;border-collapse:collapse;font-size:13px}
.table-list th{background:#334155;color:#fff;padding:12px 10px;text-align:right;font-size:12px;white-space:nowrap}
.table-list td{padding:11px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.table-list tr:hover td{background:#f8fafc}
.table-list .table-name{font-family:monospace;font-weight:bold;color:#7c3aed;font-size:13px}
.table-list .size-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:#e0f2fe;color:#0369a1}
.table-list .rows-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:#ecfdf5;color:#065f46}

.action-buttons{display:flex;gap:4px;flex-wrap:wrap}
.action-buttons .btn-a{width:32px;height:32px;padding:0;justify-content:center}

.badge-protected{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:#fee2e2;color:#991b1b;margin-right:5px}

.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-bg.active{display:flex}
.modal-box{background:#fff;border-radius:12px;max-width:500px;width:100%;padding:25px}
.modal-box h5{margin:0 0 15px;color:#1e293b}
.modal-box .form-group{margin-bottom:15px}
.modal-box label{display:block;font-weight:bold;font-size:12px;margin-bottom:5px;color:#374151}
.modal-box input{width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-family:monospace;box-sizing:border-box}
.modal-box .warning{background:#fef3c7;padding:10px;border-radius:8px;font-size:12px;margin-bottom:15px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <div>
            <h1>📊 جدول‌ساز</h1>
            <p>ساخت، مدیریت و Export جدول‌های دیتابیس بدون SQL</p>
        </div>
        <a href="/admin/table_create.php" class="btn-a btn-success-a">
            <i class="bi bi-plus-circle"></i> ساخت جدول جدید
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="value"><?= number_format($totalTables) ?></div>
            <div class="label">تعداد جدول‌ها</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= number_format($totalRows) ?></div>
            <div class="label">کل ردیف‌ها</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= number_format($totalSize / 1024, 2) ?> MB</div>
            <div class="label">حجم کل</div>
        </div>
        <div class="stat-card">
            <div class="value"><?= htmlspecialchars($tb->getDbName()) ?></div>
            <div class="label">نام دیتابیس</div>
        </div>
    </div>

    <div class="card">
        <div class="body">
            <form method="get" class="search-row">
                <input type="text" name="q" placeholder="🔍 جستجوی جدول..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px">
                <select name="sort" onchange="this.form.submit()">
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>مرتب: نام</option>
                    <option value="rows" <?= $sort === 'rows' ? 'selected' : '' ?>>مرتب: تعداد ردیف</option>
                    <option value="size" <?= $sort === 'size' ? 'selected' : '' ?>>مرتب: حجم</option>
                </select>
                <button type="submit" class="btn-a btn-primary-a">
                    <i class="bi bi-search"></i> جستجو
                </button>
                <?php if ($search): ?>
                    <a href="/admin/table_builder.php" class="btn-a btn-secondary-a">
                        <i class="bi bi-x-circle"></i> پاک کردن
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="header">
            <h5>📋 جدول‌های موجود</h5>
            <span style="font-size:12px;color:#64748b"><?= number_format(count($tables)) ?> نتیجه</span>
        </div>

        <?php if (empty($tables)): ?>
            <div class="body" style="text-align:center;padding:50px;color:#64748b">
                <i class="bi bi-inbox" style="font-size:60px;display:block;margin-bottom:15px;color:#cbd5e1"></i>
                <h5 style="color:#475569">جدولی یافت نشد</h5>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table-list">
                <thead>
                    <tr>
                        <th>نام جدول</th>
                        <th>تعداد ردیف</th>
                        <th>حجم</th>
                        <th>Engine</th>
                        <th>ساخت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $t): ?>
                        <?php $isProtected = in_array($t['name'], $protectedTables); ?>
                        <tr>
                            <td>
                                <span class="table-name"><?= htmlspecialchars($t['name']) ?></span>
                                <?php if ($isProtected): ?>
                                    <span class="badge-protected" title="جدول محافظت‌شده">🔒</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rows-badge"><?= number_format($t['rows']) ?></span>
                            </td>
                            <td>
                                <span class="size-badge">
                                    <?php
                                    if ($t['size_kb'] < 1024) {
                                        echo number_format($t['size_kb'], 1) . ' KB';
                                    } else {
                                        echo number_format($t['size_kb'] / 1024, 2) . ' MB';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td style="font-size:11px;color:#64748b"><?= htmlspecialchars($t['engine']) ?></td>
                            <td style="font-size:11px;color:#64748b">
                                <?= $t['created'] ? date('Y/m/d', strtotime($t['created'])) : '—' ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="/admin/table_data.php?table=<?= urlencode($t['name']) ?>" class="btn-a btn-info-a btn-sm" title="مشاهده داده‌ها">
                                        <i class="bi bi-table"></i>
                                    </a>
                                    <a href="/admin/table_export.php?table=<?= urlencode($t['name']) ?>" class="btn-a btn-success-a btn-sm" title="Export">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="btn-a btn-primary-a btn-sm" onclick="showStructure(<?= htmlspecialchars(json_encode($t['name']), ENT_QUOTES) ?>)" title="ساختار">
                                        <i class="bi bi-list-ul"></i>
                                    </button>
                                    <a href="/modules/table_builder/to_module.php?table=<?= urlencode($t['name']) ?>" class="btn-a btn-primary-a btn-sm" title="تبدیل به ماژول" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
                                        <i class="bi bi-box-seam"></i>
                                    </a>
                                    <?php if (!$isProtected): ?>
                                    <button type="button" class="btn-a btn-danger-a btn-sm" onclick="confirmDrop(<?= htmlspecialchars(json_encode($t['name']), ENT_QUOTES) ?>)" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
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

<!-- Modal: تأیید حذف -->
<div class="modal-bg" id="dropModal">
    <div class="modal-box">
        <h5>⚠️ تأیید حذف جدول</h5>
        <div class="warning">
            این عمل قابل بازگشت نیست! تمام داده‌های جدول از بین می‌روند.
        </div>
        <form method="post">
            <input type="hidden" name="action" value="drop_table">
            <input type="hidden" name="table" id="dropTableName">
            <div class="form-group">
                <label>برای تأیید، نام جدول را وارد کن: <code id="dropTableNameDisplay"></code></label>
                <input type="text" name="confirm" placeholder="نام جدول" autocomplete="off" required>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn-a btn-secondary-a" onclick="closeModal('dropModal')">انصراف</button>
                <button type="submit" class="btn-a btn-danger-a">
                    <i class="bi bi-trash"></i> حذف قطعی
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: ساختار جدول -->
<div class="modal-bg" id="structureModal">
    <div class="modal-box" style="max-width:800px">
        <h5>📋 ساختار جدول: <code id="structureTableName"></code></h5>
        <div id="structureContent" style="max-height:60vh;overflow-y:auto"></div>
        <div style="text-align:left;margin-top:15px">
            <button type="button" class="btn-a btn-secondary-a" onclick="closeModal('structureModal')">بستن</button>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════
//  AJAX Headers استاندارد
// ═══════════════════════════════════════════════════════════
const AJAX_HEADERS = {
    'X-Requested-With': 'XMLHttpRequest'
};

// ═══════════════════════════════════════════════════════════
//  Modal: حذف جدول
// ═══════════════════════════════════════════════════════════
function confirmDrop(table) {
    document.getElementById('dropTableName').value = table;
    document.getElementById('dropTableNameDisplay').textContent = table;
    document.getElementById('dropModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// ═══════════════════════════════════════════════════════════
//  Modal: ساختار جدول (AJAX)
// ═══════════════════════════════════════════════════════════
function showStructure(table) {
    document.getElementById('structureTableName').textContent = table;
    document.getElementById('structureContent').innerHTML = '<div style="text-align:center;padding:20px"><i class="bi bi-hourglass-split"></i> در حال بارگذاری...</div>';
    document.getElementById('structureModal').classList.add('active');

    fetch('/admin/table_ajax.php?action=structure&table=' + encodeURIComponent(table), {
        headers: AJAX_HEADERS,
        credentials: 'same-origin'
    })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (!data.ok) {
                document.getElementById('structureContent').innerHTML = '<div class="alert alert-danger">' + (data.error || 'خطا') + '</div>';
                return;
            }

            let html = '<table style="width:100%;border-collapse:collapse;font-size:12px">';
            html += '<tr style="background:#f8fafc">';
            html += '<th style="padding:8px;text-align:right">ستون</th>';
            html += '<th style="padding:8px;text-align:right">نوع</th>';
            html += '<th style="padding:8px;text-align:right">Null</th>';
            html += '<th style="padding:8px;text-align:right">Default</th>';
            html += '<th style="padding:8px;text-align:right">Index</th>';
            html += '</tr>';

            (data.columns || []).forEach(c => {
                const idx = (c.indexes || []).map(i => i.primary ? '🔑 PRIMARY' : (i.unique ? '🔸 UNIQUE' : '🔹 INDEX')).join(', ');
                html += '<tr style="border-bottom:1px solid #f1f5f9">';
                html += '<td style="padding:8px;font-family:monospace;color:#7c3aed"><strong>' + c.Field + '</strong></td>';
                html += '<td style="padding:8px;font-family:monospace;font-size:11px">' + c.Type + '</td>';
                html += '<td style="padding:8px">' + (c.Null === 'YES' ? '✅' : '❌') + '</td>';
                html += '<td style="padding:8px;font-size:11px">' + (c.Default ?? '—') + '</td>';
                html += '<td style="padding:8px;font-size:10px">' + (idx || '—') + '</td>';
                html += '</tr>';
            });

            // Foreign Keys
            if (data.foreign_keys && Object.keys(data.foreign_keys).length > 0) {
                html += '<tr style="background:#eff6ff"><td colspan="5" style="padding:8px;font-weight:bold;color:#1e40af">🔗 Foreign Keys</td></tr>';
                Object.entries(data.foreign_keys).forEach(([col, fk]) => {
                    html += '<tr style="border-bottom:1px solid #f1f5f9;background:#f0f9ff">';
                    html += '<td style="padding:8px;font-family:monospace;color:#1e40af">' + col + '</td>';
                    html += '<td colspan="4" style="padding:8px;font-size:11px">';
                    html += '→ <code>' + fk.ref_table + '.' + fk.ref_column + '</code>';
                    html += ' <span style="color:#64748b">(ON DELETE ' + fk.on_delete + ', ON UPDATE ' + fk.on_update + ')</span>';
                    html += '</td></tr>';
                });
            }

            html += '</table>';
            document.getElementById('structureContent').innerHTML = html;
        })
        .catch(e => {
            document.getElementById('structureContent').innerHTML = '<div class="alert alert-danger">خطای شبکه: ' + e.message + '</div>';
        });
}

// ═══════════════════════════════════════════════════════════
//  Esc / click outside برای بستن modal
// ═══════════════════════════════════════════════════════════
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-bg.active').forEach(m => m.classList.remove('active'));
    }
});

document.querySelectorAll('.modal-bg').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

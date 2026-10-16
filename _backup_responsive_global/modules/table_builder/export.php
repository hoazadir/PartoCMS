<?php
/**
 * PartoCMS - Table Export
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
$format = strtolower($_GET['format'] ?? '');

if (empty($table) || !$tb->tableExists($table)) {
    header('Location: table_builder.php');
    exit;
}

// اگه فرمت مشخص شده، دانلود کن
if (in_array($format, ['sql', 'json'])) {
    $result = $tb->exportTable($table, $format);

    if (empty($result['ok'])) {
        die('خطا: ' . ($result['error'] ?? '?'));
    }

    header('Content-Type: ' . $result['mime'] . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    header('Content-Length: ' . strlen($result['content']));
    echo $result['content'];
    exit;
}

// صفحه‌ی انتخاب فرمت
$columns = $tb->getColumns($table);
$rowCount = $tb->getRowCount($table);
$info = $tb->getTableInfo($table);

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export — <?= htmlspecialchars($table) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b}
.card .body{padding:22px}
.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-info-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.export-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px}
.export-card{border:2px solid #e2e8f0;border-radius:12px;padding:20px;text-align:center;transition:.2s}
.export-card:hover{border-color:#10b981;transform:translateY(-2px);box-shadow:0 4px 12px rgba(16,185,129,.15)}
.export-card h6{margin:0 0 8px;color:#1e293b;font-size:14px}
.export-card .desc{font-size:12px;color:#64748b;margin-bottom:15px;min-height:40px}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
.stat{padding:12px;background:#f8fafc;border-radius:8px;text-align:center}
.stat .value{font-size:20px;font-weight:bold;color:#10b981}
.stat .label{font-size:11px;color:#64748b}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <h1>📤 Export جدول</h1>
        <p style="font-family:monospace;color:#fde68a;margin:5px 0 0"><?= htmlspecialchars($table) ?></p>
    </div>

    <div class="card">
        <div class="header">📊 اطلاعات جدول</div>
        <div class="body">
            <div class="stats-row">
                <div class="stat">
                    <div class="value"><?= number_format($rowCount) ?></div>
                    <div class="label">تعداد ردیف</div>
                </div>
                <div class="stat">
                    <div class="value"><?= number_format(count($columns)) ?></div>
                    <div class="label">تعداد ستون</div>
                </div>
                <div class="stat">
                    <div class="value"><?= number_format(($info['size_kb'] ?? 0), 1) ?> KB</div>
                    <div class="label">حجم</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="header">🎯 فرمت Export را انتخاب کن</div>
        <div class="body">
            <div class="export-grid">

                <div class="export-card">
                    <div style="font-size:48px;color:#10b981">📄</div>
                    <h6>SQL Dump</h6>
                    <div class="desc">فایل SQL با ساختار جدول + تمام داده‌ها. قابل import در MySQL/MariaDB.</div>
                    <a href="/admin/table_export.php?table=<?= urlencode($table) ?>&format=sql" class="btn-a btn-success-a">
                        <i class="bi bi-download"></i> دانلود SQL
                    </a>
                </div>

                <div class="export-card">
                    <div style="font-size:48px;color:#0891b2">📋</div>
                    <h6>JSON</h6>
                    <div class="desc">فایل JSON با ساختار کامل. مناسب برای backup یا انتقال به سیستم دیگه.</div>
                    <a href="/admin/table_export.php?table=<?= urlencode($table) ?>&format=json" class="btn-a btn-info-a">
                        <i class="bi bi-download"></i> دانلود JSON
                    </a>
                </div>

            </div>

            <div style="margin-top:25px;padding:15px;background:#ecfeff;border-right:4px solid #0891b2;border-radius:8px;font-size:12px;line-height:1.8">
                💡 <strong>راهنما:</strong>
                <br>• <strong>SQL</strong> — برای بازگردانی در دیتابیس دیگه استفاده کن
                <br>• <strong>JSON</strong> — برای backup یا پردازش در برنامه
            </div>
        </div>
    </div>

    <a href="/admin/table_builder.php" class="btn-a btn-secondary-a">
        <i class="bi bi-arrow-right"></i> بازگشت به لیست
    </a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
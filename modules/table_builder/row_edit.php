<?php
/**
 * PartoCMS - Row Edit / Create
 * فرم خودکار بر اساس ستون‌های جدول
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
$pkVal = $_GET['pk'] ?? null;
$mode = ($pkVal === null) ? 'create' : 'edit';

if (empty($table) || !$tb->tableExists($table)) {
    header('Location: /admin/table_builder.php');
    exit;
}

$columns = $tb->getColumns($table);
$foreignKeys = $tb->getForeignKeys($table);

// PK
$pkCol = null;
foreach ($columns as $c) {
    if (($c['Key'] ?? '') === 'PRI') { $pkCol = $c['Field']; break; }
}
if (!$pkCol && !empty($columns[0]['Field'])) $pkCol = $columns[0]['Field'];

// مقادیر فعلی (برای edit)
$row = [];
if ($mode === 'edit' && $pkCol) {
    $row = $tb->getRow($table, $pkCol, $pkVal) ?: [];
    if (empty($row)) {
        header('Location: /admin/table_data.php?table=' . urlencode($table) . '&error=notfound');
        exit;
    }
}

// تشخیص FK
function detectForeignKey($colName, $allTables, $tb, $foreignKeys) {
    if (isset($foreignKeys[$colName])) {
        $fk = $foreignKeys[$colName];
        return ['ref_table' => $fk['ref_table'], 'ref_column' => $fk['ref_column'], 'detected' => 'fk_constraint'];
    }
    if (preg_match('/^(.+)_id$/', $colName, $m)) {
        $base = $m[1];
        $candidates = [$base, $base . 's', $base . 'es'];
        if (substr($base, -1) === 'y') $candidates[] = substr($base, 0, -1) . 'ies';
        foreach ($candidates as $c) {
            if (in_array($c, $allTables, true)) {
                return ['ref_table' => $c, 'ref_column' => 'id', 'detected' => 'name_guess'];
            }
        }
    }
    return null;
}

$allTables = $tb->getTableNames();
$message = '';
$messageType = '';
if (!empty($_GET['error'])) {
    $message = '❌ خطا: ' . htmlspecialchars($_GET['error']);
    $messageType = 'danger';
}

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $mode === 'create' ? 'افزودن' : 'ویرایش' ?> — <?= htmlspecialchars($table) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,<?= $mode === 'create' ? '#10b981,#059669' : '#0891b2,#155e75' ?>);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b}
.card .body{padding:22px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:bold;font-size:13px;margin-bottom:6px;color:#374151}
.form-group label .required{color:#ef4444}
.form-group label .col-type{font-size:10px;color:#94a3b8;font-family:monospace;font-weight:normal;margin-right:6px}
.form-group input[type=text],
.form-group input[type=number],
.form-group input[type=date],
.form-group input[type=time],
.form-group input[type=datetime-local],
.form-group textarea,
.form-group select{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box;transition:border-color .2s}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus{outline:none;border-color:#0891b2}
.form-group textarea{min-height:120px;resize:vertical;font-family:monospace}
.form-group textarea.json{min-height:150px}
.form-group .hint{font-size:11px;color:#94a3b8;margin-top:4px}
.form-group .fk-badge{display:inline-block;padding:1px 6px;background:#dbeafe;color:#1e40af;border-radius:6px;font-size:9px;font-weight:bold;margin-right:4px}
.form-group .fk-badge.guess{background:#fef3c7;color:#92400e}
.form-group .ai-badge{display:inline-block;padding:1px 6px;background:#f3e8ff;color:#6b21a8;border-radius:6px;font-size:9px;font-weight:bold;margin-right:4px}
.form-check{display:flex;align-items:center;gap:8px;padding:8px 0}
.form-check input{width:auto;margin:0}
.form-check label{margin:0;font-weight:normal}
.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.actions-bar{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding-top:20px;border-top:2px solid #f1f5f9}
.disabled-input{background:#f1f5f9;color:#94a3b8;cursor:not-allowed}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <h1><?= $mode === 'create' ? '➕ افزودن ردیف جدید' : '✏️ ویرایش ردیف' ?></h1>
        <p>جدول: <code><?= htmlspecialchars($table) ?></code></p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="post" action="/admin/table_row_save.php">
        <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
        <input type="hidden" name="mode" value="<?= $mode ?>">
        <?php if ($pkCol): ?>
            <input type="hidden" name="pk_col" value="<?= htmlspecialchars($pkCol) ?>">
            <?php if ($mode === 'edit'): ?>
                <input type="hidden" name="pk_val" value="<?= htmlspecialchars($pkVal) ?>">
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <div class="header">📋 اطلاعات</div>
            <div class="body">
                <?php foreach ($columns as $c): ?>
                    <?php
                    $colName = $c['Field'];
                    $colType = $c['Type'];
                    $isPk = ($colName === $pkCol);
                    $isAutoInc = (($c['Extra'] ?? '') === 'auto_increment');
                    $isNullable = (($c['Null'] ?? '') === 'YES');
                    $default = $c['Default'] ?? null;
                    $comment = $c['Comment'] ?? '';
                    $fieldType = TableBuilder::getFormFieldType($colType);
                    $enumValues = ($fieldType === 'enum') ? TableBuilder::parseEnumValues($colType) : [];
                    $fkInfo = detectForeignKey($colName, $allTables, $tb, $foreignKeys);
                    $currentVal = $row[$colName] ?? null;
                    ?>

                    <div class="form-group">
                        <label>
                            <?= htmlspecialchars($colName) ?>
                            <?php if (!$isNullable && !$isAutoInc): ?><span class="required">*</span><?php endif; ?>
                            <span class="col-type"><?= htmlspecialchars($colType) ?></span>
                            <?php if ($isPk): ?><span class="fk-badge">🔑 PK</span><?php endif; ?>
                            <?php if ($isAutoInc): ?><span class="ai-badge">⚡ AUTO</span><?php endif; ?>
                            <?php if ($fkInfo): ?>
                                <span class="fk-badge <?= $fkInfo['detected'] === 'name_guess' ? 'guess' : '' ?>">
                                    🔗 FK → <?= htmlspecialchars($fkInfo['ref_table']) ?>
                                    <?= $fkInfo['detected'] === 'name_guess' ? '(حدس)' : '' ?>
                                </span>
                            <?php endif; ?>
                        </label>

                        <?php if ($isAutoInc && $mode === 'edit'): ?>
                            <input type="text" value="<?= htmlspecialchars($currentVal ?? '') ?>" class="disabled-input" disabled>
                            <div class="hint">Auto Increment — قابل تغییر نیست</div>

                        <?php elseif ($isAutoInc && $mode === 'create'): ?>
                            <input type="text" value="— خودکار —" class="disabled-input" disabled>
                            <div class="hint">Auto Increment — خودکار پر می‌شود</div>

                        <?php elseif ($fkInfo): ?>
                            <?php $fkValues = $tb->getForeignKeyValues($fkInfo['ref_table'], $fkInfo['ref_column'], 'name', 500); ?>
                            <select name="data[<?= htmlspecialchars($colName) ?>]" <?= (!$isNullable && $currentVal === null) ? 'required' : '' ?>>
                                <option value="">— انتخاب کنید —</option>
                                <?php foreach ($fkValues as $v): ?>
                                    <option value="<?= htmlspecialchars($v['id']) ?>"
                                        <?= (string)$currentVal === (string)$v['id'] ? 'selected' : '' ?>>
                                        #<?= htmlspecialchars($v['id']) ?> — <?= htmlspecialchars($v['label'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($fieldType === 'enum'): ?>
                            <select name="data[<?= htmlspecialchars($colName) ?>]" <?= (!$isNullable && $currentVal === null) ? 'required' : '' ?>>
                                <?php if ($isNullable): ?><option value="">— خالی —</option><?php endif; ?>
                                <?php foreach ($enumValues as $v): ?>
                                    <option value="<?= htmlspecialchars($v) ?>"
                                        <?= (string)$currentVal === (string)$v ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($fieldType === 'checkbox'): ?>
                            <div class="form-check">
                                <input type="checkbox" name="data[<?= htmlspecialchars($colName) ?>]"
                                       id="cb_<?= htmlspecialchars($colName) ?>" value="1"
                                       <?= ((int)$currentVal === 1 || $currentVal === '1') ? 'checked' : '' ?>>
                                <label for="cb_<?= htmlspecialchars($colName) ?>">فعال</label>
                            </div>

                        <?php elseif ($fieldType === 'textarea'): ?>
                            <textarea name="data[<?= htmlspecialchars($colName) ?>]"
                                      class="<?= strpos($colType, 'json') !== false ? 'json' : '' ?>"
                                      <?= (!$isNullable && $currentVal === null) ? 'required' : '' ?>><?= htmlspecialchars($currentVal ?? '') ?></textarea>

                        <?php elseif ($fieldType === 'number'): ?>
                            <input type="number" step="any"
                                   name="data[<?= htmlspecialchars($colName) ?>]"
                                   value="<?= htmlspecialchars($currentVal ?? $default ?? '') ?>"
                                   <?= (!$isNullable && $currentVal === null && $default === null) ? 'required' : '' ?>>

                        <?php elseif ($fieldType === 'datetime-local'): ?>
                            <input type="datetime-local"
                                   name="data[<?= htmlspecialchars($colName) ?>]"
                                   value="<?= $currentVal ? date('Y-m-d\TH:i', strtotime($currentVal)) : '' ?>">

                        <?php elseif ($fieldType === 'date'): ?>
                            <input type="date"
                                   name="data[<?= htmlspecialchars($colName) ?>]"
                                   value="<?= $currentVal ? date('Y-m-d', strtotime($currentVal)) : '' ?>">

                        <?php else: ?>
                            <input type="text"
                                   name="data[<?= htmlspecialchars($colName) ?>]"
                                   value="<?= htmlspecialchars($currentVal ?? $default ?? '') ?>"
                                   <?= (!$isNullable && $currentVal === null && $default === null) ? 'required' : '' ?>>
                        <?php endif; ?>

                        <?php if ($comment): ?><div class="hint">💬 <?= htmlspecialchars($comment) ?></div><?php endif; ?>
                        <?php if ($isNullable): ?><div class="hint">✅ می‌تواند خالی باشد</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="actions-bar">
            <button type="submit" class="btn-a btn-success-a" style="font-size:15px;padding:14px 30px">
                <i class="bi bi-check-circle"></i> <?= $mode === 'create' ? 'افزودن ردیف' : 'ذخیره تغییرات' ?>
            </button>
            <a href="/admin/table_data.php?table=<?= urlencode($table) ?>" class="btn-a btn-secondary-a" style="font-size:15px;padding:14px 30px">
                انصراف
            </a>
        </div>
    </form>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * PartoCMS - Table Create Wizard v2.1
 * پشتیبانی از Foreign Key + Composite Index
 */
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/includes/TableBuilder.php';
require_once __DIR__ . '/../../includes/AiAssistant.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$tb = new TableBuilder($pdo);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tableName = trim($_POST['table_name'] ?? '');
    $columnsJson = $_POST['columns_json'] ?? '[]';
    $columns = json_decode($columnsJson, true) ?: [];
    $indexesJson = $_POST['composite_indexes_json'] ?? '[]';
    $compositeIndexes = json_decode($indexesJson, true) ?: [];

    if (empty($tableName)) {
        $message = 'نام جدول الزامی است';
        $messageType = 'danger';
    } elseif (empty($columns)) {
        $message = 'حداقل یک ستون تعریف کنید';
        $messageType = 'danger';
    } else {
        $result = $tb->createTable($tableName, $columns, $compositeIndexes);
        if (!empty($result['ok'])) {
            $msg = '✅ جدول `' . $tableName . '` ساخته شد';
            if (!empty($result['fks'])) $msg .= ' (FK: ' . $result['fks'] . ')';
            if (!empty($result['composite_indexes'])) $msg .= ' (Index: ' . $result['composite_indexes'] . ')';
            header('Location: table_builder.php?created=' . urlencode($msg));
            exit;
        } else {
            $message = $result['error'] ?? 'خطای ناشناخته';
            $messageType = 'danger';
            $errorHint = $result['hint'] ?? '';
            $errorField = $result['error_field'] ?? '';
            $errorRaw = $result['raw_error'] ?? '';
        }
    }
}

// ذخیره داده‌های فرم برای بازگردانی در خطا
$oldInput = [
    'table_name' => $_POST['table_name'] ?? '',
    'columns_json' => $_POST['columns_json'] ?? '[]',
    'composite_indexes_json' => $_POST['composite_indexes_json'] ?? '[]',
];

// بررسی فعال بودن دستیار هوشمند
$aiEnabled = false;
try {
    $ai = new AiAssistant();
    $aiEnabled = $ai->isEnabled();
} catch (Throwable $e) {}

$allTables = $tb->getTableNames();
sort($allTables);

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ساخت جدول جدید</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b;display:flex;justify-content:space-between;align-items:center}
.card .body{padding:22px}

.form-group{margin-bottom:15px}
.form-group label{display:block;font-weight:bold;font-size:13px;margin-bottom:6px;color:#374151}
.form-group input,.form-group select{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#7c3aed}

.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:.2s}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-info-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-sm{padding:5px 10px;font-size:11px}

.col-row{background:#f8fafc;border-radius:10px;margin-bottom:10px;border-right:3px solid #7c3aed;padding:12px}
.col-main{display:grid;grid-template-columns:2fr 1.5fr 1fr 60px 60px 60px 60px 40px;gap:8px;align-items:end;margin-bottom:8px}
.col-main input,.col-main select{padding:8px;border:2px solid #e2e8f0;border-radius:6px;font-size:12px;width:100%;box-sizing:border-box}
.col-main label{display:block;font-size:10px;color:#64748b;margin-bottom:3px;font-weight:bold}
.col-main .checkbox-cell{display:flex;align-items:center;justify-content:center;flex-direction:column}
.col-main .checkbox-cell input{width:auto;margin:0}
.col-main .checkbox-cell label{font-size:9px;margin-bottom:2px}

.col-headers{display:grid;grid-template-columns:2fr 1.5fr 1fr 60px 60px 60px 60px 40px;gap:8px;padding:0 12px;margin-bottom:5px;font-size:11px;color:#64748b;font-weight:bold}

.fk-panel{background:#eff6ff;border:1px dashed #3b82f6;border-radius:8px;padding:10px;margin-top:8px;display:none}
.fk-panel.active{display:block}
.fk-panel .fk-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr;gap:8px}
.fk-panel label{font-size:10px;color:#1e40af;font-weight:bold;margin-bottom:3px;display:block}
.fk-panel select{padding:6px;font-size:11px;border:1px solid #93c5fd;border-radius:5px;width:100%;box-sizing:border-box}

.idx-row{background:#fef3c7;border-radius:10px;padding:12px;margin-bottom:10px;border-right:3px solid #f59e0b}
.idx-main{display:grid;grid-template-columns:1.5fr 1fr 2fr 40px;gap:8px;align-items:end}
.idx-main input,.idx-main select{padding:8px;border:2px solid #fcd34d;border-radius:6px;font-size:12px;width:100%;box-sizing:border-box;background:#fff}
.idx-main label{display:block;font-size:10px;color:#92400e;margin-bottom:3px;font-weight:bold}
.idx-cols-select{display:flex;flex-wrap:wrap;gap:6px;padding:6px;background:#fff;border:2px solid #fcd34d;border-radius:6px;min-height:36px}
.idx-cols-select label{display:inline-flex;align-items:center;gap:4px;font-size:11px;padding:3px 8px;background:#fffbeb;border-radius:12px;cursor:pointer;user-select:none}
.idx-cols-select label:hover{background:#fef3c7}
.idx-cols-select input{width:auto;margin:0}

.sql-preview{background:#1e293b;color:#10b981;padding:15px;border-radius:10px;font-family:monospace;font-size:12px;direction:ltr;text-align:left;white-space:pre-wrap;max-height:400px;overflow-y:auto}

.info-box{background:#ecfeff;border-right:4px solid #0891b2;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}

@media(max-width:768px){
    .col-main,.col-headers{grid-template-columns:1fr 1fr;gap:6px}
    .fk-panel .fk-grid,.idx-main{grid-template-columns:1fr}
}

    /* Error Panel */
    .error-panel {
        display: flex;
        gap: 16px;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        border-right: 4px solid #dc2626;
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .error-icon {
        font-size: 36px;
        flex-shrink: 0;
    }
    .error-content {
        flex: 1;
    }
    .error-hint {
        background: #fff7ed;
        border: 1px dashed #f59e0b;
        border-radius: 8px;
        padding: 10px 14px;
        margin: 10px 0;
        font-size: 12.5px;
        color: #92400e;
        line-height: 1.7;
    }
    .error-hint i {
        color: #f59e0b;
        margin-left: 5px;
    }
    .error-field-info {
        font-size: 12.5px;
        color: #7f1d1d;
        margin: 8px 0;
    }
    .error-field-info code {
        background: #fff;
        padding: 2px 8px;
        border-radius: 4px;
        color: #dc2626;
        font-weight: bold;
        border: 1px solid #fecaca;
    }
    .error-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    .field-highlight {
        animation: highlightPulse 2s ease;
        border-color: #dc2626 !important;
        background: #fef2f2 !important;
    }
    @keyframes highlightPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0.2); }
    }

    .required-star {
        color: #dc2626;
        font-weight: bold;
        font-size: 13px;
        cursor: help;
    }
    .col-name:invalid, .col-type:invalid {
        border-color: #fca5a5;
    }
    .hint-small {
        font-size: 9px;
        color: #94a3b8;
        margin-top: 2px;
    }
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <h1>➕ ساخت جدول جدید</h1>
        <p>با Foreign Key، Index مرکب و بدون SQL</p>
    </div>

    <?php if ($message && $messageType === 'danger'): ?>
    <div class="error-panel" id="errorPanel">
        <div class="error-icon">⚠️</div>
        <div class="error-content">
            <h4 style="margin:0 0 8px;font-size:15px;color:#991b1b">خطا در ساخت جدول</h4>
            <p class="error-msg" style="margin:0 0 10px;font-size:13px;color:#7f1d1d;line-height:1.7"><?= htmlspecialchars($message) ?></p>
            <?php if (!empty($errorHint)): ?>
            <div class="error-hint">
                <i class="bi bi-lightbulb-fill"></i>
                <?= htmlspecialchars($errorHint) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($errorField)): ?>
            <div class="error-field-info">
                📍 فیلد مشکل‌دار: <code><?= htmlspecialchars($errorField) ?></code>
            </div>
            <?php endif; ?>
            <div class="error-actions">
                <?php if (!empty($errorField)): ?>
                <button type="button" class="btn-a btn-warning-a" onclick="highlightErrorField('<?= htmlspecialchars($errorField, ENT_QUOTES) ?>')" style="font-size:12px;padding:7px 14px">
                    <i class="bi bi-search"></i> نمایش فیلد مشکل‌دار
                </button>
                <?php endif; ?>
                <button type="button" class="btn-a btn-primary-a" onclick="document.getElementById('createForm').scrollIntoView({behavior:'smooth'})" style="font-size:12px;padding:7px 14px">
                    <i class="bi bi-pencil"></i> اصلاح و تلاش مجدد
                </button>
                <?php if ($aiEnabled): ?>
                <button type="button" class="btn-a" onclick="analyzeWithAI()" style="font-size:12px;padding:7px 14px;background:linear-gradient(135deg,#06b6d4,#0891b2)" id="aiBtn">
                    <i class="bi bi-robot"></i> تحلیل هوشمند با AI
                </button>
                <?php endif; ?>
                <button type="button" class="btn-a btn-secondary-a" onclick="document.getElementById('errorPanel').style.display='none'" style="font-size:12px;padding:7px 14px">
                    <i class="bi bi-x-lg"></i> بستن
                </button>
            </div>
        </div>
    </div>

    <?php if ($aiEnabled): ?>
    <div id="aiResult" style="display:none;background:#fff;border-radius:12px;box-shadow:0 4px 16px rgba(6,182,212,0.15);margin-bottom:20px;overflow:hidden">
        <div style="padding:16px 20px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;font-weight:bold;font-size:14px;display:flex;justify-content:space-between;align-items:center">
            <span><i class="bi bi-robot"></i> تحلیل دستیار هوشمند</span>
            <button type="button" onclick="document.getElementById('aiResult').style.display='none'" style="background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;padding:4px 10px;border-radius:6px">✖</button>
        </div>
        <div id="aiResultBody" style="padding:20px;font-size:13px;line-height:1.9;color:#334155"></div>
    </div>
    <?php endif; ?>

    <?php elseif ($message && $messageType === 'success'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="info-box">
        💡 <strong>راهنما:</strong> نام جدول فقط حروف انگلیسی، عدد و آندرلاین. حداقل یه ستون با <strong>Auto Increment</strong> (کلید اصلی) لازمه.
        <br>🔗 <strong>Foreign Key</strong>: ارتباط با جدول دیگه
        <br>🔑 <strong>Composite Index</strong>: ایندکس روی چند ستون
    </div>

    <form method="post" id="createForm">
        <input type="hidden" name="columns_json" id="columnsJson">
        <input type="hidden" name="composite_indexes_json" id="compositeIndexesJson">

        <div class="card">
            <div class="header">📝 نام جدول</div>
            <div class="body">
                <div class="form-group">
                    <label>نام جدول (انگلیسی)</label>
                    <input type="text" name="table_name" id="tableName" placeholder="products, articles, ..." required pattern="[a-zA-Z_][a-zA-Z0-9_]*" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="header">
                <span>📋 ستون‌ها</span>
                <button type="button" class="btn-a btn-success-a btn-sm" onclick="addColumn()">
                    <i class="bi bi-plus-lg"></i> افزودن ستون
                </button>
            </div>
            <div class="body">
                <div class="col-headers">
                    <div>نام ستون</div>
                    <div>نوع</div>
                    <div>طول</div>
                    <div>Null</div>
                    <div>Unique</div>
                    <div>Index</div>
                    <div>Auto Inc</div>
                    <div></div>
                </div>
                <div id="columnsContainer"></div>
            </div>
        </div>

        <div class="card">
            <div class="header">
                <span>🔑 ایندکس‌های مرکب (Composite)</span>
                <button type="button" class="btn-a btn-warning-a btn-sm" onclick="addCompositeIndex()">
                    <i class="bi bi-plus-lg"></i> افزودن ایندکس
                </button>
            </div>
            <div class="body">
                <div id="indexesContainer">
                    <p style="text-align:center;color:#94a3b8;font-size:12px;margin:0">
                        هنوز ایندکس مرکبی تعریف نشده. (اختیاری)
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="header">👁️ پیش‌نمایش SQL</div>
            <div class="body">
                <button type="button" class="btn-a btn-info-a" onclick="previewSQL()">
                    <i class="bi bi-code-slash"></i> نمایش SQL
                </button>
                <div id="sqlPreview" style="display:none;margin-top:15px"></div>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn-a btn-success-a" style="font-size:15px;padding:14px 30px">
                <i class="bi bi-check-circle"></i> ساخت جدول
            </button>
            <a href="/admin/table_builder.php" class="btn-a btn-secondary-a" style="font-size:15px;padding:14px 30px">
                انصراف
            </a>
        </div>
    </form>

</div>

<script>
// ═══════════════════════════════════════════════════════════
//  داده‌ها
// ═══════════════════════════════════════════════════════════
// ═══ انواع ستون‌ها — به‌صورت خودکار از TableBuilder خوانده می‌شود ═══
const COLUMN_TYPES = <?= json_encode(
    \TableBuilder::$columnTypes,
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

// ═══ انواع معنادار (نقشه‌برداری به MySQL) ═══
const TYPE_MAPPING = <?= json_encode(
    \TableBuilder::$typeMapping,
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const FK_ACTIONS = {
    'RESTRICT': 'محدود کن (RESTRICT)',
    'CASCADE': 'آبشاری (CASCADE)',
    'SET NULL': 'خالی کن (SET NULL)',
    'NO ACTION': 'بدون عمل (NO ACTION)',
};

const INDEX_TYPES = {
    'INDEX': 'ایندکس معمولی',
    'UNIQUE': 'یکتا',
    'FULLTEXT': 'متن کامل (Fulltext)',
};

const ALL_TABLES = <?= json_encode($allTables, JSON_UNESCAPED_UNICODE) ?>;

// ✅ هدرهای استاندارد برای همه AJAX
const AJAX_HEADERS = {
    'X-Requested-With': 'XMLHttpRequest'
};

let colCount = 0;
let idxCount = 0;

// ═══════════════════════════════════════════════════════════
//  افزودن ستون
// ═══════════════════════════════════════════════════════════
function addColumn(data = {}) {
    colCount++;
    const id = colCount;

    const typesOptions = Object.entries(COLUMN_TYPES).map(([v, l]) =>
        `<option value="${v}" ${data.type === v ? 'selected' : ''}>${l}</option>`
    ).join('');

    const tablesOptions = ALL_TABLES.map(t =>
        `<option value="${t}" ${data.fk_ref_table === t ? 'selected' : ''}>${t}</option>`
    ).join('');

    const fkActionsOptions = Object.entries(FK_ACTIONS).map(([v, l]) =>
        `<option value="${v}">${l}</option>`
    ).join('');

    const div = document.createElement('div');
    div.className = 'col-row';
    div.id = 'col-' + id;
    div.innerHTML = `
        <div class="col-main">
            <div>
                <label>نام <span class="required-star" title="اجباری">*</span></label>
                <input type="text" class="col-name" placeholder="name" value="${data.name || ''}" pattern="[a-zA-Z_][a-zA-Z0-9_]*" required>
            </div>
            <div>
                <label>نوع <span class="required-star" title="اجباری">*</span></label>
                <select class="col-type" required>${typesOptions}</select>
            </div>
            <div>
                <label>طول</label>
                <input type="number" class="col-length" placeholder="255" value="${data.length || ''}">
            </div>
            <div class="checkbox-cell">
                <input type="checkbox" class="col-nullable" ${data.nullable ? 'checked' : ''}>
                <label>Null</label>
            </div>
            <div class="checkbox-cell">
                <input type="checkbox" class="col-unique" ${data.unique ? 'checked' : ''}>
                <label>Uniq</label>
            </div>
            <div class="checkbox-cell">
                <input type="checkbox" class="col-index" ${data.index ? 'checked' : ''}>
                <label>Idx</label>
            </div>
            <div class="checkbox-cell">
                <input type="checkbox" class="col-auto" ${data.auto_increment ? 'checked' : ''}>
                <label>Auto</label>
            </div>
            <div class="checkbox-cell">
                <button type="button" class="btn-a btn-danger-a btn-sm" onclick="removeColumn(${id})" title="حذف">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
        <div style="margin-top:8px">
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:11px;color:#1e40af;cursor:pointer">
                <input type="checkbox" class="col-has-fk" onchange="toggleFkPanel(${id})" ${data.fk_ref_table ? 'checked' : ''}>
                🔗 Foreign Key (کلید خارجی)
            </label>
        </div>
        <div class="fk-panel ${data.fk_ref_table ? 'active' : ''}" id="fk-panel-${id}">
            <div class="fk-grid">
                <div>
                    <label>جدول مرجع</label>
                    <select class="fk-ref-table" onchange="updateFkColumns(${id})">
                        <option value="">-- انتخاب --</option>
                        ${tablesOptions}
                    </select>
                </div>
                <div>
                    <label>ستون مرجع</label>
                    <select class="fk-ref-column" id="fk-col-${id}">
                        <option value="id">id</option>
                    </select>
                </div>
                <div>
                    <label>ON DELETE</label>
                    <select class="fk-on-delete">${fkActionsOptions}</select>
                </div>
                <div>
                    <label>ON UPDATE</label>
                    <select class="fk-on-update">${fkActionsOptions}</select>
                </div>
            </div>
        </div>
    `;

    document.getElementById('columnsContainer').appendChild(div);

    if (data.fk_ref_table) {
        updateFkColumns(id, data.fk_ref_column);
    }
}

function removeColumn(id) {
    const el = document.getElementById('col-' + id);
    if (el) el.remove();
    updateIndexColumnChoices();
}

function toggleFkPanel(id) {
    const checkbox = document.querySelector('#col-' + id + ' .col-has-fk');
    const panel = document.getElementById('fk-panel-' + id);
    if (checkbox.checked) {
        panel.classList.add('active');
        updateFkColumns(id);
    } else {
        panel.classList.remove('active');
    }
}

function updateFkColumns(id, selectedCol = null) {
    const row = document.getElementById('col-' + id);
    if (!row) return;

    const refTable = row.querySelector('.fk-ref-table').value;
    const colSelect = document.getElementById('fk-col-' + id);

    if (!refTable) {
        colSelect.innerHTML = '<option value="id">id</option>';
        return;
    }

    fetch('/admin/table_ajax.php?action=pk_columns&table=' + encodeURIComponent(refTable), {
        headers: AJAX_HEADERS,
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.columns && data.columns.length > 0) {
                colSelect.innerHTML = data.columns.map(c =>
                    `<option value="${c}" ${selectedCol === c ? 'selected' : ''}>${c}</option>`
                ).join('');
            } else {
                colSelect.innerHTML = '<option value="id">id</option>';
            }
        })
        .catch(() => {
            colSelect.innerHTML = '<option value="id">id</option>';
        });
}

// ═══════════════════════════════════════════════════════════
//  Composite Indexes
// ═══════════════════════════════════════════════════════════
function addCompositeIndex(data = {}) {
    idxCount++;
    const id = idxCount;

    const typeOptions = Object.entries(INDEX_TYPES).map(([v, l]) =>
        `<option value="${v}" ${data.type === v ? 'selected' : ''}>${l}</option>`
    ).join('');

    const div = document.createElement('div');
    div.className = 'idx-row';
    div.id = 'idx-' + id;
    div.innerHTML = `
        <div class="idx-main">
            <div>
                <label>نام ایندکس (اختیاری)</label>
                <input type="text" class="idx-name" placeholder="idx_name_cat" value="${data.name || ''}">
            </div>
            <div>
                <label>نوع</label>
                <select class="idx-type">${typeOptions}</select>
            </div>
            <div>
                <label>ستون‌ها</label>
                <div class="idx-cols-select" id="idx-cols-${id}"></div>
            </div>
            <div class="checkbox-cell" style="display:flex;align-items:flex-end">
                <button type="button" class="btn-a btn-danger-a btn-sm" onclick="removeIndex(${id})" title="حذف">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    `;
    document.getElementById('indexesContainer').appendChild(div);

    const empty = document.querySelector('#indexesContainer p');
    if (empty) empty.remove();

    updateIndexColumnChoices();
}

function removeIndex(id) {
    const el = document.getElementById('idx-' + id);
    if (el) el.remove();

    if (document.querySelectorAll('.idx-row').length === 0) {
        document.getElementById('indexesContainer').innerHTML =
            '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:0">هنوز ایندکس مرکبی تعریف نشده. (اختیاری)</p>';
    }
}

function updateIndexColumnChoices() {
    const colNames = [];
    document.querySelectorAll('.col-row').forEach(row => {
        const n = row.querySelector('.col-name').value.trim();
        if (n) colNames.push(n);
    });

    document.querySelectorAll('.idx-row').forEach(idxRow => {
        const container = idxRow.querySelector('.idx-cols-select');
        if (!container) return;

        const currentChecked = Array.from(container.querySelectorAll('input:checked')).map(i => i.value);

        container.innerHTML = colNames.map(c =>
            `<label>
                <input type="checkbox" value="${c}" ${currentChecked.includes(c) ? 'checked' : ''}>
                ${c}
            </label>`
        ).join('') || '<span style="color:#94a3b8;font-size:11px;padding:5px">اول ستون‌ها را نام‌گذاری کن</span>';
    });
}

// ═══════════════════════════════════════════════════════════
//  جمع‌آوری داده
// ═══════════════════════════════════════════════════════════
function collectColumns() {
    const cols = [];
    document.querySelectorAll('.col-row').forEach(row => {
        const name = row.querySelector('.col-name').value.trim();
        if (!name) return;

        const col = {
            name: name,
            type: row.querySelector('.col-type').value,
            length: parseInt(row.querySelector('.col-length').value) || null,
            nullable: row.querySelector('.col-nullable').checked,
            unique: row.querySelector('.col-unique').checked,
            index: row.querySelector('.col-index').checked,
            auto_increment: row.querySelector('.col-auto').checked,
            primary: row.querySelector('.col-auto').checked,
        };

        const hasFk = row.querySelector('.col-has-fk').checked;
        if (hasFk) {
            const refTable = row.querySelector('.fk-ref-table').value;
            const refColumn = row.querySelector('.fk-ref-column').value;
            if (refTable && refColumn) {
                col.foreign_key = {
                    ref_table: refTable,
                    ref_column: refColumn,
                    on_delete: row.querySelector('.fk-on-delete').value,
                    on_update: row.querySelector('.fk-on-update').value,
                };
            }
        }

        cols.push(col);
    });
    return cols;
}

function collectIndexes() {
    const indexes = [];
    document.querySelectorAll('.idx-row').forEach(row => {
        const name = row.querySelector('.idx-name').value.trim();
        const type = row.querySelector('.idx-type').value;
        const cols = Array.from(row.querySelectorAll('.idx-cols-select input:checked')).map(i => i.value);

        if (cols.length > 0) {
            indexes.push({
                name: name,
                type: type,
                columns: cols,
            });
        }
    });
    return indexes;
}

// ═══════════════════════════════════════════════════════════
//  ✅ previewSQL (اصلاح‌شده)
// ═══════════════════════════════════════════════════════════
function previewSQL() {
    const tableName = document.getElementById('tableName').value.trim();
    const columns = collectColumns();
    const indexes = collectIndexes();

    if (!tableName) { alert('نام جدول را وارد کن'); return; }
    if (columns.length === 0) { alert('حداقل یک ستون اضافه کن'); return; }

    fetch('/admin/table_ajax.php?action=preview_sql', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            table_name: tableName,
            columns: columns,
            composite_indexes: indexes
        })
    })
    .then(r => r.json())
    .then(data => {
        const preview = document.getElementById('sqlPreview');
        preview.style.display = 'block';
        preview.innerHTML = '<div class="sql-preview">' + (data.sql || data.error || 'خطا') + '</div>';
    })
    .catch(err => {
        alert('خطای شبکه: ' + err.message);
    });
}

// ═══════════════════════════════════════════════════════════
//  Submit
// ═══════════════════════════════════════════════════════════
document.getElementById('createForm').addEventListener('submit', function(e) {
    const columns = collectColumns();
    if (columns.length === 0) {
        e.preventDefault();
        alert('حداقل یک ستون اضافه کن');
        return;
    }

    const indexes = collectIndexes();

    document.getElementById('columnsJson').value = JSON.stringify(columns);
    document.getElementById('compositeIndexesJson').value = JSON.stringify(indexes);
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('col-name')) {
        updateIndexColumnChoices();
    }
});

// شروع
addColumn({ name: 'id', type: 'INT', length: 11, auto_increment: true, primary: true });
addColumn({ name: 'name', type: 'VARCHAR', length: 255 });
addColumn({ name: 'created_at', type: 'DATETIME', nullable: true });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ==================== بازیابی داده‌های فرم (در خطا) ====================
window.OLD_INPUT = {"table_name":"","columns_json":"[]"};

// ==================== نمایش فیلد مشکل‌دار ====================
function highlightErrorField(fieldName) {
    if (!fieldName) return;

    // پیدا کردن ردیف ستون با نام مشخص
    const rows = document.querySelectorAll(".col-row");
    let found = false;

    rows.forEach(row => {
        const nameInput = row.querySelector('input[data-field="name"]');
        if (nameInput && nameInput.value === fieldName) {
            // Scroll به آن
            row.scrollIntoView({ behavior: "smooth", block: "center" });

            // Highlight
            row.classList.add("field-highlight");
            setTimeout(() => row.classList.remove("field-highlight"), 3000);

            // Focus روی input
            setTimeout(() => nameInput.focus(), 500);
            found = true;
        }
    });

    if (!found) {
        // اگر فیلد در فرم نبود (مثلاً ستون نامعتبر)، به نام جدول اشاره کن
        const tableNameInput = document.getElementById("tableName");
        if (tableNameInput) {
            tableNameInput.scrollIntoView({ behavior: "smooth", block: "center" });
            tableNameInput.classList.add("field-highlight");
            setTimeout(() => tableNameInput.classList.remove("field-highlight"), 3000);
            tableNameInput.focus();
        }
    }
}

// ==================== بازیابی داده‌های ذخیره‌شده ====================
document.addEventListener("DOMContentLoaded", () => {
    const old = window.OLD_INPUT || {};

    // بازیابی نام جدول
    if (old.table_name) {
        const tn = document.getElementById("tableName");
        if (tn) tn.value = old.table_name;
    }

    // بازیابی ستون‌ها
    if (old.columns_json && old.columns_json !== "[]") {
        try {
            const cols = JSON.parse(old.columns_json);
            if (Array.isArray(cols) && cols.length > 0) {
                // اگر ستون‌ها قبلاً توسط JS اضافه نشده، اضافه کن
                const container = document.getElementById("columnsContainer");
                if (container && container.children.length === 0) {
                    cols.forEach(col => {
                        if (typeof addColumn === "function") {
                            addColumn(col);
                        }
                    });
                }
            }
        } catch (e) { console.error("خطا در بازیابی ستون‌ها:", e); }
    }

    // بازیابی ایندکس‌ها
    if (old.composite_indexes_json && old.composite_indexes_json !== "[]") {
        try {
            const idxs = JSON.parse(old.composite_indexes_json);
            if (Array.isArray(idxs) && idxs.length > 0) {
                const container = document.getElementById("indexesContainer");
                if (container && container.children.length === 0) {
                    idxs.forEach(idx => {
                        if (typeof addCompositeIndex === "function") {
                            addCompositeIndex(idx);
                        }
                    });
                }
            }
        } catch (e) { console.error("خطا در بازیابی ایندکس‌ها:", e); }
    }

    // اگر خطا داریم، پنل خطا را برجسته کن
    const errorPanel = document.getElementById("errorPanel");
    if (errorPanel) {
        errorPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    }
});
</script>
<script src="<?= SITE_URL ?>/assets/js/ai-assistant.js"></script>
</body>
</html>

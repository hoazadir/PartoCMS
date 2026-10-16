<?php
/**
 * PartoCMS - Table to Module Wizard v2.0
 * تبدیل جدول به ماژول کامل با ModuleAnalyzer + CrudGenerator
 *
 * @version 2.0
 * @date 2026-09-19
 */
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/includes/TableBuilder.php';
require_once __DIR__ . '/includes/ModuleAnalyzer.php';
require_once __DIR__ . '/includes/FormBuilder.php';
require_once __DIR__ . '/includes/CrudGenerator.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$tb = new TableBuilder($pdo);
$ma = new ModuleAnalyzer($pdo, $tb);
$fb = new FormBuilder($tb);
$cg = new CrudGenerator($pdo, $tb, $ma, $fb);

$message = '';
$messageType = '';
$step = $_GET['step'] ?? '1';
$table = trim($_GET['table'] ?? $_POST['table'] ?? '');
$analysis = null;

// ═══════════════════════════════════════════════════════════
// مرحله ۲: تحلیل جدول
// ═══════════════════════════════════════════════════════════
if ($step === '2' && $table && $tb->tableExists($table)) {
    try {
        $analysis = $ma->analyze($table);
    } catch (Throwable $e) {
        $message = '❌ خطا در تحلیل جدول: ' . $e->getMessage();
        $messageType = 'danger';
        $step = '1';
    }
}

// ═══════════════════════════════════════════════════════════
// مرحله ۳: تولید ماژول
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $slug = trim($_POST['module_name'] ?? '');
    $title = trim($_POST['module_title'] ?? '');
    $icon = trim($_POST['module_icon'] ?? '📦');
    $desc = trim($_POST['module_desc'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 500);

    // اعتبارسنجی
    if (empty($slug) || !preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
        $message = '❌ نام ماژول باید با حرف کوچک شروع شود و فقط شامل a-z، 0-9، _ باشد';
        $messageType = 'danger';
        $step = '2';
        $analysis = $ma->analyze($table);
    } elseif (is_dir(__DIR__ . '/../generated/' . $slug)) {
        $message = "❌ پوشه ماژول «{$slug}» از قبل وجود دارد";
        $messageType = 'danger';
        $step = '2';
        $analysis = $ma->analyze($table);
    } else {
        try {
            $freshAnalysis = $ma->analyze($table);
            $result = $cg->generate($freshAnalysis, [
                'slug'        => $slug,
                'title'       => $title ?: $freshAnalysis['module_title'],
                'icon'        => $icon ?: $freshAnalysis['module_icon'],
                'description' => $desc ?: $freshAnalysis['module_desc'],
                'sort_order'  => $sortOrder,
            ]);

            if (!empty($result['ok'])) {
                $message = '✅ ماژول `' . htmlspecialchars($slug) . '` با موفقیت ساخته شد!';
                $messageType = 'success';
                $message .= '<br><br><strong>فایل‌های تولیدشده:</strong> ' . count($result['files']) . ' فایل';
                $message .= '<br><br><a href="' . htmlspecialchars($result['admin_url']) . '" class="btn-a btn-success-a" style="display:inline-flex;padding:10px 20px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">🚀 ورود به پنل ماژول</a>';
                $message .= ' <a href="/admin/modules.php" class="btn-a" style="display:inline-flex;padding:10px 20px;background:#64748b;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-right:8px">📋 مدیریت ماژول‌ها</a>';
                $step = 'done';
            } else {
                $message = '❌ خطا: ' . htmlspecialchars($result['error']);
                $messageType = 'danger';
                $step = '2';
                $analysis = $freshAnalysis;
            }
        } catch (Throwable $e) {
            $message = '❌ خطای غیرمنتظره: ' . htmlspecialchars($e->getMessage());
            $messageType = 'danger';
            $step = '2';
            $analysis = $ma->analyze($table);
        }
    }
}

// لیست جداول برای مرحله ۱
$tables = $tb->getTables();
$tableList = [];
foreach ($tables as $t) {
    $name = is_array($t) ? ($t['name'] ?? $t['TABLE_NAME'] ?? '') : $t;
    if (!$name) continue;
    $tableList[] = [
        'name' => $name,
        'rows' => (int) $tb->getRowCount($name),
        'cols' => count($tb->getColumns($name)),
    ];
}

$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تبدیل جدول به ماژول | PartoCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= function_exists('getI18n') && getI18n() && getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.9;font-size:13px}
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b}
.card .body{padding:22px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:bold;font-size:13px;margin-bottom:6px;color:#374151}
.form-group .hint{font-size:11px;color:#94a3b8;margin-top:4px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#7c3aed}
.btn-a{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-primary-a{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:15px}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
.field-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid #f1f5f9;font-size:12.5px}
.field-row:last-child{border-bottom:none}
.field-row:hover{background:#f8fafc}
.field-icon{font-size:18px;width:28px;text-align:center}
.field-name{font-weight:bold;color:#1e293b;font-family:monospace;min-width:140px}
.field-type{background:#e0e7ff;color:#4338ca;padding:2px 8px;border-radius:8px;font-size:10.5px;font-weight:bold}
.field-label{color:#64748b;flex:1}
.field-badge{font-size:10.5px;padding:2px 7px;border-radius:6px}
.badge-req{background:#fef3c7;color:#92400e}
.badge-uniq{background:#dbeafe;color:#1e40af}
.badge-fk{background:#ddd6fe;color:#5b21b6}
.analysis-header{display:flex;align-items:center;gap:15px;padding:20px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border-radius:10px;margin-bottom:20px}
.analysis-header .icon{font-size:40px}
.analysis-header h3{margin:0;font-size:18px;color:#5b21b6}
.analysis-header .meta{font-size:12px;color:#7c3aed;margin-top:5px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <h1>🪄 تبدیل جدول به ماژول</h1>
        <p>ویزارد هوشمند — تحلیل جدول، پیش‌نمایش و تولید ماژول کامل CRUD</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($step === '1'): ?>
    <!-- ═══════════════════════════════════════════════
         مرحله ۱: انتخاب جدول
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="header">📋 مرحله ۱ از ۲ — انتخاب جدول</div>
        <div class="body">
            <form method="GET" action="">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label>جدول مورد نظر را انتخاب کنید <span style="color:#ef4444">*</span></label>
                    <select name="table" required style="width:100%;padding:12px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px">
                        <option value="">— انتخاب جدول —</option>
                        <?php foreach ($tableList as $t): ?>
                            <option value="<?= htmlspecialchars($t['name']) ?>">
                                <?= htmlspecialchars($t['name']) ?>
                                (<?= $t['cols'] ?> ستون، <?= number_format($t['rows']) ?> رکورد)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">💡 فقط جداولی که ساختار مناسبی دارند (کلید اصلی، فیلدهای قابل تشخیص) ماژول خوبی می‌سازند.</div>
                </div>
                <button type="submit" class="btn-a btn-primary-a" style="font-size:14px;padding:12px 30px">
                    <i class="bi bi-magic"></i> تحلیل جدول و ادامه
                </button>
            </form>
        </div>
    </div>

    <?php elseif ($step === '2' && $analysis): ?>
    <!-- ═══════════════════════════════════════════════
         مرحله ۲: پیش‌نمایش و تنظیمات
         ═══════════════════════════════════════════════ -->
    <div class="analysis-header">
        <div class="icon"><?= htmlspecialchars($analysis['module_icon']) ?></div>
        <div>
            <h3><?= htmlspecialchars($analysis['module_title']) ?></h3>
            <div class="meta">
                نوع: <strong><?= htmlspecialchars($analysis['module_type']) ?></strong> •
                جدول: <code><?= htmlspecialchars($analysis['table']) ?></code> •
                <?= count($analysis['fields']) ?> فیلد
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="action" value="generate">
        <input type="hidden" name="table" value="<?= htmlspecialchars($analysis['table']) ?>">

        <div class="card">
            <div class="header">📝 اطلاعات ماژول</div>
            <div class="body">
                <div class="grid-2">
                    <div class="form-group">
                        <label>نام ماژول (slug) <span style="color:#ef4444">*</span></label>
                        <input type="text" name="module_name" value="<?= htmlspecialchars($analysis['table']) ?>" pattern="[a-z][a-z0-9_]*" required>
                        <div class="hint">فقط حروف کوچک انگلیسی، عدد و آندرلاین</div>
                    </div>
                    <div class="form-group">
                        <label>عنوان نمایشی</label>
                        <input type="text" name="module_title" value="<?= htmlspecialchars($analysis['module_title']) ?>">
                    </div>
                    <div class="form-group">
                        <label>آیکون (emoji)</label>
                        <input type="text" name="module_icon" value="<?= htmlspecialchars($analysis['module_icon']) ?>" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>ترتیب در منو</label>
                        <input type="number" name="sort_order" value="500" min="1" max="9999">
                    </div>
                </div>
                <div class="form-group">
                    <label>توضیحات</label>
                    <textarea name="module_desc" rows="2"><?= htmlspecialchars($analysis['module_desc']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="header">🎯 تنظیمات نمایش (خودکار)</div>
            <div class="body" style="padding:0">
                <div class="field-row">
                    <span class="field-icon">📌</span>
                    <span class="field-label">فیلد عنوان:</span>
                    <span class="field-name"><?= htmlspecialchars($analysis['title_field']) ?></span>
                </div>
                <div class="field-row">
                    <span class="field-icon">📄</span>
                    <span class="field-label">فیلد محتوا:</span>
                    <span class="field-name"><?= $analysis['body_field'] ? htmlspecialchars($analysis['body_field']) : '—' ?></span>
                </div>
                <div class="field-row">
                    <span class="field-icon">🔗</span>
                    <span class="field-label">فیلد slug:</span>
                    <span class="field-name"><?= $analysis['slug_field'] ? htmlspecialchars($analysis['slug_field']) : '—' ?></span>
                </div>
                <div class="field-row">
                    <span class="field-icon">📊</span>
                    <span class="field-label">مرتب‌سازی:</span>
                    <span class="field-name"><?= htmlspecialchars($analysis['sort_field']) ?> <?= htmlspecialchars($analysis['sort_direction']) ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="header">🔍 فیلدهای شناسایی‌شده (<?= count($analysis['fields']) ?>)</div>
            <div class="body" style="padding:0">
                <?php foreach ($analysis['fields'] as $f): ?>
                <div class="field-row">
                    <span class="field-icon"><?= htmlspecialchars($f['icon'] ?? '📋') ?></span>
                    <span class="field-name"><?= htmlspecialchars($f['name']) ?></span>
                    <span class="field-type"><?= htmlspecialchars($f['html_type']) ?></span>
                    <span class="field-label"><?= htmlspecialchars($f['label']) ?></span>
                    <?php if (!empty($f['required'])): ?><span class="field-badge badge-req">اجباری</span><?php endif; ?>
                    <?php if (!empty($f['unique'])): ?><span class="field-badge badge-uniq">یکتا</span><?php endif; ?>
                    <?php if (!empty($f['foreign_key'])): ?><span class="field-badge badge-fk">FK → <?= htmlspecialchars($f['foreign_key']['ref_table'] ?? '?') ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:30px">
            <button type="submit" class="btn-a btn-success-a" style="font-size:15px;padding:14px 30px">
                <i class="bi bi-gear-fill"></i> 🚀 تولید ماژول
            </button>
            <a href="?step=1" class="btn-a btn-secondary-a" style="font-size:15px;padding:14px 30px">
                ← بازگشت
            </a>
        </div>
    </form>

    <?php elseif ($step === 'done'): ?>
    <!-- ═══════════════════════════════════════════════
         مرحله ۳: موفقیت (نمایش داده شده در $message)
         ═══════════════════════════════════════════════ -->
    <div class="card">
        <div class="body" style="text-align:center;padding:50px">
            <div style="font-size:70px">🎉</div>
            <h2 style="color:#10b981;margin:20px 0">ماژول با موفقیت ساخته شد!</h2>
            <p style="color:#64748b">حالا می‌توانید از طریق لینک‌های بالا وارد پنل ماژول شوید.</p>
            <div style="margin-top:20px">
                <a href="?step=1" class="btn-a btn-primary-a">➕ ساخت ماژول جدید</a>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <div class="card">
        <div class="body" style="font-size:12px;color:#64748b;line-height:1.8">
            <strong>💡 نکته:</strong>
            ماژول‌های ساخته‌شده در گروه <code>📦 ماژول‌های ساخته شده</code> در سایدبار ظاهر می‌شوند و از
            <a href="/admin/modules.php" style="color:#7c3aed;font-weight:bold">مدیریت ماژول‌ها</a>
            می‌توانید آن‌ها را فعال/غیرفعال یا حذف کنید.
        </div>
    </div>

</div>
</body>
</html>

<?php
/**
 * PartoCMS - Languages Management
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$i18n = getI18n();
$message = '';
$messageType = '';

// ==================== ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // افزودن زبان
    if ($action === 'add') {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $native = trim($_POST['native_name'] ?? '');
        $direction = $_POST['direction'] ?? 'ltr';
        $flag = trim($_POST['flag'] ?? '');

        if (empty($code) || empty($name) || empty($native)) {
            $message = '❌ همه فیلدهای اجباری را پر کنید';
            $messageType = 'danger';
        } elseif (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $code)) {
            $message = '❌ فرمت کد زبان نامعتبر است (مثال: fa-IR یا en)';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO languages (code, name, native_name, direction, flag, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$code, $name, $native, $direction, $flag]);
                $message = "✅ زبان {$native} اضافه شد";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ ' . ($e->getCode() == 23000 ? 'این کد زبان قبلاً وجود دارد' : $e->getMessage());
                $messageType = 'danger';
            }
        }
    }

    // ویرایش
    elseif ($action === 'edit') {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $native = trim($_POST['native_name'] ?? '');
        $direction = $_POST['direction'] ?? 'ltr';
        $flag = trim($_POST['flag'] ?? '');

        try {
            $pdo->prepare("UPDATE languages SET name = ?, native_name = ?, direction = ?, flag = ? WHERE id = ?")
                ->execute([$name, $native, $direction, $flag, $id]);
            $message = '✅ زبان بروزرسانی شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // تغییر وضعیت فعال
    elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        try {
            $pdo->prepare("UPDATE languages SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            $message = '✅ وضعیت تغییر کرد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // تنظیم به‌عنوان پیش‌فرض
    elseif ($action === 'set_default') {
        $id = (int) $_POST['id'];
        try {
            $pdo->exec("UPDATE languages SET is_default = 0");
            $pdo->prepare("UPDATE languages SET is_default = 1 WHERE id = ?")->execute([$id]);
            $message = '✅ زبان پیش‌فرض تغییر کرد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // حذف
    elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        try {
            $pdo->prepare("DELETE FROM languages WHERE id = ?")->execute([$id]);
            $message = '✅ زبان حذف شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ==================== LOAD LANGUAGES ====================
$languages = [];
try {
    $languages = $pdo->query("
        SELECT l.*, 
            (SELECT COUNT(*) FROM translations WHERE language_id = l.id) as translations_count
        FROM languages l
        ORDER BY l.is_default DESC, l.sort_order, l.name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت زبان‌ها — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#6366f1,#4338ca);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.content-card .body{padding:22px}

.lang-row{padding:15px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:15px;flex-wrap:wrap}
.lang-row:hover{background:#f8fafc}
.lang-row:last-child{border-bottom:none}
.lang-flag{font-size:32px;flex-shrink:0}
.lang-info{flex:1;min-width:180px}
.lang-info .name{font-size:15px;font-weight:bold;color:#1e293b}
.lang-info .code{font-size:11px;color:#94a3b8;font-family:monospace;margin-top:3px}
.lang-info .stats{font-size:11px;color:#64748b;margin-top:3px}
.lang-actions{display:flex;gap:6px;flex-wrap:wrap}

.badge-dir{padding:3px 10px;border-radius:10px;font-size:11px;font-weight:bold}
.badge-rtl{background:#fef3c7;color:#92400e}
.badge-ltr{background:#dbeafe;color:#1e40af}
.badge-default{background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:bold}
.badge-active{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:bold}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:bold}

.btn-act{padding:6px 12px;border-radius:8px;border:none;font-size:12px;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.2s;text-decoration:none}
.btn-act:hover{transform:translateY(-1px)}
.btn-primary-act{background:#6366f1;color:#fff}
.btn-success-act{background:#10b981;color:#fff}
.btn-warning-act{background:#f59e0b;color:#fff}
.btn-danger-act{background:#ef4444;color:#fff}
.btn-secondary-act{background:#64748b;color:#fff}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
@media (max-width:600px){.form-row{grid-template-columns:1fr}}
.form-group{margin-bottom:15px}
.form-group label{display:block;margin-bottom:6px;font-size:13px;font-weight:bold;color:#374151}
.form-group input, .form-group select{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;transition:.2s}
.form-group input:focus, .form-group select:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🌍 مدیریت زبان‌ها</h1>
        <p>افزودن، ویرایش و مدیریت زبان‌های سایت</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- زبان‌های موجود -->
    <div class="content-card">
        <div class="header">
            <h5>📋 زبان‌های موجود (<?= count($languages) ?>)</h5>
            <a href="translations.php" class="btn-act btn-primary-act">
                <i class="bi bi-translate"></i> مدیریت ترجمه‌ها
            </a>
        </div>
        <div>
            <?php if (empty($languages)): ?>
                <div style="padding:40px;text-align:center;color:#94a3b8">
                    <i class="bi bi-inbox" style="font-size:60px;opacity:.4;display:block;margin-bottom:15px"></i>
                    هیچ زبانی وجود ندارد
                </div>
            <?php else: foreach ($languages as $l): ?>
                <div class="lang-row">
                    <div class="lang-flag"><?= htmlspecialchars($l['flag'] ?: '🌐') ?></div>
                    <div class="lang-info">
                        <div class="name">
                            <?= htmlspecialchars($l['native_name']) ?>
                            <?php if ($l['is_default']): ?>
                                <span class="badge-default">⭐ پیش‌فرض</span>
                            <?php endif; ?>
                            <?php if ($l['is_active']): ?>
                                <span class="badge-active">فعال</span>
                            <?php else: ?>
                                <span class="badge-inactive">غیرفعال</span>
                            <?php endif; ?>
                        </div>
                        <div class="code">
                            <?= htmlspecialchars($l['code']) ?>
                            <span class="badge-dir badge-<?= $l['direction'] ?>">
                                <?= strtoupper($l['direction']) ?>
                            </span>
                        </div>
                        <div class="stats">
                            📖 <?= (int) $l['translations_count'] ?> ترجمه
                        </div>
                    </div>
                    <div class="lang-actions">
                        <button class="btn-act btn-secondary-act" onclick="editLang(<?= htmlspecialchars(json_encode($l)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn-act btn-warning-act" title="فعال/غیرفعال">
                                <i class="bi bi-<?= $l['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                            </button>
                        </form>
                        <?php if (!$l['is_default']): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="set_default">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn-act btn-success-act" title="پیش‌فرض کن">
                                <i class="bi bi-star"></i>
                            </button>
                        </form>
                        <form method="post" style="display:inline" onsubmit="return confirm('زبان حذف شود؟ همه ترجمه‌ها هم پاک می‌شوند.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $l['id'] ?>">
                            <button type="submit" class="btn-act btn-danger-act" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- افزودن زبان جدید -->
    <div class="content-card">
        <div class="header">
            <h5 id="formTitle">➕ افزودن زبان جدید</h5>
        </div>
        <div class="body">
            <form method="post" id="langForm">
                <input type="hidden" name="action" value="add" id="formAction">
                <input type="hidden" name="id" value="" id="formId">

                <div class="form-row">
                    <div class="form-group">
                        <label>کد زبان * (مثال: fa-IR یا en)</label>
                        <input type="text" name="code" id="formCode" placeholder="fa-IR" required>
                    </div>
                    <div class="form-group">
                        <label>نام انگلیسی *</label>
                        <input type="text" name="name" id="formName" placeholder="Persian" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>نام بومی *</label>
                        <input type="text" name="native_name" id="formNative" placeholder="فارسی" required>
                    </div>
                    <div class="form-group">
                        <label>جهت</label>
                        <select name="direction" id="formDirection">
                            <option value="ltr">LTR (چپ به راست)</option>
                            <option value="rtl">RTL (راست به چپ)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>پرچم (ایموجی، اختیاری)</label>
                    <input type="text" name="flag" id="formFlag" placeholder="🇮🇷" maxlength="10">
                </div>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn-act btn-success-act" style="padding:10px 20px;font-size:14px">
                        <i class="bi bi-plus-circle"></i> <span id="submitText">افزودن زبان</span>
                    </button>
                    <button type="button" class="btn-act btn-secondary-act" id="cancelBtn" style="padding:10px 20px;font-size:14px;display:none" onclick="resetForm()">
                        لغو
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLang(lang) {
    document.getElementById('formTitle').textContent = '✏️ ویرایش: ' + lang.native_name;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = lang.id;
    document.getElementById('formCode').value = lang.code;
    document.getElementById('formCode').setAttribute('readonly', true);
    document.getElementById('formName').value = lang.name;
    document.getElementById('formNative').value = lang.native_name;
    document.getElementById('formDirection').value = lang.direction;
    document.getElementById('formFlag').value = lang.flag || '';
    document.getElementById('submitText').textContent = 'ذخیره تغییرات';
    document.getElementById('cancelBtn').style.display = 'inline-flex';
    document.getElementById('langForm').scrollIntoView({behavior: 'smooth'});
}

function resetForm() {
    document.getElementById('formTitle').textContent = '➕ افزودن زبان جدید';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formCode').value = '';
    document.getElementById('formCode').removeAttribute('readonly');
    document.getElementById('formName').value = '';
    document.getElementById('formNative').value = '';
    document.getElementById('formDirection').value = 'ltr';
    document.getElementById('formFlag').value = '';
    document.getElementById('submitText').textContent = 'افزودن زبان';
    document.getElementById('cancelBtn').style.display = 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

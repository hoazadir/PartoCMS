<?php
/**
 * PartoCMS - Translations Management
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$message = '';
$messageType = '';

// ==================== ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // افزودن کلید ترجمه جدید
    if ($action === 'add_key') {
        $key = trim($_POST['key'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $description = trim($_POST['description'] ?? '');

        if (empty($key)) {
            $message = '❌ کلید ترجمه الزامی است';
            $messageType = 'danger';
        } elseif (!preg_match('/^[a-z0-9_\.]+$/i', $key)) {
            $message = '❌ کلید فقط شامل حروف انگلیسی، اعداد، _ و .';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO translation_keys (`key`, category, description) VALUES (?, ?, ?)");
                $stmt->execute([$key, $category, $description]);
                $message = "✅ کلید «{$key}» اضافه شد";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ ' . ($e->getCode() == 23000 ? 'این کلید قبلاً وجود دارد' : $e->getMessage());
                $messageType = 'danger';
            }
        }
    }

    // ذخیره ترجمه‌ها
    elseif ($action === 'save_translations') {
        $langId = (int) ($_POST['language_id'] ?? 0);
        $translations = $_POST['trans'] ?? [];

        if ($langId <= 0) {
            $message = '❌ زبان انتخاب نشده';
            $messageType = 'danger';
        } else {
            try {
                $saved = 0;
                $stmt = $pdo->prepare("
                    INSERT INTO translations (language_id, key_id, `value`) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
                ");

                foreach ($translations as $keyId => $value) {
                    $keyId = (int) $keyId;
                    $value = trim($value);

                    if ($value === '') {
                        // خالی = حذف ترجمه
                        $pdo->prepare("DELETE FROM translations WHERE language_id = ? AND key_id = ?")
                            ->execute([$langId, $keyId]);
                    } else {
                        $stmt->execute([$langId, $keyId, $value]);
                        $saved++;
                    }
                }

                $message = "✅ {$saved} ترجمه ذخیره شد";
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = '❌ ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }

    // حذف کلید
    elseif ($action === 'delete_key') {
        $keyId = (int) $_POST['key_id'];
        try {
            $pdo->prepare("DELETE FROM translation_keys WHERE id = ?")->execute([$keyId]);
            $message = '✅ کلید حذف شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // Auto-Translate (شبیه‌سازی ترجمه خودکار)
    elseif ($action === 'copy_from') {
        $fromLang = (int) $_POST['from_lang'];
        $toLang = (int) $_POST['to_lang'];
        $overwrite = !empty($_POST['overwrite']);

        if ($fromLang === $toLang || $fromLang <= 0 || $toLang <= 0) {
            $message = '❌ زبان‌های انتخابی نامعتبر هستند';
            $messageType = 'danger';
        } else {
            try {
                // کپی از زبان مبدأ به مقصد
                if ($overwrite) {
                    $pdo->prepare("DELETE FROM translations WHERE language_id = ?")->execute([$toLang]);
                    $sql = "INSERT INTO translations (language_id, key_id, `value`)
                            SELECT ?, key_id, `value` FROM translations WHERE language_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$toLang, $fromLang]);
                } else {
                    $sql = "INSERT IGNORE INTO translations (language_id, key_id, `value`)
                            SELECT ?, key_id, `value` FROM translations WHERE language_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$toLang, $fromLang]);
                }

                $count = $stmt->rowCount();
                $message = "✅ {$count} ترجمه کپی شد";
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = '❌ ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// ==================== LOAD DATA ====================
$languages = [];
try {
    $languages = $pdo->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY is_default DESC, sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// زبان انتخابی
$selectedLang = (int) ($_GET['lang'] ?? 0);
if ($selectedLang <= 0 && !empty($languages)) {
    // پیش‌فرض: اولین زبان
    $selectedLang = (int) $languages[0]['id'];
}

// فیلتر دسته‌بندی
$filterCategory = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// بارگذاری کلیدها و ترجمه‌ها
$keys = [];
$translations = [];
try {
    // ساخت query
    $where = [];
    $params = [];

    if ($filterCategory) {
        $where[] = "category = ?";
        $params[] = $filterCategory;
    }

    if ($searchQuery) {
        $where[] = "(`key` LIKE ? OR description LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT * FROM translation_keys {$whereSQL} ORDER BY category, `key`");
    $stmt->execute($params);
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ترجمه‌های زبان انتخابی
    if ($selectedLang > 0) {
        $stmt = $pdo->prepare("SELECT key_id, `value` FROM translations WHERE language_id = ?");
        $stmt->execute([$selectedLang]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $translations[$row['key_id']] = $row['value'];
        }
    }
} catch (Throwable $e) {}

// آمار
$categoryList = [];
try {
    $categoryList = $pdo->query("SELECT DISTINCT category FROM translation_keys ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

// زبان فعلی
$currentLang = null;
foreach ($languages as $l) {
    if ((int)$l['id'] === $selectedLang) { $currentLang = $l; break; }
}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت ترجمه‌ها — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}
.content-card .body{padding:22px}

.filter-bar{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.filter-bar select, .filter-bar input{padding:8px 12px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit}
.filter-bar select:focus, .filter-bar input:focus{outline:none;border-color:#8b5cf6}

.btn-act{padding:8px 14px;border-radius:8px;border:none;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:13px;transition:.2s;text-decoration:none}
.btn-act:hover{transform:translateY(-1px)}
.btn-primary-act{background:#8b5cf6;color:#fff}
.btn-success-act{background:#10b981;color:#fff}
.btn-warning-act{background:#f59e0b;color:#fff}
.btn-danger-act{background:#ef4444;color:#fff}
.btn-secondary-act{background:#64748b;color:#fff}
.btn-info-act{background:#3b82f6;color:#fff}
.btn-sm{padding:5px 10px;font-size:11px}

.trans-row{padding:12px 22px;border-bottom:1px solid #f1f5f9;display:grid;grid-template-columns:220px 1fr 80px;gap:15px;align-items:center}
.trans-row:hover{background:#f8fafc}
.trans-row:last-child{border-bottom:none}
.trans-row .key-info .key{font-family:monospace;font-size:13px;color:#1e293b;font-weight:bold;word-break:break-all}
.trans-row .key-info .cat{font-size:10px;color:#94a3b8;margin-top:3px}
.trans-row .key-info .desc{font-size:11px;color:#64748b;margin-top:3px}
.trans-row input[type=text]{width:100%;padding:8px 12px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;transition:.2s}
.trans-row input[type=text]:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.1)}
.trans-row .action-btns{display:flex;gap:5px;justify-content:flex-end}

.empty-state{padding:40px;text-align:center;color:#94a3b8}
.empty-state .icon{font-size:60px;opacity:.4;display:block;margin-bottom:15px}

.sticky-actions{position:sticky;bottom:0;background:#fff;padding:15px 22px;border-top:2px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}

.lang-info-box{background:#f5f3ff;border:2px solid #8b5cf6;border-radius:12px;padding:15px 20px;margin-bottom:20px;display:flex;align-items:center;gap:15px}
.lang-info-box .flag{font-size:36px}
.lang-info-box .info h3{margin:0;font-size:16px;color:#1e293b}
.lang-info-box .info p{margin:3px 0 0;font-size:12px;color:#64748b}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>📖 مدیریت ترجمه‌ها</h1>
        <p>ویرایش و افزودن ترجمه‌های زبان‌های مختلف</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- انتخاب زبان -->
    <div class="content-card">
        <div class="header">
            <h5>🌍 انتخاب زبان</h5>
            <a href="languages.php" class="btn-act btn-primary-act btn-sm">
                <i class="bi bi-gear"></i> مدیریت زبان‌ها
            </a>
        </div>
        <div class="body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <label style="font-weight:bold;font-size:14px">زبان:</label>
                <select onchange="location.href='?lang='+this.value" style="padding:10px 15px;border:2px solid #8b5cf6;border-radius:8px;font-size:14px;font-family:inherit">
                    <?php foreach ($languages as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= (int)$l['id'] === $selectedLang ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['flag'] ?? '🌐') ?>
                        <?= htmlspecialchars($l['native_name']) ?>
                        (<?= htmlspecialchars($l['code']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($currentLang): ?>
                <span style="font-size:12px;color:#64748b">
                    <?= count($translations) ?> از <?= count($keys) ?> کلید ترجمه شده
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- کپی از زبان دیگر -->
    <?php if (count($languages) > 1): ?>
    <div class="content-card">
        <div class="header"><h5>📋 کپی از زبان دیگر</h5></div>
        <div class="body">
            <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center" onsubmit="return confirm('مطمئنید؟');">
                <input type="hidden" name="action" value="copy_from">
                <label style="font-size:13px">از زبان:</label>
                <select name="from_lang" required style="padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit">
                    <option value="">-- انتخاب --</option>
                    <?php foreach ($languages as $l): if ((int)$l['id'] === $selectedLang) continue; ?>
                    <option value="<?= $l['id'] ?>">
                        <?= htmlspecialchars($l['flag'] ?? '🌐') ?> <?= htmlspecialchars($l['native_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <span style="font-size:14px">→</span>

                <label style="font-size:13px">به:</label>
                <strong style="color:#8b5cf6"><?= htmlspecialchars($currentLang['native_name'] ?? '') ?></strong>

                <input type="hidden" name="to_lang" value="<?= $selectedLang ?>">

                <label style="font-size:13px;display:flex;align-items:center;gap:5px">
                    <input type="checkbox" name="overwrite" value="1">
                    بازنویسی
                </label>

                <button type="submit" class="btn-act btn-info-act">
                    <i class="bi bi-files"></i> کپی
                </button>
            </form>
            <p style="font-size:11px;color:#94a3b8;margin-top:10px">
                💡 برای شروع سریع ترجمه در زبان جدید، از زبان پیش‌فرض کپی کنید و سپس ویرایش کنید.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- افزودن کلید جدید -->
    <div class="content-card">
        <div class="header"><h5>➕ افزودن کلید ترجمه جدید</h5></div>
        <div class="body">
            <form method="post" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end">
                <input type="hidden" name="action" value="add_key">
                <div>
                    <label style="font-size:12px;font-weight:bold;display:block;margin-bottom:5px">کلید *</label>
                    <input type="text" name="key" placeholder="welcome_message" required
                           style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:13px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:bold;display:block;margin-bottom:5px">دسته</label>
                    <input type="text" name="category" placeholder="general" value="general"
                           style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:bold;display:block;margin-bottom:5px">توضیح</label>
                    <input type="text" name="description" placeholder="توضیح اختیاری"
                           style="width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px">
                </div>
                <button type="submit" class="btn-act btn-success-act" style="padding:11px 20px">
                    <i class="bi bi-plus-circle"></i> افزودن
                </button>
            </form>
        </div>
    </div>

    <!-- لیست ترجمه‌ها -->
    <div class="content-card">
        <div class="header">
            <h5>📋 لیست ترجمه‌ها (<?= count($keys) ?> کلید)</h5>
        </div>

        <!-- فیلتر -->
        <div class="filter-bar">
            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%">
                <input type="hidden" name="lang" value="<?= $selectedLang ?>">
                <input type="text" name="q" placeholder="جستجوی کلید..." value="<?= htmlspecialchars($searchQuery) ?>" style="flex:1;min-width:180px">
                <select name="category">
                    <option value="">همه دسته‌ها</option>
                    <?php foreach ($categoryList as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-act btn-primary-act">
                    <i class="bi bi-search"></i> فیلتر
                </button>
                <?php if ($searchQuery || $filterCategory): ?>
                <a href="?lang=<?= $selectedLang ?>" class="btn-act btn-secondary-act">
                    <i class="bi bi-x"></i> پاک کردن
                </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($keys)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox icon"></i>
                <p>هیچ کلید ترجمه‌ای یافت نشد</p>
            </div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="save_translations">
                <input type="hidden" name="language_id" value="<?= $selectedLang ?>">

                <div>
                    <?php foreach ($keys as $k): ?>
                    <div class="trans-row">
                        <div class="key-info">
                            <div class="key"><?= htmlspecialchars($k['key']) ?></div>
                            <div class="cat">
                                <i class="bi bi-tag"></i> <?= htmlspecialchars($k['category']) ?>
                            </div>
                            <?php if ($k['description']): ?>
                            <div class="desc"><?= htmlspecialchars($k['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <input type="text" name="trans[<?= $k['id'] ?>]"
                                   value="<?= htmlspecialchars($translations[$k['id']] ?? '') ?>"
                                   placeholder="ترجمه <?= htmlspecialchars($currentLang['native_name'] ?? '') ?>"
                                   dir="<?= $currentLang && $currentLang['direction'] === 'rtl' ? 'rtl' : 'ltr' ?>">
                        </div>
                        <div class="action-btns">
                            <button type="button" class="btn-act btn-danger-act btn-sm"
                                    onclick="deleteKey(<?= $k['id'] ?>, '<?= htmlspecialchars($k['key'], ENT_QUOTES) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="sticky-actions">
                    <span style="font-size:13px;color:#64748b">
                        <i class="bi bi-info-circle"></i>
                        برای حذف ترجمه، کادر را خالی کنید
                    </span>
                    <button type="submit" class="btn-act btn-success-act" style="padding:12px 30px;font-size:14px">
                        <i class="bi bi-save"></i> ذخیره همه ترجمه‌ها
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- فرم مخفی برای حذف کلید -->
<form method="post" id="deleteKeyForm" style="display:none">
    <input type="hidden" name="action" value="delete_key">
    <input type="hidden" name="key_id" id="deleteKeyId">
</form>

<script>
function deleteKey(id, keyName) {
    if (confirm('کلید «' + keyName + '» و تمام ترجمه‌های آن حذف شود؟')) {
        document.getElementById('deleteKeyId').value = id;
        document.getElementById('deleteKeyForm').submit();
    }
}

// تأیید خروج اگر تغییرات ذخیره نشده
let formChanged = false;
document.querySelectorAll('input[name^="trans"]').forEach(input => {
    input.addEventListener('input', () => { formChanged = true; });
});
window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => { formChanged = false; });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

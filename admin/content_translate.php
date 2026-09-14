<?php
/**
 * PartoCMS - Content Translation Management
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/content_translator.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$translator = new ContentTranslator($pdo);
$message = '';
$messageType = '';

// دریافت مقاله
$contentId = (int) ($_GET['id'] ?? 0);
if ($contentId <= 0) {
    // لیست مقالات
    $contents = $pdo->query("
        SELECT c.*, 
            (SELECT COUNT(*) FROM content_translations WHERE content_id = c.id) as trans_count
        FROM content_items c 
        ORDER BY c.created_at DESC 
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $sidebarFile = __DIR__ . '/includes/sidebar.php';
    $siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
    ?>
    <!DOCTYPE html>
    <html <?= __html_attrs() ?>>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ترجمه محتوا</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
    .main{margin-right:260px;padding:20px;min-height:100vh}
    @media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
    .page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
    .page-header h1{margin:0;font-size:22px}
    .item-card{background:#fff;border-radius:12px;padding:18px 22px;margin-bottom:12px;display:flex;align-items:center;gap:15px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:.2s}
    .item-card:hover{transform:translateX(-5px);box-shadow:0 4px 15px rgba(0,0,0,.1)}
    .item-card .num{width:40px;height:40px;background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:bold}
    .item-card .info{flex:1;min-width:0}
    .item-card .title{font-weight:bold;color:#1e293b;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .item-card .meta{font-size:11px;color:#64748b}
    .btn-t{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;text-decoration:none;font-size:13px;color:#fff;background:linear-gradient(135deg,#0891b2,#155e75)}
    .btn-t:hover{transform:translateY(-2px);color:#fff;box-shadow:0 4px 15px rgba(8,145,178,.3)}
    </style>
    </head>
    <body>
    <?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
    <div class="main">
        <div class="page-header">
            <h1>🌍 ترجمه محتوا</h1>
            <p>انتخاب مقاله برای ترجمه دستی یا خودکار</p>
        </div>

        <?php if (empty($contents)): ?>
            <div class="alert alert-info">هیچ مقاله‌ای وجود ندارد</div>
        <?php else: ?>
            <?php foreach ($contents as $c): ?>
            <div class="item-card">
                <div class="num"><?= $c['id'] ?></div>
                <div class="info">
                    <div class="title"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="meta">
                        🕐 <?= htmlspecialchars($c['created_at']) ?>
                        • 🌍 <?= (int) $c['trans_count'] ?> ترجمه
                    </div>
                </div>
                <a href="?id=<?= $c['id'] ?>" class="btn-t">
                    <i class="bi bi-translate"></i> ترجمه
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ============ یک مقاله خاص ============

// اقدامات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'auto_translate_one') {
        $langCode = $_POST['lang'] ?? '';
        $result = $translator->translateContent($contentId, $langCode, $_SESSION['user_id'] ?? null);
        if (!empty($result['ok'])) {
            $message = "✅ ترجمه به $langCode انجام شد: " . htmlspecialchars($result['title']);
            $messageType = 'success';
        } else {
            $message = "❌ خطا: " . htmlspecialchars($result['error'] ?? 'نامشخص');
            $messageType = 'danger';
        }
    } elseif ($action === 'auto_translate_all') {
        $results = $translator->translateAll($contentId, $_SESSION['user_id'] ?? null);
        $success = 0;
        $errors = [];
        foreach ($results as $code => $res) {
            if (!empty($res['ok'])) $success++;
            else $errors[] = "$code: " . ($res['error'] ?? '?');
        }
        $message = "✅ $success زبان ترجمه شد" . (!empty($errors) ? " | خطاها: " . implode(', ', array_slice($errors, 0, 3)) : '');
        $messageType = $success > 0 ? 'success' : 'warning';
    } elseif ($action === 'save_manual') {
        $langId = (int) $_POST['lang_id'];
        $title = $_POST['title'] ?? '';
        $excerpt = $_POST['excerpt'] ?? '';
        $content = $_POST['content'] ?? '';
        $status = $_POST['status'] ?? 'published';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO content_translations 
                    (content_id, language_id, title, excerpt, content, status, translated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    excerpt = VALUES(excerpt),
                    content = VALUES(content),
                    status = VALUES(status),
                    translated_at = NOW()
            ");
            $stmt->execute([$contentId, $langId, $title, $excerpt, $content, $status, $_SESSION['user_id'] ?? null]);
            $message = "✅ ترجمه دستی ذخیره شد";
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($action === 'delete_translation') {
        $langId = (int) $_POST['lang_id'];
        $pdo->prepare("DELETE FROM content_translations WHERE content_id = ? AND language_id = ?")->execute([$contentId, $langId]);
        $message = "✅ ترجمه حذف شد";
        $messageType = 'success';
    }
}

// دریافت مقاله
$stmt = $pdo->prepare("SELECT * FROM content_items WHERE id = ?");
$stmt->execute([$contentId]);
$content = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$content) {
    die('مقاله یافت نشد');
}

// زبان‌ها
$languages = $pdo->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY is_default DESC, sort_order")->fetchAll(PDO::FETCH_ASSOC);

// ترجمه‌های موجود
$translations = [];
$stmt = $pdo->prepare("SELECT * FROM content_translations WHERE content_id = ?");
$stmt->execute([$contentId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $translations[$row['language_id']] = $row;
}

// زبان فعلی برای ویرایش
$editLangId = (int) ($_GET['lang_id'] ?? 0);
if ($editLangId <= 0 && !empty($languages)) {
    // پیش‌فرض: اولین زبان غیر فارسی
    foreach ($languages as $l) {
        if ($l['code'] !== 'fa-IR') { $editLangId = (int) $l['id']; break; }
    }
}

$editTrans = $translations[$editLangId] ?? null;
$editLangInfo = null;
foreach ($languages as $l) if ((int) $l['id'] === $editLangId) $editLangInfo = $l;

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ترجمه مقاله</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:20px}
.page-header p{margin:8px 0 0;font-size:13px;opacity:.9}
.card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b}
.card .body{padding:22px}
.btn-a{padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;margin:4px;display:inline-flex;align-items:center;gap:6px}
.btn-auto{background:linear-gradient(135deg,#10b981,#059669)}
.btn-all{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.btn-save{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-danger{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.lang-tabs{display:flex;gap:6px;flex-wrap:wrap;padding:12px 22px;background:#f1f5f9}
.lang-tab{padding:8px 14px;background:#fff;border:2px solid #e2e8f0;border-radius:8px;text-decoration:none;color:#475569;font-size:13px;transition:.2s;display:flex;align-items:center;gap:6px}
.lang-tab:hover{background:#e0f2fe;border-color:#0891b2;color:#0e7490}
.lang-tab.active{background:#0891b2;border-color:#0891b2;color:#fff;font-weight:bold}
.lang-tab.has-trans::after{content:'✅';font-size:11px}
.lang-tab.auto-trans::after{content:'🤖';font-size:11px}
.form-group{margin-bottom:15px}
.form-group label{display:block;font-weight:bold;font-size:13px;color:#374151;margin-bottom:6px}
.form-group input,.form-group textarea{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:14px;box-sizing:border-box}
.form-group input:focus,.form-group textarea:focus{outline:none;border-color:#0891b2}
.form-group textarea{min-height:100px;resize:vertical;font-family:inherit}
.source-box{background:#fef9c3;border:2px solid #facc15;border-radius:10px;padding:15px;font-size:13px;margin-bottom:15px}
.source-box .st{font-weight:bold;color:#854d0e;margin-bottom:8px}
.status-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:bold;margin-right:5px}
.status-draft{background:#fef3c7;color:#92400e}
.status-auto{background:#e0e7ff;color:#4338ca}
.status-published{background:#d1fae5;color:#065f46}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">
    <div class="page-header">
        <h1>🌍 ترجمه مقاله: <?= htmlspecialchars($content['title']) ?></h1>
        <p>روش دستی یا خودکار — کدام زبان را می‌خواهی ترجمه کنی؟</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <a href="content_translate.php" class="btn-a" style="background:#64748b">
        <i class="bi bi-arrow-right"></i> بازگشت به لیست
    </a>

    <!-- عملیات خودکار -->
    <div class="card">
        <div class="header">🤖 ترجمه خودکار (MyMemory API — رایگان)</div>
        <div class="body">
            <p style="font-size:13px;color:#64748b;margin-top:0">
                ترجمه خودکار به تمام ۱۹ زبان غیر فارسی. کیفیت متوسط، سرعت بالا.
            </p>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="auto_translate_all">
                <button type="submit" class="btn-a btn-all" onclick="return confirm('ترجمه به ۱۹ زبان؟ ممکن است چند دقیقه طول بکشد.');">
                    <i class="bi bi-magic"></i> ترجمه به همه زبان‌ها
                </button>
            </form>
        </div>
    </div>

    <!-- متن مبدأ -->
    <div class="card">
        <div class="header">📄 متن اصلی (فارسی)</div>
        <div class="body">
            <div class="source-box">
                <div class="st">عنوان:</div>
                <div><?= htmlspecialchars($content['title']) ?></div>
            </div>
            <?php if (!empty($content['excerpt'])): ?>
            <div class="source-box">
                <div class="st">خلاصه:</div>
                <div><?= htmlspecialchars(mb_substr($content['excerpt'], 0, 500)) ?></div>
            </div>
            <?php endif; ?>
            <div class="source-box">
                <div class="st">محتوا:</div>
                <div style="max-height:200px;overflow-y:auto"><?= htmlspecialchars(mb_substr(strip_tags($content['content'] ?? ''), 0, 500)) ?>...</div>
            </div>
        </div>
    </div>

    <!-- تب‌های زبان -->
    <div class="card">
        <div class="header">🌍 ترجمه‌ها</div>
        
        <div class="lang-tabs">
            <?php foreach ($languages as $l): ?>
                <?php if ($l['code'] === 'fa-IR') continue; ?>
                <?php
                $has = isset($translations[$l['id']]);
                $statusClass = '';
                if ($has) {
                    $st = $translations[$l['id']]['status'];
                    $statusClass = $st === 'auto' ? 'auto-trans' : 'has-trans';
                }
                ?>
                <a href="?id=<?= $contentId ?>&lang_id=<?= $l['id'] ?>" 
                   class="lang-tab <?= $editLangId === (int)$l['id'] ? 'active' : '' ?> <?= $statusClass ?>">
                    <?= htmlspecialchars($l['flag'] ?? '🌐') ?>
                    <?= htmlspecialchars($l['native_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($editLangInfo): ?>
        <div class="body">
            <h5 style="margin-top:0">
                <?= htmlspecialchars($editLangInfo['flag'] ?? '🌐') ?>
                ویرایش ترجمه <?= htmlspecialchars($editLangInfo['native_name']) ?>
                
                <?php if ($editTrans): ?>
                    <span class="status-badge status-<?= $editTrans['status'] ?>">
                        <?= $editTrans['status'] ?>
                    </span>
                <?php endif; ?>
            </h5>

            <form method="post" style="margin-bottom:15px">
                <input type="hidden" name="action" value="auto_translate_one">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($editLangInfo['code']) ?>">
                <button type="submit" class="btn-a btn-auto">
                    <i class="bi bi-magic"></i> ترجمه خودکار فقط به این زبان
                </button>
            </form>

            <form method="post">
                <input type="hidden" name="action" value="save_manual">
                <input type="hidden" name="lang_id" value="<?= $editLangId ?>">

                <div class="form-group">
                    <label>عنوان (<?= htmlspecialchars($editLangInfo['native_name']) ?>)</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editTrans['title'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>خلاصه</label>
                    <textarea name="excerpt" style="min-height:80px"><?= htmlspecialchars($editTrans['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>محتوا</label>
                    <textarea name="content" style="min-height:300px;font-family:monospace;direction:ltr;text-align:left"><?= htmlspecialchars($editTrans['content'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>وضعیت</label>
                    <select name="status" style="padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit">
                        <option value="draft" <?= ($editTrans['status'] ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="published" <?= ($editTrans['status'] ?? '') === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                        <option value="auto" <?= ($editTrans['status'] ?? '') === 'auto' ? 'selected' : '' ?>>خودکار</option>
                    </select>
                </div>

                <button type="submit" class="btn-a btn-save">
                    <i class="bi bi-save"></i> ذخیره ترجمه
                </button>

                <?php if ($editTrans): ?>
                <button type="submit" name="action" value="delete_translation" class="btn-a btn-danger"
                        onclick="return confirm('این ترجمه حذف شود؟')">
                    <i class="bi bi-trash"></i> حذف ترجمه
                </button>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

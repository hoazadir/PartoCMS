<?php
/**
 * PartoCMS - Module Management v3.0
 * مدیریت ماژول‌ها: 3 دسته (کاربر، پیش‌فرض، سیستمی)
 *
 * @version 3.0
 * @date 2026-09-19
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$mm = getModuleManager();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// ═══════════════════════════════════════════════════════════
// تابع کمکی: حذف بازگشتی پوشه
// ═══════════════════════════════════════════════════════════
if (!function_exists('deleteDirRecursive')) {
    function deleteDirRecursive($dir) {
        if (!is_dir($dir)) return 0;
        $count = 0;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $count += deleteDirRecursive($path);
            } else {
                if (@unlink($path)) $count++;
            }
        }
        @rmdir($dir);
        return $count;
    }
}

// ═══════════════════════════════════════════════════════════
// ۱. حذف ماژول (فقط Generated)
// ═══════════════════════════════════════════════════════════
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $moduleId = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT slug, name, is_core, menu_group FROM modules WHERE id = ?");
    $stmt->execute([$moduleId]);
    $mod = $stmt->fetch();

    if (!$mod) {
        $error = 'ماژول یافت نشد';
    } elseif ($mod['is_core']) {
        $error = 'ماژول‌های سیستمی قابل حذف نیستند';
    } elseif ($mod['menu_group'] !== 'generated') {
        $error = 'فقط ماژول‌های ساخته‌شده توسط کاربر قابل حذف هستند';
    } else {
        try {
            $slug = $mod['slug'];
            $deletedFiles = 0;

            // ۱. حذف پوشه ماژول
            $moduleDir = __DIR__ . '/../modules/generated/' . $slug;
            if (is_dir($moduleDir)) {
                $deletedFiles = deleteDirRecursive($moduleDir);
            }

            // ۲. حذف از module_files
            $pdo->prepare("DELETE FROM module_files WHERE module_slug = ?")->execute([$slug]);

            // ۳. حذف از role_modules
            try {
                $pdo->prepare("DELETE FROM role_modules WHERE module_slug = ?")->execute([$slug]);
            } catch (Throwable $e) {}

            // ۴. حذف از modules
            $pdo->prepare("DELETE FROM modules WHERE slug = ?")->execute([$slug]);

            // ۵. لاگ امنیتی
            try {
                getSecurity()->log('module_deleted', 'module', $moduleId, 'ماژول حذف شد: ' . $slug);
            } catch (Throwable $e) {}

            header('Location: modules.php?msg=deleted&files=' . $deletedFiles);
            exit;
        } catch (Throwable $e) {
            $error = 'خطا در حذف: ' . $e->getMessage();
        }
    }
}

// ═══════════════════════════════════════════════════════════
// ۲. فعال/غیرفعال
// ═══════════════════════════════════════════════════════════
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $moduleId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("SELECT slug, is_enabled, is_core FROM modules WHERE id = ?");
    $stmt->execute([$moduleId]);
    $mod = $stmt->fetch();

    if ($mod) {
        if ($mod['is_core']) {
            $error = 'ماژول‌های سیستمی قابل غیرفعال شدن نیستند';
        } else {
            if ($mod['is_enabled']) {
                $mm->disable($mod['slug']);
                try { getSecurity()->log('module_disabled', 'module', $moduleId, 'ماژول غیرفعال شد: ' . $mod['slug']); } catch (Throwable $e) {}
            } else {
                $mm->enable($mod['slug']);
                try { getSecurity()->log('module_enabled', 'module', $moduleId, 'ماژول فعال شد: ' . $mod['slug']); } catch (Throwable $e) {}
            }
            header('Location: modules.php?msg=updated');
            exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════
// ۳. پیام‌ها
// ═══════════════════════════════════════════════════════════
if (isset($_GET['msg'])) {
    $msgs = [
        'updated' => '✅ وضعیت ماژول تغییر کرد',
        'deleted' => '🗑 ماژول با موفقیت حذف شد' . (isset($_GET['files']) ? ' (' . (int)$_GET['files'] . ' فایل)' : ''),
    ];
    $success = $msgs[$_GET['msg']] ?? '';
}

$modules = $mm->getAll();

// ═══════════════════════════════════════════════════════════
// ۴. دسته‌بندی ماژول‌ها به 3 گروه اصلی
// ═══════════════════════════════════════════════════════════
$userModules = [];      // ماژول‌های کاربر (Generated)
$defaultModules = [];   // ماژول‌های پیش‌فرض PartoCMS
$coreModules = [];      // ماژول‌های سیستمی

foreach ($modules as $m) {
    if ($m['is_core']) {
        $coreModules[] = $m;
    } elseif ($m['menu_group'] === 'generated') {
        $userModules[] = $m;
    } else {
        $defaultModules[] = $m;
    }
}

// گروه‌بندی ماژول‌های پیش‌فرض برای نمایش زیباتر
$groups = [
    'content'     => ['title' => '📝 مدیریت محتوا',           'color' => '#3b82f6'],
    'tools'       => ['title' => '🧩 ابزارها',                'color' => '#8b5cf6'],
    'tools_pro'   => ['title' => '🔧 ابزارهای حرفه‌ای',       'color' => '#7c3aed'],
    'i18n'        => ['title' => '🌍 چند زبانی',              'color' => '#0891b2'],
    'translation' => ['title' => '🔄 ترجمه محتوا',            'color' => '#06b6d4'],
    'security'    => ['title' => '🛡 امنیت',                  'color' => '#ef4444'],
    'backup_auto' => ['title' => '💾 پشتیبان‌گیری خودکار',    'color' => '#f59e0b'],
    'ai'          => ['title' => '🤖 هوش مصنوعی',              'color' => '#06b6d4'],
    'appearance'  => ['title' => '🎨 ظاهر سایت',              'color' => '#ec4899'],
    'reports'     => ['title' => '📈 گزارش‌گیری',             'color' => '#10b981'],
    'seo'         => ['title' => '🔍 SEO و بهینه‌سازی',       'color' => '#f59e0b'],
    'other'       => ['title' => '📦 سایر',                   'color' => '#64748b'],
];

$groupedDefault = [];
foreach ($defaultModules as $m) {
    $g = $m['menu_group'] ?: 'other';
    if (!isset($groupedDefault[$g])) $groupedDefault[$g] = [];
    $groupedDefault[$g][] = $m;
}

// آمار
$totalModules   = count($modules);
$enabledModules = count(array_filter($modules, fn($m) => $m['is_enabled']));
$cntUser        = count($userModules);
$cntDefault     = count($defaultModules);
$cntCore        = count($coreModules);
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت ماژول‌ها | پنل مدیریت</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}

/* Stat Cards */
.stat-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);height:100%}
.stat-card .icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;flex-shrink:0}
.stat-card .num{font-size:24px;font-weight:bold;color:#1e293b;line-height:1}
.stat-card .label{font-size:11.5px;color:#64748b;margin-top:4px}

/* Section */
.section-title{font-size:16px;font-weight:bold;color:#1e293b;margin:25px 0 12px;display:flex;align-items:center;gap:10px}
.section-title .badge{background:#e0e7ff;color:#4338ca;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:bold}
.section-title.user .badge{background:#ddd6fe;color:#5b21b6}
.section-title.default .badge{background:#dbeafe;color:#1e40af}
.section-title.core .badge{background:#fef3c7;color:#78350f}

/* Group */
.group-section{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:14px;overflow:hidden}
.group-header{background:#f8fafc;padding:12px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center}
.group-header h6{margin:0;font-size:13px;color:#475569;font-weight:600}
.badge-count{background:#e2e8f0;color:#475569;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:bold}

/* Module Card */
.module-card{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid #f1f5f9;transition:background .15s}
.module-card:last-child{border-bottom:none}
.module-card:hover{background:#f8fafc}
.module-card.disabled{opacity:.6}
.module-card.user-card{background:linear-gradient(90deg,#f3e8ff,transparent 20%);border-right:3px solid #8b5cf6}
.module-card.core-card{background:linear-gradient(90deg,#fef3c7,transparent 20%);border-right:3px solid #f59e0b}
.module-icon{font-size:26px;width:42px;text-align:center;flex-shrink:0}
.module-info{flex:1;min-width:0}
.module-info .name{font-weight:bold;color:#1e293b;font-size:13.5px;display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.module-info .desc{color:#64748b;font-size:11.5px;margin-top:3px}
.module-info .meta{color:#94a3b8;font-size:11px;margin-top:5px;display:flex;gap:12px;flex-wrap:wrap}
.module-info .meta code{background:#f1f5f9;padding:1px 5px;border-radius:3px;color:#475569;font-size:10.5px}

/* Badges */
.badge-tag{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold}
.badge-user{background:#ddd6fe;color:#5b21b6}
.badge-default{background:#dbeafe;color:#1e40af}
.badge-core{background:#fbbf24;color:#78350f}
.badge-version{background:#e0e7ff;color:#4338ca;padding:2px 7px;border-radius:10px;font-size:10px}

/* Actions */
.module-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap;flex-shrink:0}
.status-badge{padding:4px 9px;border-radius:8px;font-size:11px;font-weight:bold;display:inline-flex;align-items:center;gap:4px}
.status-on{background:#d1fae5;color:#065f46}
.status-off{background:#fee2e2;color:#991b1b}

.toggle-btn{padding:6px 12px;border-radius:7px;font-size:11.5px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:4px;border:none;cursor:pointer;transition:all .15s}
.toggle-btn.on{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.toggle-btn.on:hover{background:#fbbf24;color:#fff}
.toggle-btn.off{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.toggle-btn.off:hover{background:#10b981;color:#fff}
.toggle-btn.disabled{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;padding:6px 12px;border-radius:7px;font-size:11.5px}

.delete-btn{padding:6px 12px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:7px;font-size:11.5px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:4px;cursor:pointer;transition:all .15s}
.delete-btn:hover{background:#dc2626;color:#fff;border-color:#dc2626;transform:translateY(-1px);box-shadow:0 4px 10px rgba(220,38,38,.25)}

.empty-state{padding:30px;text-align:center;color:#94a3b8;font-size:13px}
</style>
</head>
<body>

<?php
$sidebarFile = __DIR__ . '/includes/sidebar.php';
if (file_exists($sidebarFile)) require_once $sidebarFile;
?>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">🧩 مدیریت ماژول‌ها</h3>
            <small class="text-muted">ماژول‌های ساخته‌شده، پیش‌فرض و سیستمی</small>
        </div>
        <a href="modules.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-clockwise"></i> بازخوانی
        </a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        ❌ <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- آمار -->
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#667eea,#764ba2)"><i class="bi bi-grid-3x3-gap"></i></div>
                <div><div class="num"><?= $totalModules ?></div><div class="label">کل ماژول‌ها</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#8b5cf6,#a78bfa)"><i class="bi bi-puzzle"></i></div>
                <div><div class="num"><?= $cntUser ?></div><div class="label">ساخته‌شده توسط کاربر</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#3b82f6,#60a5fa)"><i class="bi bi-box"></i></div>
                <div><div class="num"><?= $cntDefault ?></div><div class="label">پیش‌فرض PartoCMS</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24)"><i class="bi bi-shield-lock"></i></div>
                <div><div class="num"><?= $cntCore ?></div><div class="label">سیستمی (غیرقابل حذف)</div></div>
            </div>
        </div>
    </div>

    <!-- راهنما -->
    <div class="alert alert-info d-flex align-items-center mb-4" style="font-size:12.5px">
        <i class="bi bi-info-circle fs-4 me-3"></i>
        <div>
            <strong>راهنما:</strong>
            <span class="badge-tag badge-user">✏️ کاربر</span> = ساخته‌شده با جدول‌ساز (قابل حذف) •
            <span class="badge-tag badge-default">⚙️ پیش‌فرض</span> = ماژول‌های پیش‌فرض PartoCMS (فقط غیرفعال) •
            <span class="badge-tag badge-core">🔒 سیستمی</span> = برای کارکرد ضروری (کاملاً قفل)
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         دسته ۱: ماژول‌های ساخته‌شده توسط کاربر
         ═══════════════════════════════════════════════════════ -->
    <div class="section-title user">
        ✏️ ماژول‌های ساخته‌شده توسط کاربر
        <span class="badge"><?= $cntUser ?> ماژول</span>
    </div>

    <?php if (empty($userModules)): ?>
        <div class="group-section">
            <div class="empty-state">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                هنوز ماژولی توسط کاربر ساخته نشده است.<br>
                <small>از <a href="table_builder.php" style="color:#7c3aed">جدول‌ساز</a> می‌توانید ماژول جدید بسازید.</small>
            </div>
        </div>
    <?php else: ?>
        <div class="group-section">
            <?php foreach ($userModules as $mod): ?>
                <div class="module-card user-card <?= $mod['is_enabled'] ? '' : 'disabled' ?>">
                    <div class="module-icon"><?= htmlspecialchars($mod['icon'] ?: '📦') ?></div>
                    <div class="module-info">
                        <div class="name">
                            <?= htmlspecialchars($mod['name']) ?>
                            <span class="badge-tag badge-user">✏️ کاربر</span>
                            <span class="badge-version">v<?= htmlspecialchars($mod['version']) ?></span>
                        </div>
                        <div class="desc"><?= htmlspecialchars($mod['description']) ?></div>
                        <div class="meta">
                            <span><i class="bi bi-key"></i> <?= (int)$mod['roles_count'] ?> نقش</span>
                            <span><i class="bi bi-code-slash"></i> <code><?= htmlspecialchars($mod['slug']) ?></code></span>
                        </div>
                    </div>
                    <div class="module-actions">
                        <span class="status-badge <?= $mod['is_enabled'] ? 'status-on' : 'status-off' ?>">
                            <i class="bi bi-<?= $mod['is_enabled'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                            <?= $mod['is_enabled'] ? 'فعال' : 'غیرفعال' ?>
                        </span>
                        <a href="?toggle=<?= $mod['id'] ?>"
                           class="toggle-btn <?= $mod['is_enabled'] ? 'on' : 'off' ?>"
                           onclick="return confirm('<?= $mod['is_enabled'] ? 'غیرفعال' : 'فعال' ?> کردن «<?= htmlspecialchars($mod['name']) ?>»؟')">
                            <i class="bi bi-toggle-<?= $mod['is_enabled'] ? 'on' : 'off' ?>"></i>
                            <?= $mod['is_enabled'] ? 'غیرفعال' : 'فعال' ?>
                        </a>
                        <a href="?delete=<?= $mod['id'] ?>"
                           class="delete-btn"
                           onclick="return confirm('⚠️ حذف کامل ماژول «<?= htmlspecialchars($mod['name']) ?>»\n\nتمام فایل‌ها و داده‌ها حذف می‌شوند!\n\nآیا مطمئن هستید؟')">
                            <i class="bi bi-trash"></i> حذف
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         دسته ۲: ماژول‌های پیش‌فرض PartoCMS
         ═══════════════════════════════════════════════════════ -->
    <div class="section-title default">
        ⚙️ ماژول‌های پیش‌فرض PartoCMS
        <span class="badge"><?= $cntDefault ?> ماژول</span>
    </div>

    <?php foreach ($groups as $groupKey => $groupInfo): ?>
        <?php if (!empty($groupedDefault[$groupKey])): ?>
            <div class="group-section">
                <div class="group-header">
                    <h6><?= $groupInfo['title'] ?></h6>
                    <span class="badge-count"><?= count($groupedDefault[$groupKey]) ?> ماژول</span>
                </div>
                <?php foreach ($groupedDefault[$groupKey] as $mod): ?>
                    <div class="module-card <?= $mod['is_enabled'] ? '' : 'disabled' ?>">
                        <div class="module-icon"><?= htmlspecialchars($mod['icon'] ?: '🧩') ?></div>
                        <div class="module-info">
                            <div class="name">
                                <?= htmlspecialchars($mod['name']) ?>
                                <span class="badge-tag badge-default">⚙️ پیش‌فرض</span>
                                <span class="badge-version">v<?= htmlspecialchars($mod['version']) ?></span>
                            </div>
                            <div class="desc"><?= htmlspecialchars($mod['description']) ?></div>
                            <div class="meta">
                                <span><i class="bi bi-key"></i> <?= (int)$mod['roles_count'] ?> نقش</span>
                                <span><i class="bi bi-code-slash"></i> <code><?= htmlspecialchars($mod['slug']) ?></code></span>
                            </div>
                        </div>
                        <div class="module-actions">
                            <span class="status-badge <?= $mod['is_enabled'] ? 'status-on' : 'status-off' ?>">
                                <i class="bi bi-<?= $mod['is_enabled'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                <?= $mod['is_enabled'] ? 'فعال' : 'غیرفعال' ?>
                            </span>
                            <a href="?toggle=<?= $mod['id'] ?>"
                               class="toggle-btn <?= $mod['is_enabled'] ? 'on' : 'off' ?>"
                               onclick="return confirm('<?= $mod['is_enabled'] ? 'غیرفعال' : 'فعال' ?> کردن «<?= htmlspecialchars($mod['name']) ?>»؟')">
                                <i class="bi bi-toggle-<?= $mod['is_enabled'] ? 'on' : 'off' ?>"></i>
                                <?= $mod['is_enabled'] ? 'غیرفعال' : 'فعال' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- ═══════════════════════════════════════════════════════
         دسته ۳: ماژول‌های سیستمی
         ═══════════════════════════════════════════════════════ -->
    <div class="section-title core">
        🔒 ماژول‌های سیستمی
        <span class="badge"><?= $cntCore ?> ماژول</span>
    </div>

    <div class="group-section">
        <?php foreach ($coreModules as $mod): ?>
            <div class="module-card core-card">
                <div class="module-icon"><?= htmlspecialchars($mod['icon'] ?: '🔒') ?></div>
                <div class="module-info">
                    <div class="name">
                        <?= htmlspecialchars($mod['name']) ?>
                        <span class="badge-tag badge-core">🔒 سیستمی</span>
                        <span class="badge-version">v<?= htmlspecialchars($mod['version']) ?></span>
                    </div>
                    <div class="desc"><?= htmlspecialchars($mod['description']) ?></div>
                    <div class="meta">
                        <span><i class="bi bi-key"></i> <?= (int)$mod['roles_count'] ?> نقش</span>
                        <span><i class="bi bi-code-slash"></i> <code><?= htmlspecialchars($mod['slug']) ?></code></span>
                    </div>
                </div>
                <div class="module-actions">
                    <span class="status-badge status-on">
                        <i class="bi bi-check-circle-fill"></i> فعال
                    </span>
                    <span class="toggle-btn disabled" title="ماژول سیستمی - غیرقابل تغییر">
                        <i class="bi bi-lock-fill"></i> قفل
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

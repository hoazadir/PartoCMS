<?php
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

// ==================== فعال/غیرفعال کردن ====================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $moduleId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("SELECT slug, is_enabled, is_core FROM modules WHERE id = ?");
    $stmt->execute([$moduleId]);
    $mod = $stmt->fetch();

    if ($mod) {
        if ($mod['is_core']) {
            $error = 'ماژول‌های اصلی قابل غیرفعال شدن نیستند';
        } else {
            if ($mod['is_enabled']) {
                $mm->disable($mod['slug']);
                getSecurity()->log('module_disabled', 'module', $moduleId, 'ماژول غیرفعال شد: ' . $mod['slug']);
            } else {
                $mm->enable($mod['slug']);
                getSecurity()->log('module_enabled', 'module', $moduleId, 'ماژول فعال شد: ' . $mod['slug']);
            }
            header('Location: modules.php?msg=updated');
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['updated' => 'وضعیت ماژول تغییر کرد'];
    $success = $msgs[$_GET['msg']] ?? '';
}

$modules = $mm->getAll();

// گروه‌بندی ماژول‌ها
$groups = [
    'content' => ['title' => '📝 مدیریت محتوا', 'color' => '#3b82f6'],
    'tools' => ['title' => '🧩 ابزارها', 'color' => '#8b5cf6'],
    'appearance' => ['title' => '🎨 ظاهر سایت', 'color' => '#ec4899'],
    'reports' => ['title' => '📈 گزارش‌گیری', 'color' => '#10b981'],
    'seo' => ['title' => '🔍 SEO و بهینه‌سازی', 'color' => '#f59e0b'],
    'admin' => ['title' => '⚙️ سیستم', 'color' => '#ef4444'],
    'other' => ['title' => '📦 سایر', 'color' => '#64748b'],
];

$grouped = [];
foreach ($modules as $m) {
    $group = $m['menu_group'] ?: 'other';
    if (!isset($grouped[$group])) $grouped[$group] = [];
    $grouped[$group][] = $m;
}

// اطلاعات آماری
$totalModules = count($modules);
$enabledModules = count(array_filter($modules, fn($m) => $m['is_enabled']));
$coreModules = count(array_filter($modules, fn($m) => $m['is_core']));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت ماژول‌ها | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }

        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-card .icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; flex-shrink: 0; }
        .stat-card .info .num { font-size: 24px; font-weight: bold; color: #1e293b; line-height: 1; }
        .stat-card .info .label { font-size: 12px; color: #7f8c8d; margin-top: 5px; }

        .group-section { margin-bottom: 30px; }
        .group-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 15px; padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .group-header h5 { margin: 0; color: #1e293b; font-size: 15px; font-weight: bold; }
        .group-header .badge-count {
            background: #f1f5f9; color: #64748b;
            font-size: 11px; padding: 3px 10px; border-radius: 10px;
            margin-right: auto;
        }

        .module-card {
            background: #fff; border-radius: 12px; padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 10px; gap: 15px;
            border-right: 4px solid #cbd5e1;
            transition: all 0.2s;
        }
        .module-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .module-card.enabled { border-right-color: #10b981; }
        .module-card.core { border-right-color: #3b82f6; }
        .module-card.disabled { opacity: 0.65; }

        .module-icon {
            width: 45px; height: 45px;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .module-card.enabled .module-icon {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        }
        .module-card.core .module-icon {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
        }

        .module-info { flex: 1; min-width: 200px; }
        .module-info .name {
            font-weight: bold; color: #1e293b; font-size: 14px;
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .module-info .desc {
            font-size: 12px; color: #64748b; margin-top: 4px;
            line-height: 1.5;
        }
        .module-info .meta {
            font-size: 11px; color: #94a3b8; margin-top: 6px;
            display: flex; gap: 12px; flex-wrap: wrap;
        }

        .badge-core {
            background: #dbeafe; color: #1e40af;
            font-size: 9px; padding: 2px 8px; border-radius: 8px;
            font-weight: bold;
        }
        .badge-version {
            background: #f1f5f9; color: #64748b;
            font-size: 9px; padding: 2px 8px; border-radius: 8px;
        }

        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 15px;
            font-size: 11px; font-weight: bold;
        }
        .status-on { background: #d1fae5; color: #065f46; }
        .status-off { background: #f1f5f9; color: #64748b; }

        .module-actions { display: flex; gap: 8px; align-items: center; }

        .toggle-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px;
            border: none; cursor: pointer;
            font-family: Tahoma; font-size: 12px; font-weight: bold;
            text-decoration: none; transition: all 0.2s;
        }
        .toggle-btn.on {
            background: #fee2e2; color: #991b1b;
        }
        .toggle-btn.on:hover { background: #fecaca; }
        .toggle-btn.off {
            background: #d1fae5; color: #065f46;
        }
        .toggle-btn.off:hover { background: #a7f3d0; }
        .toggle-btn.disabled {
            background: #f1f5f9; color: #94a3b8;
            cursor: not-allowed;
        }

        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
            .module-card { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0; color:#1e293b;">🧩 مدیریت ماژول‌ها</h4>
            <small class="text-muted">فعال/غیرفعال کردن و مدیریت دسترسی ماژول‌ها</small>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- آمار -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $totalModules ?></div>
                    <div class="label">کل ماژول‌ها</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $enabledModules ?></div>
                    <div class="label">فعال</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $coreModules ?></div>
                    <div class="label">اصلی (غیرقابل غیرفعال)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="bi bi-toggle-off"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $totalModules - $enabledModules ?></div>
                    <div class="label">غیرفعال</div>
                </div>
            </div>
        </div>
    </div>

    <!-- راهنما -->
    <div class="alert alert-info d-flex align-items-center mb-4" style="font-size: 13px;">
        <i class="bi bi-info-circle fs-4 me-3"></i>
        <div>
            <strong>راهنما:</strong> 
            ماژول‌های <span class="badge-core">اصلی</span> قابل غیرفعال شدن نیستن (چون برای کارکرد سیستم ضروری هستن).
            برای مدیریت دسترسی نقش‌ها به ماژول‌ها، به 
            <a href="roles.php" style="color: #0369a1; font-weight: bold;">مدیریت نقش‌ها</a> برو.
        </div>
    </div>

    <!-- لیست ماژول‌ها بر اساس گروه -->
    <?php foreach ($groups as $groupKey => $groupInfo): ?>
        <?php if (!empty($grouped[$groupKey])): ?>
            <div class="group-section">
                <div class="group-header">
                    <h5><?= $groupInfo['title'] ?></h5>
                    <span class="badge-count"><?= count($grouped[$groupKey]) ?> ماژول</span>
                </div>

                <?php foreach ($grouped[$groupKey] as $mod): ?>
                    <div class="module-card <?= $mod['is_enabled'] ? 'enabled' : 'disabled' ?> <?= $mod['is_core'] ? 'core' : '' ?>">
                        <div class="module-icon">
                            <?= htmlspecialchars($mod['icon'] ?: '🧩') ?>
                        </div>
                        <div class="module-info">
                            <div class="name">
                                <?= htmlspecialchars($mod['name']) ?>
                                <?php if ($mod['is_core']): ?>
                                    <span class="badge-core">🔒 اصلی</span>
                                <?php endif; ?>
                                <span class="badge-version">v<?= htmlspecialchars($mod['version']) ?></span>
                            </div>
                            <div class="desc"><?= htmlspecialchars($mod['description']) ?></div>
                            <div class="meta">
                                <span><i class="bi bi-key"></i> <?= (int)$mod['roles_count'] ?> نقش دسترسی داره</span>
                                <span><i class="bi bi-code-slash"></i> slug: <code><?= htmlspecialchars($mod['slug']) ?></code></span>
                            </div>
                        </div>
                        <div class="module-actions">
                            <span class="status-badge <?= $mod['is_enabled'] ? 'status-on' : 'status-off' ?>">
                                <?php if ($mod['is_enabled']): ?>
                                    <i class="bi bi-check-circle-fill"></i> فعال
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill"></i> غیرفعال
                                <?php endif; ?>
                            </span>

                            <?php if ($mod['is_core']): ?>
                                <span class="toggle-btn disabled" title="ماژول‌های اصلی قابل غیرفعال شدن نیستند">
                                    <i class="bi bi-lock-fill"></i> قفل
                                </span>
                            <?php else: ?>
                                <a href="?toggle=<?= $mod['id'] ?>" 
                                   class="toggle-btn <?= $mod['is_enabled'] ? 'on' : 'off' ?>"
                                   onclick="return confirm('<?= $mod['is_enabled'] ? 'غیرفعال کردن' : 'فعال کردن' ?> ماژول «<?= htmlspecialchars($mod['name']) ?>»؟')">
                                    <?php if ($mod['is_enabled']): ?>
                                        <i class="bi bi-toggle-on"></i> غیرفعال کن
                                    <?php else: ?>
                                        <i class="bi bi-toggle-off"></i> فعال کن
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

</div>

</body>
</html>

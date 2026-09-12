<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
// require_once __DIR__ . '/includes/security_config.php';
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$backup = getBackup();
$success = '';
$error = '';

// ==================== دانلود ====================
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $b = $backup->get($_GET['download']);
    if ($b) {
        $filepath = __DIR__ . '/../' . $b['filepath'];
        if (file_exists($filepath)) {
            getSecurity()->log('backup_downloaded', 'backup', $b['id'], 'پشتیبان دانلود شد');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $b['filename'] . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
    header('Location: backups.php?msg=notfound');
    exit;
}

// ==================== ذخیره فایل پشتیبان روی سرور ====================
if (isset($_GET['save']) && is_numeric($_GET['save'])) {
    // (اختیاری - برای ذخیره روی سرور خودمان)
    header('Location: backups.php');
    exit;
}

// ==================== حذف ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $backupId = (int) $_GET['delete'];
    $b = $backup->get($backupId);
    if ($b && $backup->delete($backupId)) {
        header('Location: backups.php?msg=deleted&name=' . urlencode($b['filename']));
        exit;
    }
    header('Location: backups.php?msg=notfound');
    exit;
}

// ==================== بازیابی ====================
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        try {
            $result = $backup->restoreDatabase((int) $_GET['restore']);
            $msg = 'restored&executed=' . $result['executed'] . '&errors=' . count($result['errors']);
            if (!empty($result['errors'])) {
                $_SESSION['restore_errors'] = array_slice($result['errors'], 0, 10);
            }
            header('Location: backups.php?msg=' . $msg);
            exit;
        } catch (Exception $e) {
            $error = 'خطا در بازیابی: ' . $e->getMessage();
        }
    }
}

// ==================== پردازش POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    getSecurity()->requireCsrf();
    getSecurity()->requireRateLimit('backup_create', 5, 300);

    $action = $_POST['action'] ?? '';
    $description = trim($_POST['description'] ?? '');

    try {
        if ($action === 'backup_db') {
            $result = $backup->backupDatabase($description ?: 'پشتیبان دستی دیتابیس');
            header('Location: backups.php?msg=created&size=' . $result['size'] . '&name=' . urlencode($result['filename']));
            exit;
        }

        if ($action === 'backup_full') {
            $result = $backup->backupFull($description ?: 'پشتیبان کامل');
            header('Location: backups.php?msg=created_full&size=' . $result['size'] . '&name=' . urlencode($result['filename']));
            exit;
        }

        if ($action === 'upload_restore' && isset($_FILES['sql_file'])) {
            $result = $backup->uploadAndRestore($_FILES['sql_file']);
            $msg = 'restored&executed=' . $result['executed'] . '&errors=' . count($result['errors']);
            if (!empty($result['errors'])) {
                $_SESSION['restore_errors'] = array_slice($result['errors'], 0, 10);
            }
            header('Location: backups.php?msg=' . $msg);
            exit;
        }

        if ($action === 'clean_old') {
            $days = (int) ($_POST['keep_days'] ?? 30);
            $count = $backup->cleanOld($days);
            header('Location: backups.php?msg=cleaned&count=' . $count);
            exit;
        }

        // حذف دسته‌جمعی
        if ($action === 'bulk_delete' && !empty($_POST['backup_ids'])) {
            $ids = array_filter((array) $_POST['backup_ids'], 'is_numeric');
            $count = 0;
            foreach ($ids as $id) {
                if ($backup->delete((int) $id)) $count++;
            }
            header('Location: backups.php?msg=bulk_deleted&count=' . $count);
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ==================== پیام‌ها ====================
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $success = '✅ پشتیبان دیتابیس ساخته شد';
            if (!empty($_GET['name'])) $success .= ' — <code style="color:#155724;background:rgba(255,255,255,0.5);padding:2px 6px;border-radius:4px;">' . htmlspecialchars($_GET['name']) . '</code>';
            if (!empty($_GET['size'])) $success .= ' (' . Backup::formatSize((int)$_GET['size']) . ')';
            break;
        case 'created_full':
            $success = '✅ پشتیبان کامل ساخته شد';
            if (!empty($_GET['name'])) $success .= ' — <code style="color:#155724;background:rgba(255,255,255,0.5);padding:2px 6px;border-radius:4px;">' . htmlspecialchars($_GET['name']) . '</code>';
            if (!empty($_GET['size'])) $success .= ' (' . Backup::formatSize((int)$_GET['size']) . ')';
            break;
        case 'deleted':
            $success = '🗑 پشتیبان حذف شد';
            if (!empty($_GET['name'])) $success .= ': ' . htmlspecialchars($_GET['name']);
            break;
        case 'bulk_deleted':
            $success = '🗑 ' . ((int)($_GET['count'] ?? 0)) . ' پشتیبان حذف شد';
            break;
        case 'cleaned':
            $success = '🧹 ' . ((int)($_GET['count'] ?? 0)) . ' پشتیبان قدیمی حذف شد';
            break;
        case 'restored':
            $success = '✅ بازیابی انجام شد. ' . ((int)($_GET['executed'] ?? 0)) . ' دستور با موفقیت اجرا شد.';
            if ((int)($_GET['errors'] ?? 0) > 0) {
                $success .= ' ⚠️ ' . ((int)$_GET['errors']) . ' خطا.';
            }
            break;
        case 'notfound':
            $error = 'فایل پشتیبان یافت نشد';
            break;
    }
}

// ==================== داده‌ها ====================
$backups = $backup->getAll();
$totalSize = array_sum(array_column($backups, 'size'));

// فیلتر
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'database') {
    $backups = array_filter($backups, fn($b) => $b['type'] === 'database');
} elseif ($filter === 'full') {
    $backups = array_filter($backups, fn($b) => $b['type'] === 'full');
}

$countAll = count($backup->getAll());
$countDb = count(array_filter($backup->getAll(), fn($b) => $b['type'] === 'database'));
$countFull = count(array_filter($backup->getAll(), fn($b) => $b['type'] === 'full'));

// اطلاعات دیتابیس فعلی
$currentInfo = [
    'tables' => (function($pdo) {
        try { return $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'")->fetchColumn(); }
        catch(Exception $e) { return 0; }
    })($pdo),
    'size' => (function($pdo) {
        try {
            $result = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
            return $result ? $result . ' MB' : '0 MB';
        } catch(Exception $e) { return 'نامشخص'; }
    })($pdo),
];

// تابع تبدیل تاریخ میلادی به شمسی (تقریبی)
function toJalali($timestamp) {
    $date = new DateTime($timestamp);
    // الگوریتم تبدیل
    $gy = (int) $date->format('Y');
    $gm = (int) $date->format('n');
    $gd = (int) $date->format('j');
    
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * ((int)($days / 12053)));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $hour = $date->format('H:i');
    
    return $jd . ' ' . $months[$jm - 1] . ' ' . $jy . ' - ساعت ' . $hour;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پشتیبان‌گیری | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; flex-shrink: 0; }
        .stat-card .info .num { font-size: 20px; font-weight: bold; color: #1e293b; line-height: 1; }
        .stat-card .info .label { font-size: 11px; color: #7f8c8d; margin-top: 5px; }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-tab {
            padding: 8px 18px; border-radius: 25px; background: #fff;
            color: #64748b; text-decoration: none; font-size: 13px;
            border: 1px solid #e2e8f0; transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .filter-tab:hover { background: #f1f5f9; color: #1e293b; }
        .filter-tab.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .filter-tab .count {
            background: rgba(255,255,255,0.2); border-radius: 10px;
            padding: 1px 7px; font-size: 11px;
        }
        .filter-tab.active .count { background: rgba(255,255,255,0.3); }

        /* Backup items */
        .backup-item {
            background: #fff; border-radius: 12px; padding: 18px 20px;
            margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 15px;
            border-right: 5px solid #3b82f6;
            transition: all 0.2s;
        }
        .backup-item:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .backup-item.type-full { border-right-color: #8b5cf6; }
        .backup-item .info { flex: 1; min-width: 220px; }
        .backup-item .name {
            font-weight: bold; color: #1e293b; font-size: 14px;
            direction: ltr; text-align: left; font-family: monospace;
            word-break: break-all; margin-bottom: 6px;
        }
        .backup-item .meta {
            font-size: 12px; color: #64748b; line-height: 1.8;
            display: flex; flex-wrap: wrap; gap: 12px;
        }
        .backup-item .meta span { display: flex; align-items: center; gap: 4px; }

        .type-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: bold;
        }
        .type-database { background: #dbeafe; color: #1e40af; }
        .type-full { background: #ede9fe; color: #6d28d9; }

        .backup-item .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .backup-item .actions .btn {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; padding: 7px 14px;
        }

        /* Empty state */
        .empty-backups { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-backups i { font-size: 70px; display: block; margin-bottom: 20px; opacity: 0.5; }
        .empty-backups h5 { color: #64748b; }

        /* Bulk actions */
        .bulk-bar {
            background: #fef3c7; padding: 12px 20px; border-radius: 10px;
            display: none; align-items: center; justify-content: space-between;
            margin-bottom: 15px; border: 1px solid #fbbf24;
        }
        .bulk-bar.active { display: flex; }

        /* Progress overlay */
        .loading-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            display: none; align-items: center; justify-content: center;
            z-index: 9999; color: #fff;
        }
        .loading-overlay.active { display: flex; }
        .loading-box {
            background: #fff; color: #1e293b; padding: 40px;
            border-radius: 16px; text-align: center; min-width: 300px;
        }
        .loading-spinner {
            width: 50px; height: 50px; border: 4px solid #e2e8f0;
            border-top-color: #3b82f6; border-radius: 50%;
            animation: spin 1s linear infinite; margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
            .backup-item .actions { width: 100%; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 style="margin:0; color:#1e293b;">💾 پشتیبان‌گیری و بازیابی</h4>
            <small class="text-muted">مدیریت پشتیبان‌های دیتابیس و فایل‌ها</small>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            ❌ <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['restore_errors'])): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <strong>⚠️ برخی خطاها در بازیابی:</strong>
            <ul style="margin: 10px 0 0; font-size: 12px; max-height: 200px; overflow-y: auto;">
                <?php foreach ($_SESSION['restore_errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['restore_errors']); ?>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="bi bi-database"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $currentInfo['tables'] ?></div>
                    <div class="label">جداول</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="bi bi-hdd"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $currentInfo['size'] ?></div>
                    <div class="label">حجم دیتابیس</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="bi bi-archive"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $countAll ?></div>
                    <div class="label">کل پشتیبان‌ها</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="bi bi-pie-chart"></i>
                </div>
                <div class="info">
                    <div class="num"><?= Backup::formatSize($totalSize) ?></div>
                    <div class="label">حجم کل</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create + Restore Cards -->
    <div class="row g-3 mb-4">
        <!-- Create -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-plus-circle" style="color: #10b981;"></i>
                    ساخت پشتیبان جدید
                </div>
                <div class="card-body">
                    <form method="post" id="backupForm" onsubmit="showLoading('در حال ساخت پشتیبان...')">
                        <?= getSecurity()->csrfField() ?>
                        <input type="hidden" name="action" value="backup_db">
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 12px;">توضیحات (اختیاری)</label>
                            <input type="text" name="description" class="form-control" placeholder="مثلاً: قبل از بروزرسانی">
                        </div>
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-database-fill-down"></i> پشتیبان دیتابیس (سریع)
                        </button>
                    </form>

                    <form method="post" onsubmit="showLoading('در حال ساخت پشتیبان کامل... (ممکن است چند دقیقه طول بکشد)'); return confirm('پشتیبان کامل شامل فایل‌ها و دیتابیس است.\n\nادامه؟');">
                        <?= getSecurity()->csrfField() ?>
                        <input type="hidden" name="action" value="backup_full">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-file-zip"></i> پشتیبان کامل (دیتابیس + فایل‌ها)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Restore -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-arrow-counterclockwise" style="color: #f59e0b;"></i>
                    بازیابی از فایل
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" style="font-size: 12px; padding: 10px;">
                        ⚠️ <strong>هشدار:</strong> بازیابی، داده‌های فعلی را جایگزین می‌کند.
                    </div>
                    <form method="post" enctype="multipart/form-data" onsubmit="return confirm('⚠️ آیا مطمئن هستید؟ داده‌های فعلی جایگزین می‌شوند.')">
                        <?= getSecurity()->csrfField() ?>
                        <input type="hidden" name="action" value="upload_restore">
                        <div class="mb-3">
                            <label class="form-label" style="font-size: 12px;">فایل SQL</label>
                            <input type="file" name="sql_file" class="form-control" accept=".sql,.txt" required>
                            <small class="text-muted">حداکثر ۵۰ مگابایت</small>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="bi bi-upload"></i> آپلود و بازیابی
                        </button>
                    </form>

                    <hr>

                    <form method="post" onsubmit="return confirm('پشتیبان‌های قدیمی‌تر از این تعداد روز حذف شوند؟')">
                        <?= getSecurity()->csrfField() ?>
                        <input type="hidden" name="action" value="clean_old">
                        <label class="form-label" style="font-size: 12px;">حذف پشتیبان‌های قدیمی‌تر از:</label>
                        <div class="input-group">
                            <input type="number" name="keep_days" class="form-control" value="30" min="1" max="365">
                            <span class="input-group-text">روز</span>
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i> پاک‌سازی
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul" style="color: #3b82f6;"></i> پشتیبان‌های موجود (<?= count($backups) ?>)</span>
            <?php if ($totalSize > 0): ?>
                <small class="text-muted">حجم کل: <?= Backup::formatSize($totalSize) ?></small>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Filter Tabs -->
            <?php if ($countAll > 0): ?>
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-collection"></i> همه
                    <span class="count"><?= $countAll ?></span>
                </a>
                <a href="?filter=database" class="filter-tab <?= $filter === 'database' ? 'active' : '' ?>">
                    <i class="bi bi-database"></i> دیتابیس
                    <span class="count"><?= $countDb ?></span>
                </a>
                <a href="?filter=full" class="filter-tab <?= $filter === 'full' ? 'active' : '' ?>">
                    <i class="bi bi-file-zip"></i> کامل
                    <span class="count"><?= $countFull ?></span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Bulk Delete Bar -->
            <div class="bulk-bar" id="bulkBar">
                <span><i class="bi bi-check-square"></i> <strong id="selectedCount">0</strong> مورد انتخاب شده</span>
                <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">
                    <i class="bi bi-trash"></i> حذف موارد انتخاب‌شده
                </button>
            </div>

            <!-- List -->
            <?php if (empty($backups)): ?>
                <div class="empty-backups">
                    <i class="bi bi-inbox"></i>
                    <h5>هنوز پشتیبانی ساخته نشده</h5>
                    <p>از دکمه‌های بالا برای ساخت اولین پشتیبان استفاده کن</p>
                </div>
            <?php else: ?>
                <form method="post" id="bulkForm">
                    <?= getSecurity()->csrfField() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <?php foreach ($backups as $b): ?>
                        <div class="backup-item type-<?= htmlspecialchars($b['type']) ?>">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="checkbox" name="backup_ids[]" value="<?= $b['id'] ?>" class="backup-checkbox" onchange="updateBulkBar()" style="width:18px;height:18px;cursor:pointer;">
                                <div class="info">
                                    <div class="name">
                                        <span class="type-badge type-<?= htmlspecialchars($b['type']) ?>">
                                            <?= $b['type'] === 'full' ? '📦 کامل' : '💾 دیتابیس' ?>
                                        </span>
                                        <?= htmlspecialchars($b['filename']) ?>
                                    </div>
                                    <div class="meta">
                                        <span><i class="bi bi-calendar3"></i> <?= toJalali($b['created_at']) ?></span>
                                        <span><i class="bi bi-hdd"></i> <?= Backup::formatSize($b['size']) ?></span>
                                        <span><i class="bi bi-table"></i> <?= $b['tables_count'] ?> جدول</span>
                                        <span><i class="bi bi-list-ol"></i> <?= number_format($b['records_count']) ?> رکورد</span>
                                        <?php if ($b['creator_name']): ?>
                                            <span><i class="bi bi-person"></i> <?= htmlspecialchars($b['creator_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($b['description']): ?>
                                        <div style="font-size: 12px; color: #64748b; margin-top: 6px; background: #f8fafc; padding: 5px 10px; border-radius: 6px;">
                                            💬 <?= htmlspecialchars($b['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="actions">
                                <a href="?download=<?= $b['id'] ?>" class="btn btn-sm btn-success" title="دانلود فایل">
                                    <i class="bi bi-download"></i> دانلود
                                </a>
                                <?php if ($b['type'] === 'database'): ?>
                                    <a href="?restore=<?= $b['id'] ?>&confirm=yes" 
                                       class="btn btn-sm btn-warning"
                                       onclick="return confirm('⚠️ بازیابی از این پشتیبان؟\n\nفایل: <?= htmlspecialchars($b['filename']) ?>\nتاریخ: <?= toJalali($b['created_at']) ?>\n\nهشدار: داده‌های فعلی جایگزین می‌شوند!')"
                                       title="بازیابی از این نسخه">
                                        <i class="bi bi-arrow-counterclockwise"></i> بازیابی
                                    </a>
                                <?php endif; ?>
                                <a href="?delete=<?= $b['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('🗑 حذف پشتیبان؟\n\nفایل: <?= htmlspecialchars($b['filename']) ?>\nتاریخ: <?= toJalali($b['created_at']) ?>\nحجم: <?= Backup::formatSize($b['size']) ?>\n\nاین عمل قابل بازگشت نیست!')"
                                   title="حذف">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <div class="loading-spinner"></div>
        <h5 id="loadingText">در حال پردازش...</h5>
        <small class="text-muted">لطفاً صبر کنید</small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==================== Loading Overlay ====================
function showLoading(text) {
    document.getElementById('loadingText').textContent = text || 'در حال پردازش...';
    document.getElementById('loadingOverlay').classList.add('active');
}

// ==================== Bulk Selection ====================
function updateBulkBar() {
    const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
    const count = checkboxes.length;
    const bar = document.getElementById('bulkBar');
    document.getElementById('selectedCount').textContent = count;
    
    if (count > 0) {
        bar.classList.add('active');
    } else {
        bar.classList.remove('active');
    }
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.backup-checkbox:checked');
    if (checkboxes.length === 0) return;
    
    if (!confirm('🗑 حذف ' + checkboxes.length + ' پشتیبان انتخاب شده؟\n\nاین عمل قابل بازگشت نیست!')) return;
    
    showLoading('در حال حذف...');
    document.getElementById('bulkForm').submit();
}

// بستن overlay در صورت برگشت به صفحه
window.addEventListener('pageshow', function() {
    document.getElementById('loadingOverlay').classList.remove('active');
});

// ==================== Auto-dismiss alerts ====================
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 6000);
</script>

</body>
</html>

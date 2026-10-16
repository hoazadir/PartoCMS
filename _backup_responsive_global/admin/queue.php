<?php
/**
 * PartoCMS - Translation Queue Monitor
 * پنل مدیریت و مانیتور صف ترجمه
 *
 * @version 1.0
 * @date 2026-09-17
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/queue_manager.php';
require_once __DIR__ . '/includes/environment.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

$queue = new QueueManager($pdo);
$message = '';
$messageType = '';

// ============================================================
//   عملیات POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- پردازش فوری ----------
    if ($action === 'process_now') {
        $max = (int)($_POST['max_jobs'] ?? 5);
        $max = min(max($max, 1), 20);

        try {
            $script = realpath(__DIR__ . '/cron_translation_queue.php');
            if (!$script) {
                throw new Exception('cron script not found');
            }

            $php = PHP_BINARY ?: 'php';
            $logFile = __DIR__ . '/../logs/translation_queue.log';

            // اجرا در پس‌زمینه
            if (Environment::hasSetsid()) {
                $setsid = trim((string)@shell_exec('which setsid 2>/dev/null'));
                $cmd = sprintf(
                    '%s nohup %s %s %d >> %s 2>&1 < /dev/null &',
                    escapeshellcmd($setsid),
                    escapeshellcmd($php),
                    escapeshellarg($script),
                    $max,
                    escapeshellarg($logFile)
                );
            } else {
                $cmd = sprintf(
                    'nohup %s %s %d >> %s 2>&1 < /dev/null &',
                    escapeshellcmd($php),
                    escapeshellarg($script),
                    $max,
                    escapeshellarg($logFile)
                );
            }
            @exec($cmd);

            $message = "✅ پردازش $max job در پس‌زمینه شروع شد. صفحه رو رفرش کن.";
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // ---------- ریست گیرکرده‌ها ----------
    elseif ($action === 'reset_stuck') {
        $n = $queue->resetStuck();
        $message = "🔄 $n job گیرکرده ریست شد";
        $messageType = 'success';
    }

    // ---------- Retry Failed ----------
    elseif ($action === 'retry_failed') {
        $n = $queue->retryFailed();
        $message = "↩️ $n job برای retry آماده شد";
        $messageType = 'success';
    }

    // ---------- پاک کردن jobهای قدیمی ----------
    elseif ($action === 'clean_old') {
        $days = (int)($_POST['days'] ?? 7);
        $n = $queue->cleanOldJobs($days);
        $message = "🧹 $n job قدیمی پاک شد";
        $messageType = 'success';
    }

    // ---------- پاک کردن همه ----------
    elseif ($action === 'clear_all') {
        $queue->clearAll();
        $message = "🗑️ صف به‌طور کامل پاک شد";
        $messageType = 'success';
    }

    // ---------- افزودن محتوا به صف ----------
    elseif ($action === 'enqueue_content') {
        $contentId = (int)($_POST['content_id'] ?? 0);
        if ($contentId > 0) {
            $r = $queue->enqueueContent($contentId, $_SESSION['user_id'] ?? null, 5);
            if (!empty($r['ok'])) {
                $message = "✅ {$r['added']} job اضافه شد (skip: {$r['skipped']})";
                $messageType = 'success';
            } else {
                $message = "❌ خطا: " . ($r['error'] ?? '?');
                $messageType = 'danger';
            }
        }
    }

    // ---------- حذف یک job ----------
    elseif ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            try {
                $pdo->prepare("DELETE FROM translation_queue WHERE id = ?")->execute([$jobId]);
                $message = "🗑️ Job #$jobId حذف شد";
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = "❌ خطا: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }

    // ---------- ریست یک job ----------
    elseif ($action === 'reset_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId > 0) {
            try {
                $pdo->prepare("
                    UPDATE translation_queue
                    SET status = 'pending', attempts = 0, error_message = NULL
                    WHERE id = ?
                ")->execute([$jobId]);
                $message = "🔄 Job #$jobId ریست شد";
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = "❌ خطا: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// ============================================================
//   فیلترها
// ============================================================
$filterStatus = trim($_GET['status'] ?? '');
$filterContentId = (int)($_GET['content_id'] ?? 0);
$filterLang = trim($_GET['lang'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($filterStatus !== '') {
    $where[] = "q.status = ?";
    $params[] = $filterStatus;
}
if ($filterContentId > 0) {
    $where[] = "q.content_id = ?";
    $params[] = $filterContentId;
}
if ($filterLang !== '') {
    $where[] = "q.language_code = ?";
    $params[] = $filterLang;
}

$whereSql = implode(" AND ", $where);

// تعداد کل
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM translation_queue q WHERE $whereSql");
    $stmt->execute($params);
    $totalFiltered = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $totalFiltered = 0;
}
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));

// لیست jobها
$jobs = [];
try {
    $sql = "
        SELECT 
            q.*,
            ci.title as content_title,
            ci.status as content_status,
            l.native_name as lang_name,
            l.flag as lang_flag
        FROM translation_queue q
        LEFT JOIN content_items ci ON ci.id = q.content_id
        LEFT JOIN languages l ON l.code = q.language_code
        WHERE $whereSql
        ORDER BY 
            CASE q.status
                WHEN 'processing' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'failed' THEN 3
                WHEN 'done' THEN 4
                ELSE 5
            END,
            q.priority ASC,
            q.id DESC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $jobs = [];
}

// آمار
$stats = $queue->getStats();

// زبان‌ها (برای فیلتر)
$languages = [];
try {
    $languages = $pdo->query("
        SELECT DISTINCT language_code FROM translation_queue
        WHERE language_code IS NOT NULL
        ORDER BY language_code
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

// محیط
$env = Environment::getInfo();

// تنظیمات فعال بودن
$autoEnabled = false;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_translate_on_publish' LIMIT 1");
    $stmt->execute();
    $autoEnabled = ($stmt->fetchColumn() === '1');
} catch (Throwable $e) {}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>صف ترجمه — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);text-align:center;position:relative;overflow:hidden}
.stat-card .value{font-size:26px;font-weight:bold;margin-bottom:5px}
.stat-card .label{font-size:12px;color:#64748b}
.stat-card.pending .value{color:#f59e0b}
.stat-card.processing .value{color:#0891b2}
.stat-card.done .value{color:#10b981}
.stat-card.failed .value{color:#ef4444}
.stat-card.total .value{color:#6366f1}
.stat-card.processing::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#0891b2,#06b6d4);animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* Cards */
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.card .header h5{margin:0;font-size:15px;color:#1e293b}
.card .body{padding:20px}

/* Buttons */
.btn-a{padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:12px;color:#fff;display:inline-flex;align-items:center;gap:5px;transition:.2s;text-decoration:none}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-info-a{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.btn-sm{padding:5px 10px;font-size:11px}

/* Environment box */
.env-box{background:#ecfeff;border-right:4px solid #0891b2;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:12px;line-height:1.8}
.env-badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:bold;margin:2px}
.env-badge.ok{background:#d1fae5;color:#065f46}
.env-badge.no{background:#fee2e2;color:#991b1b}
.env-badge.info{background:#e0f2fe;color:#0369a1}

/* Filters */
.filter-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end}
.filter-group label{display:block;font-size:11px;color:#64748b;margin-bottom:4px;font-weight:bold}
.filter-group select,.filter-group input{width:100%;padding:7px 11px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}

/* Table */
.queue-table{width:100%;border-collapse:collapse;font-size:12px}
.queue-table th{background:#334155;color:#fff;padding:10px 8px;text-align:right;font-size:11px;white-space:nowrap}
.queue-table td{padding:9px 8px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.queue-table tr:hover td{background:#f8fafc}
.queue-table tr.processing{background:#ecfeff}
.queue-table tr.failed{background:#fef2f2}

/* Status badges */
.badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:bold;white-space:nowrap}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-processing{background:#cffafe;color:#155e75;animation:pulse 1.5s infinite}
.badge-done{background:#d1fae5;color:#065f46}
.badge-failed{background:#fee2e2;color:#991b1b}
.badge-skipped{background:#f1f5f9;color:#64748b}

/* Priority */
.priority{display:inline-block;min-width:20px;padding:2px 6px;border-radius:8px;font-size:10px;font-weight:bold;background:#e0e7ff;color:#4338ca}

/* Pagination */
.pagination .page-link{color:#0891b2;font-size:13px}
.pagination .active .page-link{background:#0891b2;border-color:#0891b2;color:#fff}

/* Action row */
.actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.action-card{background:#fff;border:2px solid #e2e8f0;border-radius:10px;padding:15px;text-align:center;transition:.2s}
.action-card:hover{border-color:#0891b2;transform:translateY(-2px);box-shadow:0 4px 12px rgba(8,145,178,.15)}
.action-card h6{margin:0 0 8px;font-size:13px;color:#1e293b}
.action-card .desc{font-size:11px;color:#64748b;margin-bottom:10px;min-height:32px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>⚙️ صف ترجمه</h1>
            <p>مدیریت و مانیتور jobهای ترجمه در پس‌زمینه</p>
        </div>
        <a href="translator_settings.php" class="btn-a btn-secondary-a">
            <i class="bi bi-gear"></i> تنظیمات
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Environment -->
    <div class="card">
        <div class="header"><h5>🖥️ محیط اجرا</h5></div>
        <div class="body">
            <div class="env-box">
                <strong>OS:</strong> <span class="env-badge info"><?= htmlspecialchars($env['os']) ?></span>
                <strong>PHP:</strong> <span class="env-badge info"><?= htmlspecialchars($env['php_version']) ?></span>
                <br>
                <strong>Shell:</strong>
                <span class="env-badge <?= $env['has_shell_exec'] ? 'ok' : 'no' ?>">
                    shell_exec <?= $env['has_shell_exec'] ? '✅' : '❌' ?>
                </span>
                <span class="env-badge <?= $env['has_exec'] ? 'ok' : 'no' ?>">
                    exec <?= $env['has_exec'] ? '✅' : '❌' ?>
                </span>
                <span class="env-badge <?= $env['has_setsid'] ? 'ok' : 'no' ?>">
                    setsid <?= $env['has_setsid'] ? '✅' : '❌' ?>
                </span>
                <br>
                <strong>Argos آفلاین:</strong>
                <span class="env-badge <?= $env['has_argos'] ? 'ok' : 'no' ?>">
                    <?= $env['has_argos'] ? '✅ نصب شده' : '❌ نصب نیست' ?>
                </span>
                <br>
                <strong>روش اجرا:</strong>
                <span class="env-badge info"><?= htmlspecialchars($env['background_method']) ?></span>
                <?php if ($env['background_method'] === 'queue_only'): ?>
                    <span style="color:#f59e0b;font-size:11px">⚠️ فقط با cron کار می‌کند</span>
                <?php endif; ?>
                <br>
                <strong>ترجمه خودکار در انتشار:</strong>
                <span class="env-badge <?= $autoEnabled ? 'ok' : 'no' ?>">
                    <?= $autoEnabled ? '✅ فعال' : '⛔ غیرفعال' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="value"><?= number_format($stats['total']) ?></div>
            <div class="label">کل</div>
        </div>
        <div class="stat-card pending">
            <div class="value"><?= number_format($stats['pending']) ?></div>
            <div class="label">در انتظار</div>
        </div>
        <div class="stat-card processing">
            <div class="value"><?= number_format($stats['processing']) ?></div>
            <div class="label">در حال پردازش</div>
        </div>
        <div class="stat-card done">
            <div class="value"><?= number_format($stats['done']) ?></div>
            <div class="label">انجام شده</div>
        </div>
        <div class="stat-card failed">
            <div class="value"><?= number_format($stats['failed']) ?></div>
            <div class="label">خطا</div>
        </div>
    </div>

    <!-- Actions -->
    <div class="card">
        <div class="header"><h5>🎯 عملیات سریع</h5></div>
        <div class="body">
            <div class="actions-grid">

                <div class="action-card">
                    <h6>🚀 پردازش فوری</h6>
                    <div class="desc">پردازش دستی jobها (جدا از cron)</div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="process_now">
                        <input type="hidden" name="max_jobs" value="5">
                        <button type="submit" class="btn-a btn-primary-a btn-sm" onclick="return confirm('پردازش ۵ job؟')">
                            <i class="bi bi-play-fill"></i> شروع
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <h6>🔄 ریست گیرکرده‌ها</h6>
                    <div class="desc">jobهای processing بیش از ۱۰ دقیقه</div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="reset_stuck">
                        <button type="submit" class="btn-a btn-warning-a btn-sm">
                            <i class="bi bi-arrow-clockwise"></i> ریست
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <h6>↩️ Retry Failed</h6>
                    <div class="desc">آماده‌سازی jobهای fail برای retry</div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="retry_failed">
                        <button type="submit" class="btn-a btn-info-a btn-sm" onclick="return confirm('همه jobهای failed ریست شوند؟')">
                            <i class="bi bi-arrow-counterclockwise"></i> Retry
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <h6>🧹 پاک کردن jobهای قدیمی</h6>
                    <div class="desc">jobهای done/failed بیشتر از ۷ روز</div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="clean_old">
                        <input type="hidden" name="days" value="7">
                        <button type="submit" class="btn-a btn-secondary-a btn-sm" onclick="return confirm('پاکسازی؟')">
                            <i class="bi bi-trash"></i> پاکسازی
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <h6>🗑️ پاک کردن کل صف</h6>
                    <div class="desc" style="color:#ef4444">⚠️ قابل بازگشت نیست!</div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn-a btn-danger-a btn-sm" onclick="return confirm('همه jobها حذف شوند؟ این عمل قابل بازگشت نیست!')">
                            <i class="bi bi-x-circle"></i> پاک کن
                        </button>
                    </form>
                </div>

            </div>

            <hr style="margin:20px 0">

            <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                <input type="hidden" name="action" value="enqueue_content">
                <div style="flex:1;min-width:200px">
                    <label style="font-size:12px;font-weight:bold;display:block;margin-bottom:5px">➕ افزودن محتوا به صف</label>
                    <input type="number" name="content_id" placeholder="Content ID" style="width:100%;padding:8px;border:2px solid #e2e8f0;border-radius:8px" required>
                </div>
                <button type="submit" class="btn-a btn-success-a">
                    <i class="bi bi-plus-lg"></i> افزودن
                </button>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="header"><h5>🔍 فیلترها</h5></div>
        <div class="body">
            <form method="get" class="filter-row">
                <div class="filter-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">— همه —</option>
                        <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>در انتظار</option>
                        <option value="processing" <?= $filterStatus==='processing'?'selected':'' ?>>در حال پردازش</option>
                        <option value="done" <?= $filterStatus==='done'?'selected':'' ?>>انجام شده</option>
                        <option value="failed" <?= $filterStatus==='failed'?'selected':'' ?>>خطا</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>زبان</label>
                    <select name="lang">
                        <option value="">— همه —</option>
                        <?php foreach ($languages as $l): ?>
                            <option value="<?= htmlspecialchars($l) ?>" <?= $filterLang===$l?'selected':'' ?>>
                                <?= htmlspecialchars($l) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Content ID</label>
                    <input type="number" name="content_id" value="<?= $filterContentId ?: '' ?>" placeholder="مثلاً 9">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-a btn-primary-a" style="width:100%;justify-content:center">
                        <i class="bi bi-funnel"></i> اعمال
                    </button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="queue.php" class="btn-a btn-secondary-a" style="width:100%;justify-content:center">
                        <i class="bi bi-x-circle"></i> پاک کردن
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Jobs Table -->
    <div class="card">
        <div class="header">
            <h5>📋 Jobها — <?= number_format($totalFiltered) ?> نتیجه</h5>
            <div>
                <button onclick="location.reload()" class="btn-a btn-primary-a btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> رفرش
                </button>
            </div>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="body" style="text-align:center;padding:50px;color:#64748b">
                <i class="bi bi-inbox" style="font-size:60px;display:block;margin-bottom:15px;color:#cbd5e1"></i>
                <h5 style="color:#475569">هیچ jobی یافت نشد</h5>
                <p style="font-size:13px">فیلترها را تغییر دهید یا محتوایی برای ترجمه منتشر کنید.</p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>محتوا</th>
                        <th>زبان</th>
                        <th>وضعیت</th>
                        <th>اولویت</th>
                        <th>تلاش</th>
                        <th>شروع</th>
                        <th>پایان</th>
                        <th>خطا</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <?php
                        $statusClass = 'badge-' . $job['status'];
                        $rowClass = '';
                        if ($job['status'] === 'processing') $rowClass = 'processing';
                        elseif ($job['status'] === 'failed') $rowClass = 'failed';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><strong>#<?= $job['id'] ?></strong></td>
                            <td>
                                <a href="../post.php?id=<?= $job['content_id'] ?>" target="_blank" style="color:#0891b2;text-decoration:none;font-size:11px">
                                    #<?= $job['content_id'] ?>
                                </a>
                                <div style="font-size:10px;color:#64748b;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($job['content_title'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_substr($job['content_title'] ?? '-', 0, 30)) ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:11px;font-weight:bold">
                                    <?= $job['lang_flag'] ?: '🌐' ?>
                                    <?= htmlspecialchars($job['language_code']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $statusClass ?>">
                                    <?php
                                    echo match($job['status']) {
                                        'pending' => '⏳ در انتظار',
                                        'processing' => '⚙️ در حال پردازش',
                                        'done' => '✅ انجام شده',
                                        'failed' => '❌ خطا',
                                        'skipped' => '⏭ رد شده',
                                        default => $job['status'],
                                    };
                                    ?>
                                </span>
                            </td>
                            <td><span class="priority"><?= $job['priority'] ?></span></td>
                            <td style="text-align:center">
                                <strong><?= $job['attempts'] ?></strong>
                                <span style="font-size:10px;color:#94a3b8">/<?= $job['max_attempts'] ?></span>
                            </td>
                            <td style="font-size:10px;color:#64748b;white-space:nowrap">
                                <?= $job['started_at'] ? date('H:i:s', strtotime($job['started_at'])) : '—' ?>
                            </td>
                            <td style="font-size:10px;color:#64748b;white-space:nowrap">
                                <?= $job['completed_at'] ? date('H:i:s', strtotime($job['completed_at'])) : '—' ?>
                            </td>
                            <td style="font-size:10px;color:#ef4444;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($job['error_message'] ?? '') ?>">
                                <?= $job['error_message'] ? htmlspecialchars(mb_substr($job['error_message'], 0, 40)) : '—' ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:3px">
                                    <?php if ($job['status'] === 'failed' || $job['status'] === 'processing'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="action" value="reset_job">
                                            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                            <button type="submit" class="btn-a btn-warning-a btn-sm" title="ریست">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('حذف job #<?= $job['id'] ?>؟')">
                                        <input type="hidden" name="action" value="delete_job">
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" class="btn-a btn-danger-a btn-sm" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="body" style="border-top:1px solid #e2e8f0">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← قبلی</a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">بعدی →</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Help -->
    <div class="card">
        <div class="header"><h5>📖 راهنما</h5></div>
        <div class="body">
            <div class="env-box" style="border-color:#6366f1;background:#eef2ff">
                <strong>🎯 صف چطور کار می‌کند؟</strong>
                <ol style="margin:8px 0 0 20px;padding:0;font-size:12px;line-height:1.8">
                    <li>وقتی مقاله‌ای <strong>publish</strong> می‌شود، به تعداد زبان‌های فعال job ساخته می‌شود</li>
                    <li>هر job در صف با وضعیت <span class="badge badge-pending">در انتظار</span> قرار می‌گیرد</li>
                    <li>Cron هر ۲ دقیقه صف را بررسی و jobها را پردازش می‌کند</li>
                    <li>هر job بعد از موفقیت → <span class="badge badge-done">انجام شده</span></li>
                    <li>در صورت خطا → retry خودکار تا ۳ بار، سپس <span class="badge badge-failed">خطا</span></li>
                </ol>
            </div>
        </div>
    </div>

</div>

<script>
// Auto-refresh هر ۱۵ ثانیه اگه processing داره
<?php if ($stats['processing'] > 0 || $stats['pending'] > 0): ?>
setTimeout(() => location.reload(), 15000);
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/**
 * PartoCMS - Backup Management Page
 */
require_once __DIR__ . '/auth_check.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();
require_once __DIR__ . '/includes/backup_manager.php';
require_once __DIR__ . '/includes/telegram_notifier.php';

$bm = new BackupManager($pdo);
$message = '';
$messageType = '';

// Telegram
$tgToken = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
$tgChatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
$telegram = ($tgToken && $tgChatId) ? new TelegramNotifier($tgToken, $tgChatId) : null;

// ==================== ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- بکاپ جدید ----------
    if ($action === 'create') {
        $result = $bm->backupDatabase();

        if ($result['ok']) {
            $message = '✅ بکاپ با موفقیت ایجاد شد — ' . $result['file'] . ' (' . $result['size_human'] . ')';
            $messageType = 'success';

            // Log
            try {
                $pdo->prepare("INSERT INTO security_logs (type, message, ip) VALUES ('integrity', :m, :i)")
                    ->execute([':m' => 'DB backup created: ' . $result['file'], ':i' => $_SERVER['REMOTE_ADDR'] ?? '?']);
            } catch (Throwable $e) {}

            // Telegram
            if ($telegram) {
                try {
                    $msg  = "💾 <b>Database Backup Created</b>\n";
                    $msg .= "📄 File: <code>" . $result['file'] . "</code>\n";
                    $msg .= "📊 Size: " . $result['size_human'] . "\n";
                    $msg .= "👤 By: " . htmlspecialchars($_SESSION['username'] ?? '?') . "\n";
                    $msg .= "🕐 " . date('Y-m-d H:i:s');
                    $telegram->sendMessage($msg);
                } catch (Throwable $e) {}
            }
        } else {
            $message = '❌ خطا در بکاپ: ' . $result['error'];
            $messageType = 'danger';

            if ($telegram) {
                try {
                    $telegram->sendMessage("🚨 <b>Backup FAILED</b>\n\n" . htmlspecialchars($result['error']));
                } catch (Throwable $e) {}
            }
        }
    }

    // ---------- حذف بکاپ ----------
    elseif ($action === 'delete') {
        $file = $_POST['file'] ?? '';
        $result = $bm->deleteBackup($file);
        if ($result['ok']) {
            $message = '✅ فایل حذف شد: ' . htmlspecialchars($file);
            $messageType = 'success';
        } else {
            $message = '❌ ' . $result['error'];
            $messageType = 'danger';
        }
    }

    // ---------- پاکسازی بکاپ‌های قدیمی ----------
    elseif ($action === 'clean') {
        $result = $bm->cleanOldBackups();
        $message = "✅ {$result['deleted']} فایل حذف شد — {$result['freed_human']} آزاد شد";
        $messageType = 'success';
    }
}

// ==================== DOWNLOAD ====================
if (isset($_GET['download'])) {
    $bm->downloadBackup($_GET['download']);
    exit;
}

$stats = $bm->getStats();
$backups = $bm->listBackups();

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت بکاپ — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px}
.stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);border-right:5px solid}
.stat-card .icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;margin-bottom:12px}
.stat-card .value{font-size:28px;font-weight:bold;color:#1e293b}
.stat-card .label{font-size:12px;color:#64748b;margin-top:5px}
.c-teal{border-color:#14b8a6}.c-teal .icon{background:linear-gradient(135deg,#14b8a6,#0d9488)}
.c-blue{border-color:#3b82f6}.c-blue .icon{background:linear-gradient(135deg,#3b82f6,#1e40af)}
.c-purple{border-color:#8b5cf6}.c-purple .icon{background:linear-gradient(135deg,#8b5cf6,#5b21b6)}
.c-orange{border-color:#f59e0b}.c-orange .icon{background:linear-gradient(135deg,#f59e0b,#d97706)}

.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center}
.content-card .header h5{margin:0;font-size:15px;color:#1e293b;font-weight:bold}

.btn-action{padding:10px 18px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-size:13px;margin:4px;transition:.2s;text-decoration:none}
.btn-action:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
.btn-teal{background:linear-gradient(135deg,#14b8a6,#0d9488);color:#fff}
.btn-blue{background:linear-gradient(135deg,#3b82f6,#1e40af);color:#fff}
.btn-red{background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff}
.btn-orange{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}

.backup-row{padding:14px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:12px;font-size:13px;flex-wrap:wrap}
.backup-row:hover{background:#f8fafc}
.backup-row:last-child{border-bottom:none}
.backup-row .file-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#14b8a6,#0d9488);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0}
.backup-row .info{flex:1;min-width:180px}
.backup-row .filename{font-family:monospace;font-size:12px;color:#1e293b;font-weight:bold;word-break:break-all}
.backup-row .meta{font-size:11px;color:#94a3b8;margin-top:3px}
.backup-row .actions{display:flex;gap:6px;flex-wrap:wrap}

.empty-state{padding:40px;text-align:center;color:#94a3b8}
.empty-state .icon{font-size:60px;opacity:.4;display:block;margin-bottom:15px}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>💾 مدیریت بکاپ دیتابیس</h1>
        <p>بکاپ خودکار و دستی از دیتابیس PartoCMS</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card c-teal">
            <div class="icon"><i class="bi bi-archive"></i></div>
            <div class="value"><?= $stats['total_backups'] ?></div>
            <div class="label">تعداد بکاپ</div>
        </div>
        <div class="stat-card c-blue">
            <div class="icon"><i class="bi bi-hdd"></i></div>
            <div class="value" style="font-size:20px"><?= $stats['total_size_human'] ?></div>
            <div class="label">حجم کل</div>
        </div>
        <div class="stat-card c-purple">
            <div class="icon"><i class="bi bi-clock-history"></i></div>
            <div class="value" style="font-size:16px"><?= $stats['last_backup_ago'] ?></div>
            <div class="label">آخرین بکاپ</div>
        </div>
        <div class="stat-card c-orange">
            <div class="icon"><i class="bi bi-calendar-week"></i></div>
            <div class="value"><?= $stats['keep_days'] ?></div>
            <div class="label">روز نگهداری</div>
        </div>
    </div>

    <!-- Actions -->
    <div class="content-card">
        <div class="header"><h5>⚡ عملیات سریع</h5></div>
        <div style="padding:18px">
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="create">
                <button type="submit" class="btn-action btn-teal">
                    <i class="bi bi-plus-circle"></i> ایجاد بکاپ جدید
                </button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('بکاپ‌های قدیمی‌تر از <?= $stats['keep_days'] ?> روز حذف می‌شوند. مطمئنید؟');">
                <input type="hidden" name="action" value="clean">
                <button type="submit" class="btn-action btn-orange">
                    <i class="bi bi-brush"></i> پاکسازی بکاپ‌های قدیمی
                </button>
            </form>
            <a href="security_dashboard.php" class="btn-action btn-blue">
                <i class="bi bi-arrow-right"></i> بازگشت به داشبورد
            </a>
        </div>
    </div>

    <!-- List -->
    <div class="content-card">
        <div class="header">
            <h5>📦 بکاپ‌های موجود (<?= count($backups) ?>)</h5>
        </div>
        <div>
            <?php if (empty($backups)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox icon"></i>
                    <p>هیچ بکاپی وجود ندارد</p>
                    <p style="font-size:12px">روی «ایجاد بکاپ جدید» کلیک کنید</p>
                </div>
            <?php else: ?>
                <?php foreach ($backups as $b): ?>
                <div class="backup-row">
                    <div class="file-icon">
                        <i class="bi bi-file-earmark-zip"></i>
                    </div>
                    <div class="info">
                        <div class="filename"><?= htmlspecialchars($b['file']) ?></div>
                        <div class="meta">
                            📊 <?= htmlspecialchars($b['size_human']) ?> • 
                            🕐 <?= htmlspecialchars($b['created_at']) ?>
                            (<?= $b['age_days'] ?> روز پیش)
                        </div>
                    </div>
                    <div class="actions">
                        <a href="?download=<?= urlencode($b['file']) ?>" class="btn-action btn-blue btn-sm">
                            <i class="bi bi-download"></i> دانلود
                        </a>
                        <form method="post" style="display:inline" onsubmit="return confirm('این فایل حذف شود؟');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="file" value="<?= htmlspecialchars($b['file']) ?>">
                            <button type="submit" class="btn-action btn-red btn-sm">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info -->
    <div class="content-card">
        <div class="header"><h5>ℹ️ راهنما</h5></div>
        <div style="padding:18px;font-size:13px;color:#475569;line-height:1.9">
            <p><b>📌 بکاپ خودکار:</b> یک Cron Job تنظیم می‌کنیم که هر روز ساعت ۳ بامداد یک بکاپ بسازد و بکاپ‌های قدیمی‌تر از <?= $stats['keep_days'] ?> روز را حذف کند.</p>
            <p><b>💾 محل ذخیره:</b> فایل‌ها در پوشه <code>~/PartoCMS/backups/database/</code> ذخیره می‌شوند.</p>
            <p><b>📥 دانلود:</b> برای دانلود، روی دکمه «دانلود» کلیک کنید. فایل با پسوند <code>.sql.gz</code> ذخیره می‌شود.</p>
            <p><b>🔧 بازیابی:</b> برای بازگرداندن یک بکاپ، فایل را دانلود و با دستور زیر import کنید:</p>
            <pre style="background:#1e293b;color:#7dd3fc;padding:12px;border-radius:8px;direction:ltr;text-align:left;font-size:11px;overflow-x:auto">gunzip db_xxx.sql.gz
mysql -u root -p grapesjs_cms &lt; db_xxx.sql</pre>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

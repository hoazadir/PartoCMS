<?php
/**
 * PartoCMS - داشبورد امنیتی
 */
require_once __DIR__ . '/auth_check.php';


// ==================== اتصال دیتابیس ====================
if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}

// ==================== بارگذاری محافظت‌شده کلاس‌ها ====================
foreach (['fim.php', 'input_validator.php', 'telegram_notifier.php', 'pdf_reporter.php'] as $f) {
    $p = __DIR__ . '/includes/' . $f;
    if (file_exists($p)) {
        try { require_once $p; } catch (Throwable $e) { /* نادیده بگیر */ }
    }
}

// ==================== بررسی دسترسی ادمین ====================
// auth_check فقط لاگین را چک می‌کند، اینجا نقش admin را چک می‌کنیم
if (($_SESSION['role'] ?? '') !== 'admin') {
    $loginUrl = defined('SITE_URL') && SITE_URL ? SITE_URL . '/admin/index.php' : 'index.php';
    header('Location: ' . $loginUrl);
    exit;
}

// ==================== بررسی $pdo ====================
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('<div style="font-family:Tahoma;padding:20px;background:#fee2e2;color:#991b1b;border-radius:10px;margin:20px">'
        . '<h2>❌ اتصال دیتابیس (PDO) برقرار نیست</h2>'
        . '<p>فایل <code>config.php</code> باید متغیر <code>$pdo</code> را تعریف کند.</p>'
        . '</div>');
}

// ==================== متغیرهای پایه ====================
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';

// ==================== راه‌اندازی FIM ====================
$fimAvailable = class_exists('FileIntegrityMonitor');
$fim = null;
if ($fimAvailable) {
    try {
        $fim = new FileIntegrityMonitor($pdo, realpath(__DIR__ . '/..'));
    } catch (Throwable $e) {
        $fimAvailable = false;
    }
}

// ==================== راه‌اندازی Telegram ====================
$tgToken  = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
$tgChatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
$telegram = class_exists('TelegramNotifier') ? new TelegramNotifier($tgToken, $tgChatId) : null;

$message = '';
$messageType = '';

// ==================== توابع کمکی ====================
function safeQuery($pdo, $sql, $default = 0) {
    try { return (int) $pdo->query($sql)->fetchColumn(); }
    catch (Throwable $e) { return $default; }
}

function safeQueryValue($pdo, $sql, $default = null) {
    try { return $pdo->query($sql)->fetchColumn() ?: $default; }
    catch (Throwable $e) { return $default; }
}

function getDashboardStats($pdo) {
    return [
        'files_monitored' => safeQuery($pdo, "SELECT COUNT(*) FROM file_hashes"),
        'modified_files'  => safeQuery($pdo, "SELECT COUNT(*) FROM file_hashes WHERE status='modified'"),
        'added_files'     => safeQuery($pdo, "SELECT COUNT(*) FROM file_hashes WHERE status='added'"),
        'deleted_files'   => safeQuery($pdo, "SELECT COUNT(*) FROM file_hashes WHERE status='deleted'"),
        'total_logs'      => safeQuery($pdo, "SELECT COUNT(*) FROM security_logs"),
        'malware_blocks'  => safeQuery($pdo, "SELECT COUNT(*) FROM security_logs WHERE type='malware'"),
        'login_fails'     => safeQuery($pdo, "SELECT COUNT(*) FROM security_logs WHERE type='login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
        'last_scan'       => safeQueryValue($pdo, "SELECT MAX(created_at) FROM security_logs WHERE type='scan'"),
    ];
}

// ==================== پردازش فرم‌ها ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = class_exists('SecurityValidator')
        ? SecurityValidator::verifyCsrfToken($_POST['csrf_token'] ?? '')
        : true;

    if (!$csrfOk) {
        $message = 'خطای امنیتی: CSRF Token نامعتبر';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $stats = getDashboardStats($pdo);

        switch ($action) {

            case 'scan_integrity':
                if (!$fim) { $message = 'FIM در دسترس نیست'; $messageType = 'danger'; break; }
                try {
                    $changes   = $fim->checkIntegrity();
                    $malicious = $fim->scanForMaliciousPatterns();
                    $total     = count($changes) + count($malicious);

                    $message = $total > 0
                        ? "⚠️ $total مورد مشکوک شناسایی شد!"
                        : '✅ اسکن کامل شد — همه فایل‌ها سالم هستند';
                    $messageType = $total > 0 ? 'warning' : 'success';

                    try {
                        $pdo->prepare("INSERT INTO security_logs (type,message,ip) VALUES ('scan',:m,:i)")
                            ->execute([':m' => "اسکن — $total مورد", ':i' => $_SERVER['REMOTE_ADDR']]);
                    } catch (Throwable $e) {}

                    if ($total > 0 && $telegram) {
                        $telegram->sendSecurityAlert(
                            'INTEGRITY_SCAN',
                            'تغییرات مشکوک در فایل‌ها',
                            ['تغییرات' => count($changes), 'مخرب' => count($malicious)],
                            'danger'
                        );
                    }
                } catch (Throwable $e) {
                    $message = 'خطا: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'regenerate_hashes':
                if (!$fim) { $message = 'FIM در دسترس نیست'; $messageType = 'danger'; break; }
                try {
                    $c = $fim->generateHashes();
                    $message = "✅ $c فایل هش شدند";
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $message = 'خطا: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'clear_logs':
                try {
                    $pdo->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $message = '✅ لاگ‌های قدیمی پاک شدند';
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $message = 'خطا: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'fix_file':
                $fp = $_POST['file_path'] ?? '';
                if ($fp) {
                    try {
                        $pdo->prepare("UPDATE file_hashes SET status='clean' WHERE file_path=:p")
                            ->execute([':p' => $fp]);
                        $message = '✅ فایل به عنوان سالم علامت‌گذاری شد';
                        $messageType = 'success';
                    } catch (Throwable $e) {
                        $message = 'خطا: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'test_telegram':
                if (!$telegram) { $message = 'کلاس TelegramNotifier در دسترس نیست'; $messageType = 'danger'; break; }
                $r = $telegram->testConnection();
                $message = !empty($r['ok']) ? '✅ پیام تست ارسال شد' : '❌ خطا: ' . ($r['error'] ?? json_encode($r));
                $messageType = !empty($r['ok']) ? 'success' : 'danger';
                break;

            case 'send_report_telegram':
                if (!$telegram) { $message = 'Telegram در دسترس نیست'; $messageType = 'danger'; break; }
                $sf = [];
                try { $sf = $pdo->query("SELECT * FROM file_hashes WHERE status IN ('modified','added','deleted') LIMIT 20")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
                $r = $telegram->sendFullReport($stats, $sf);
                $message = !empty($r['ok']) ? '✅ گزارش ارسال شد' : '❌ خطا در ارسال';
                $messageType = !empty($r['ok']) ? 'success' : 'danger';
                break;

            case 'download_pdf':
                if (!class_exists('PdfReporter')) { $message = 'کلاس PdfReporter در دسترس نیست'; $messageType = 'danger'; break; }
                try {
                    $sf = $lg = [];
                    try { $sf = $pdo->query("SELECT * FROM file_hashes WHERE status IN ('modified','added','deleted') LIMIT 50")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
                    try { $lg = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

                    $pdf = (new PdfReporter())->generateSecurityReport($stats, $sf, $lg, $siteName);

                    if (ob_get_level()) ob_end_clean();
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="security_' . date('Ymd_His') . '.pdf"');
                    header('Content-Length: ' . strlen($pdf));
                    echo $pdf;
                    exit;
                } catch (Throwable $e) {
                    $message = 'خطا در PDF: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;

            case 'send_pdf_telegram':
                if (!class_exists('PdfReporter') || !$telegram) { $message = 'PDF یا Telegram در دسترس نیست'; $messageType = 'danger'; break; }
                try {
                    $sf = $lg = [];
                    try { $sf = $pdo->query("SELECT * FROM file_hashes WHERE status IN ('modified','added','deleted') LIMIT 50")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
                    try { $lg = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

                    $pdf = (new PdfReporter())->generateSecurityReport($stats, $sf, $lg, $siteName);
                    $tmp = sys_get_temp_dir() . '/pcm_' . time() . '.pdf';
                    file_put_contents($tmp, $pdf);

                    $r = $telegram->sendDocument($tmp, '📄 گزارش امنیتی — ' . date('Y-m-d H:i'));
                    @unlink($tmp);

                    $message = !empty($r['ok']) ? '✅ PDF ارسال شد' : '❌ خطا در ارسال';
                    $messageType = !empty($r['ok']) ? 'success' : 'danger';
                } catch (Throwable $e) {
                    $message = 'خطا: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// ==================== داده‌های نمایش ====================
$stats  = getDashboardStats($pdo);
$filter = $_GET['filter'] ?? 'all';

$logs = [];
try {
    if ($filter === 'all') {
        $logs = $pdo->query("SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $pdo->prepare("SELECT * FROM security_logs WHERE type=:t ORDER BY created_at DESC LIMIT 100");
        $st->execute([':t' => $filter]);
        $logs = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { $logs = []; }

$suspiciousFiles = [];
try {
    $suspiciousFiles = $pdo->query("SELECT * FROM file_hashes WHERE status IN ('modified','added','deleted') ORDER BY last_checked DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $suspiciousFiles = []; }

// CSRF Token
$csrfToken = class_exists('SecurityValidator')
    ? SecurityValidator::generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)));
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = $csrfToken;

$sidebarFile = __DIR__ . '/includes/sidebar.php';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>داشبورد امنیتی — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#1e293b,#334155);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.7;font-size:13px}
.stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);border-right:5px solid}
.stat-card .icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;margin-bottom:10px}
.stat-card .value{font-size:26px;font-weight:bold;color:#1e293b}
.stat-card .label{font-size:12px;color:#64748b;margin-top:3px}
.card-blue{border-color:#3b82f6}.card-blue .icon{background:#3b82f6}
.card-green{border-color:#10b981}.card-green .icon{background:#10b981}
.card-red{border-color:#ef4444}.card-red .icon{background:#ef4444}
.card-orange{border-color:#f59e0b}.card-orange .icon{background:#f59e0b}
.card-purple{border-color:#8b5cf6}.card-purple .icon{background:#8b5cf6}
.card-pink{border-color:#ec4899}.card-pink .icon{background:#ec4899}
.action-btn{padding:10px 18px;border-radius:10px;border:none;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-size:13px;margin:4px}
.action-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.15)}
.btn-scan{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff}
.btn-regen{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.btn-clear{background:linear-gradient(135deg,#64748b,#475569);color:#fff}
.btn-tg{background:linear-gradient(135deg,#0088cc,#006699);color:#fff}
.btn-pdf{background:linear-gradient(135deg,#dc2626,#991b1b);color:#fff}
.btn-pdf-tg{background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff}
.content-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden}
.content-card .header{padding:18px 22px;border-bottom:1px solid #e2e8f0}
.content-card .header h5{margin:0;font-size:16px;color:#1e293b}
.log-item{padding:12px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:12px;font-size:13px}
.log-item:hover{background:#f8fafc}
.badge-type{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:bold;background:#e2e8f0;color:#475569}
.badge-scan{background:#dbeafe;color:#1e40af}
.badge-malware{background:#fee2e2;color:#991b1b}
.badge-login_fail{background:#fef3c7;color:#92400e}
.badge-integrity{background:#fce7f3;color:#9d174d}
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.filter-tab{padding:6px 14px;border-radius:20px;background:#fff;border:1px solid #e2e8f0;font-size:12px;color:#475569;text-decoration:none}
.filter-tab.active{background:#1e293b;color:#fff;border-color:#1e293b}
.suspicious-row{padding:15px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:15px}
.suspicious-row:hover{background:#fef9c3}
.suspicious-row .path{font-family:monospace;font-size:12px;color:#1e293b;word-break:break-all;flex:1}
.status-badge{padding:4px 10px;border-radius:6px;font-size:11px;font-weight:bold}
.status-modified{background:#fef3c7;color:#92400e}
.status-added{background:#fee2e2;color:#991b1b}
.status-deleted{background:#e2e8f0;color:#475569}
</style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="page-header">
        <h1>🛡️ داشبورد امنیتی</h1>
        <p>آخرین اسکن: <?= $stats['last_scan'] ? htmlspecialchars($stats['last_scan']) : 'هرگز' ?></p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!$fimAvailable): ?>
    <div class="alert alert-warning">⚠️ کلاس FileIntegrityMonitor یافت نشد. فایل <code>admin/includes/fim.php</code> را بررسی کنید.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-blue"><div class="icon"><i class="bi bi-file-earmark-code"></i></div><div class="value"><?= number_format($stats['files_monitored']) ?></div><div class="label">فایل تحت نظر</div></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-orange"><div class="icon"><i class="bi bi-pencil-square"></i></div><div class="value"><?= $stats['modified_files'] ?></div><div class="label">دستکاری شده</div></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-red"><div class="icon"><i class="bi bi-file-plus"></i></div><div class="value"><?= $stats['added_files'] ?></div><div class="label">فایل جدید</div></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-purple"><div class="icon"><i class="bi bi-shield-x"></i></div><div class="value"><?= $stats['malware_blocks'] ?></div><div class="label">بدافزار مسدود</div></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-pink"><div class="icon"><i class="bi bi-person-x"></i></div><div class="value"><?= $stats['login_fails'] ?></div><div class="label">ورود ناموفق</div></div></div>
        <div class="col-6 col-md-4 col-lg-2"><div class="stat-card card-green"><div class="icon"><i class="bi bi-list-check"></i></div><div class="value"><?= number_format($stats['total_logs']) ?></div><div class="label">کل رویدادها</div></div></div>
    </div>

    <div class="content-card mb-4">
        <div class="header"><h5>⚡ عملیات سریع</h5></div>
        <div class="p-3">
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="scan_integrity"><button class="action-btn btn-scan"><i class="bi bi-search"></i> اسکن کامل</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="regenerate_hashes"><button class="action-btn btn-regen"><i class="bi bi-arrow-repeat"></i> بازسازی هش‌ها</button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('لاگ‌های قدیمی پاک می‌شوند؟')"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="clear_logs"><button class="action-btn btn-clear"><i class="bi bi-trash"></i> پاکسازی لاگ</button></form>
            <hr class="my-3">
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="test_telegram"><button class="action-btn btn-tg"><i class="bi bi-send"></i> تست تلگرام</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="send_report_telegram"><button class="action-btn btn-tg"><i class="bi bi-telegram"></i> ارسال گزارش</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="download_pdf"><button class="action-btn btn-pdf"><i class="bi bi-file-pdf"></i> دانلود PDF</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="send_pdf_telegram"><button class="action-btn btn-pdf-tg"><i class="bi bi-file-pdf"></i> PDF به تلگرام</button></form>
        </div>
    </div>

    <?php if (!empty($suspiciousFiles)): ?>
    <div class="content-card mb-4">
        <div class="header"><h5>⚠️ فایل‌های مشکوک (<?= count($suspiciousFiles) ?>)</h5></div>
        <?php foreach ($suspiciousFiles as $file): ?>
        <div class="suspicious-row">
            <span class="path"><?= htmlspecialchars($file['file_path']) ?></span>
            <span class="status-badge status-<?= htmlspecialchars($file['status']) ?>">
                <?php $lb=['modified'=>'دستکاری شده','added'=>'فایل جدید','deleted'=>'حذف شده']; echo $lb[$file['status']]??$file['status']; ?>
            </span>
            <form method="post" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="fix_file">
                <input type="hidden" name="file_path" value="<?= htmlspecialchars($file['file_path']) ?>">
                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check"></i></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="content-card">
        <div class="header"><h5>📓 رویدادهای امنیتی</h5></div>
        <div class="filter-tabs">
            <?php foreach (['all'=>'همه','scan'=>'اسکن','malware'=>'بدافزار','login_fail'=>'ورود ناموفق','integrity'=>'یکپارچگی'] as $k=>$v): ?>
            <a href="?filter=<?= $k ?>" class="filter-tab <?= $filter===$k?'active':'' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
        <?php if (empty($logs)): ?>
            <div class="p-4 text-center text-muted">هیچ رویدادی ثبت نشده است</div>
        <?php else: foreach ($logs as $log): ?>
            <div class="log-item">
                <span class="badge-type badge-<?= htmlspecialchars($log['type']) ?>"><?= htmlspecialchars($log['type']) ?></span>
                <div style="flex:1">
                    <div style="color:#1e293b;font-weight:bold"><?= htmlspecialchars(mb_substr($log['message'],0,200)) ?></div>
                    <div style="color:#94a3b8;font-size:11px;margin-top:3px"><?= htmlspecialchars($log['created_at']) ?><?php if (!empty($log['ip'])): ?> • <?= htmlspecialchars($log['ip']) ?><?php endif; ?></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

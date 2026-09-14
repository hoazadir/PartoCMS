<?php
/**
 * PartoCMS - Media Manager (with VirusTotal + CSRF + Logging)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// ==================== LOAD SECURITY MODULES ====================
$vtScannerPath = __DIR__ . '/includes/virustotal_scanner.php';
if (file_exists($vtScannerPath)) require_once $vtScannerPath;

$tgPath = __DIR__ . '/includes/telegram_notifier.php';
if (file_exists($tgPath)) require_once $tgPath;

$rlPath = __DIR__ . '/includes/rate_limiter.php';
if (file_exists($rlPath)) require_once $rlPath;

// ==================== AUTH CHECK ====================
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
if (!$perm->can($_SESSION['user_id'], 'content.view')) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// ==================== SETUP SERVICES ====================
$vtApiKey  = function_exists('getSetting') ? getSetting('virustotal_api_key', '') : '';
$tgToken   = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
$tgChatId  = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';

$vtScanner = (!empty($vtApiKey) && class_exists('VirusTotalScanner'))
    ? new VirusTotalScanner($vtApiKey)
    : null;

$telegram = (!empty($tgToken) && !empty($tgChatId) && class_exists('TelegramNotifier'))
    ? new TelegramNotifier($tgToken, $tgChatId)
    : null;

// ==================== CSRF TOKEN ====================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf() {
    $provided = $_POST['csrf_token'] ?? '';
    return !empty($provided) && hash_equals($_SESSION['csrf_token'] ?? '', $provided);
}

// ==================== UPLOAD HANDLER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    // 1. CSRF check
    if (!verifyCsrf()) {
        $error = 'خطای امنیتی: CSRF Token نامعتبر است. لطفاً صفحه را رفرش کنید.';
    }
    // 2. File received
    elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'خطا در آپلود فایل (کد ' . $_FILES['file']['error'] . ')';
    } else {
        $file = $_FILES['file'];

        // 3. MIME validation
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        // SVG is dangerous (can contain JavaScript) - handle separately
        $isSvg = ($mime === 'image/svg+xml');

        if (!in_array($mime, $allowed) && !$isSvg) {
            $error = 'فقط تصویر مجاز است (JPG, PNG, GIF, WEBP)';
        }
        elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'حجم فایل بیشتر از ۵ مگابایت است';
        }
        else {
            // ==================== VIRUSTOTAL SCAN ====================
            $scanResult = null;
            if ($vtScanner) {
                try {
                    // First try to check by hash (faster, no upload)
                    $scanResult = $vtScanner->checkByHash($file['tmp_name']);

                    // If unknown, upload for full scan
                    if (empty($scanResult['ok']) && ($scanResult['error'] ?? '') === 'unknown') {
                        $scanResult = $vtScanner->scanFile($file['tmp_name']);
                    }
                } catch (Throwable $e) {
                    $scanResult = ['ok' => false, 'safe' => false, 'error' => $e->getMessage()];
                }

                // ==================== BLOCK IF MALICIOUS ====================
                if ($scanResult && empty($scanResult['safe'])) {
                    $threats = [];
                    if (!empty($scanResult['malicious'])) {
                        $threats[] = $scanResult['malicious'] . ' موتور ویروس را تشخیص داد';
                    }
                    if (!empty($scanResult['suspicious'])) {
                        $threats[] = $scanResult['suspicious'] . ' مورد مشکوک';
                    }
                    $reason = !empty($threats) ? implode('، ', $threats) : ($scanResult['error'] ?? 'نامشخص');

                    $error = '🚨 فایل آلوده است! ' . $reason;

                    // Log to database
                    try {
                        $logMsg = 'Malicious upload blocked: ' . $file['name'] . ' | Reason: ' . $reason;
                        $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id, created_at) VALUES ('malware', :m, :ip, :uid, NOW())")
                            ->execute([
                                ':m' => $logMsg,
                                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                ':uid' => $_SESSION['user_id'] ?? null,
                            ]);
                    } catch (Throwable $e) {}

                    // Telegram alert
                    if ($telegram) {
                        try {
                            $msg  = "\xF0\x9F\x9A\xA8 <b>Malicious File Upload Blocked</b>\n";
                            $msg .= "\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\n\n";
                            $msg .= "\xF0\x9F\x93\x81 <b>File:</b> <code>" . htmlspecialchars($file['name']) . "</code>\n";
                            $msg .= "\xF0\x9F\x91\xA4 <b>User:</b> " . htmlspecialchars($_SESSION['username'] ?? '?') . "\n";
                            $msg .= "\xF0\x9F\x8C\x90 <b>IP:</b> <code>" . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '?') . "</code>\n";
                            $msg .= "\xF0\x9F\x94\xA5 <b>Reason:</b> " . htmlspecialchars($reason) . "\n";
                            $msg .= "\xF0\x9F\x95\x90 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
                            $telegram->sendMessage($msg);
                        } catch (Throwable $e) {}
                    }
                }
                // ==================== FILE SAFE ====================
                else {
                    // Log successful scan
                    try {
                        $pdo->prepare("INSERT INTO security_logs (type, message, ip, created_at) VALUES ('scan', :m, :ip, NOW())")
                            ->execute([
                                ':m' => 'Upload scanned OK: ' . $file['name'],
                                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            ]);
                    } catch (Throwable $e) {}
                }
            }

            // ==================== SAVE FILE (only if safe) ====================
            if (empty($error)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // Prevent dangerous extensions
                $dangerousExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pht', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm'];
                if (in_array($ext, $dangerousExt)) {
                    $error = 'پسوند فایل غیرمجاز است';
                } else {
                    $filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/../assets/uploads/';

                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                    $filepath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Extra: forbid executable permissions
                        @chmod($filepath, 0644);

                        // Get image dimensions
                        $width = $height = null;
                        if (!$isSvg) {
                            $size = @getimagesize($filepath);
                            if ($size) {
                                $width = $size[0];
                                $height = $size[1];
                            }
                        }

                        $altText = trim($_POST['alt_text'] ?? '');

                        $stmt = $pdo->prepare("
                            INSERT INTO media (filename, original_name, filepath, mime_type, file_size, width, height, alt_text, uploaded_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $filename,
                            $file['name'],
                            'assets/uploads/' . $filename,
                            $mime,
                            $file['size'],
                            $width,
                            $height,
                            $altText,
                            $_SESSION['user_id']
                        ]);

                        header('Location: media.php?msg=uploaded');
                        exit;
                    } else {
                        $error = 'خطا در ذخیره فایل';
                    }
                }
            }
        }
    }
}

// ==================== DELETE HANDLER ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Verify via session (basic protection)
    $stmt = $pdo->prepare("SELECT filepath FROM media WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $media = $stmt->fetch();

    if ($media) {
        $fullPath = __DIR__ . '/../' . $media['filepath'];
        if (file_exists($fullPath)) @unlink($fullPath);

        $pdo->prepare("DELETE FROM media WHERE id = ?")->execute([$_GET['delete']]);

        try {
            $pdo->prepare("INSERT INTO security_logs (type, message, ip, created_at) VALUES ('integrity', :m, :ip, NOW())")
                ->execute([
                    ':m' => 'Media deleted: ' . $media['filepath'] . ' by ' . ($_SESSION['username'] ?? '?'),
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
        } catch (Throwable $e) {}
    }
    header('Location: media.php?msg=deleted');
    exit;
}

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'uploaded' ? 'فایل با موفقیت آپلود شد' : 'فایل حذف شد';
}

// ==================== LOAD MEDIA LIST ====================
$mediaList = $pdo->query("
    SELECT m.*, u.username as uploader
    FROM media m
    LEFT JOIN users u ON u.id = m.uploaded_by
    ORDER BY m.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت رسانه‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }

        /* ✅ سایدبار قدیمی حذف شد — الان از includes/sidebar.php استفاده می‌شود */
        .main { margin-right: 260px; padding: 25px; }
        @media (max-width: 900px) { .main { margin-right: 0 !important; padding: 70px 15px 15px !important; } }

        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .upload-zone { background: #fff; border: 3px dashed #bdc3c7; border-radius: 12px; padding: 40px; text-align: center; margin-bottom: 25px; cursor: pointer; transition: all 0.2s; }
        .upload-zone:hover { border-color: #3498db; background: #f0f7ff; }
        .upload-zone.dragover { border-color: #27ae60; background: #d4edda; }
        .security-badge { background: #ecfdf5; border: 2px solid #10b981; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-size: 13px; color: #065f46; display: flex; align-items: center; gap: 10px; }
        .security-badge.off { background: #fef3c7; border-color: #f59e0b; color: #92400e; }

        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .media-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative; transition: all 0.2s; }
        .media-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .media-card img { width: 100%; height: 140px; object-fit: cover; display: block; }
        .media-card .info { padding: 10px; font-size: 11px; }
        .media-card .info .name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: bold; }
        .media-card .actions { position: absolute; top: 5px; left: 5px; display: flex; gap: 3px; opacity: 0; transition: opacity 0.2s; }
        .media-card:hover .actions { opacity: 1; }
        .media-card .actions a { background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; color: #e74c3c; text-decoration: none; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">🖼 مدیریت رسانه‌ها</h4>
        <span class="badge bg-info"><?= count($mediaList) ?> فایل</span>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Security Status Badge -->
    <?php if ($vtScanner): ?>
    <div class="security-badge">
        <i class="bi bi-shield-check fs-4"></i>
        <div>
            <b>🦠 محافظت VirusTotal فعال است</b><br>
            <span style="font-size:12px;">هر فایل آپلودی قبل از ذخیره با ۷۰+ آنتی‌ویروس اسکن می‌شود</span>
        </div>
    </div>
    <?php else: ?>
    <div class="security-badge off">
        <i class="bi bi-shield-exclamation fs-4"></i>
        <div>
            <b>⚠️ VirusTotal غیرفعال است</b><br>
            <span style="font-size:12px;">برای محافظت از سایت، API Key را در <a href="virustotal_setup.php">ویروس‌یاب VirusTotal</a> تنظیم کنید</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
            <div style="font-size: 60px;">📤</div>
            <h5>فایل را بکشید و رها کنید یا کلیک کنید</h5>
            <p class="text-muted" style="margin:0;">JPG, PNG, GIF, WEBP — حداکثر ۵ مگابایت</p>
            <?php if ($vtScanner): ?>
            <p style="margin:8px 0 0;font-size:12px;color:#10b981;">
                <i class="bi bi-shield-check"></i> اسکن خودکار با VirusTotal
            </p>
            <?php endif; ?>
        </div>
        <input type="file" name="file" id="fileInput" accept="image/*" style="display:none" onchange="this.form.submit()">
    </form>

    <!-- Gallery -->
    <?php if (empty($mediaList)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-images fs-1 d-block mb-2"></i>
            هنوز فایلی آپلود نشده است
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($mediaList as $m): ?>
                <div class="media-card">
                    <div class="actions">
                        <a href="?delete=<?= $m['id'] ?>" onclick="return confirm('حذف فایل؟')" title="حذف">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>" alt="<?= htmlspecialchars($m['alt_text'] ?: $m['original_name']) ?>">
                    <div class="info">
                        <div class="name" title="<?= htmlspecialchars($m['original_name']) ?>">
                            <?= htmlspecialchars($m['original_name']) ?>
                        </div>
                        <div class="text-muted">
                            <?= number_format($m['file_size'] / 1024, 1) ?> KB
                            <?php if ($m['width']): ?> | <?= $m['width'] ?>×<?= $m['height'] ?><?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:10px;">
                            توسط: <?= htmlspecialchars($m['uploader'] ?? '-') ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2"
                                onclick="copyUrl('<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>')">
                            <i class="bi bi-clipboard"></i> کپی لینک
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('لینک کپی شد:\n' + url);
    });
}

// Drag & Drop
const zone = document.querySelector('.upload-zone');
if (zone) {
    ['dragenter', 'dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('dragover'); }));
    zone.addEventListener('drop', ev => {
        const file = ev.dataTransfer.files[0];
        if (file) {
            const input = document.getElementById('fileInput');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            document.getElementById('uploadForm').submit();
        }
    });
}
</script>
</body>
</html>

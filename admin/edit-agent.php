<?php
// admin/edit-agent.php — ویرایش مدارک مشاور
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

function notify_admin_infected_upload_admin(string $field, string $error, int $agentId = 0): void {
    try {
        $pdo = getDB();
        $tgToken  = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
        $tgChatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
        if (!empty($tgToken) && !empty($tgChatId) && file_exists(__DIR__ . '/includes/telegram_notifier.php')) {
            require_once __DIR__ . '/includes/telegram_notifier.php';
            if (class_exists('TelegramNotifier')) {
                $tg = new TelegramNotifier($tgToken, $tgChatId);
                $tg->sendSecurityAlert('infected_upload_admin', 'فایل آلوده در ویرایش مدارک (ادمین)', [
                    'field'    => $field,
                    'error'    => $error,
                    'agent_id' => $agentId,
                    'admin_id' => $_SESSION['user_id'] ?? 0,
                    'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ], 'danger');
            }
        }
        $stmt = $pdo->prepare("INSERT INTO security_logs (type, message, ip, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute(['infected_upload_admin', "[$field] $error", $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SESSION['user_id'] ?? 0]);
    } catch (Throwable $e) { error_log('notify_admin_infected_upload_admin: ' . $e->getMessage()); }
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');

$agentId = (int)($_GET['id'] ?? 0);
if ($agentId <= 0) {
    header('Location: agents.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM agent_profiles WHERE id = ?");
$stmt->execute([$agentId]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agent) {
    header('Location: agents.php');
    exit;
}

// فقط مشاوران pending یا rejected قابل ویرایش هستند
if (!in_array($agent['status'], ['pending', 'rejected'], true)) {
    header('Location: agents.php?error=locked');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['_csrf'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $fields = ['avatar', 'national_card_front', 'national_card_back', 'agency_license_image', 'manager_photo', 'contract_image', 'social_security_image'];
        $updates = [];
        $params = [];

        foreach ($fields as $field) {
            if (!empty($_FILES[$field]['tmp_name'])) {
                $mimeAllowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES[$field]['tmp_name']);
                if (!isset($mimeAllowed[$mime])) {
                    $error = "فرمت $field مجاز نیست.";
                    continue;
                }
                $ext = $mimeAllowed[$mime];
                $uploadDir = dirname(__DIR__) . '/assets/uploads/agents/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                // ─── اسکن ClamAV (لایه اول) ───
                $clamscan = trim((string)shell_exec('which clamscan 2>/dev/null'));
                if ($clamscan !== '') {
                    $output = [];
                    $returnCode = 0;
                    exec(escapeshellarg($clamscan) . ' --no-summary ' . escapeshellarg($_FILES[$field]['tmp_name']) . ' 2>&1', $output, $returnCode);
                    if ($returnCode === 1) {
                        notify_admin_infected_upload_admin($field, 'ClamAV detected', $agentId);
                        $error = "⚠️ فایل $field آلوده به ویروس است (ClamAV). لطفاً تصویر دیگری انتخاب کنید.";
                        continue;
                    }
                }

                // ─── اسکن VirusTotal (لایه دوم — اختیاری) ───
                $vtApiKey = function_exists('getSetting') ? getSetting('virustotal_api_key', '') : '';
                if (!empty($vtApiKey) && file_exists(dirname(__DIR__) . '/admin/includes/virustotal_scanner.php')) {
                    require_once dirname(__DIR__) . '/admin/includes/virustotal_scanner.php';
                    if (class_exists('VirusTotalScanner')) {
                        try {
                            $vtScanner = new VirusTotalScanner($vtApiKey);
                            $scanResult = $vtScanner->checkByHash($_FILES[$field]['tmp_name']);
                            if (empty($scanResult['ok']) && ($scanResult['error'] ?? '') === 'unknown') {
                                $scanResult = $vtScanner->scanFile($_FILES[$field]['tmp_name']);
                            }
                            if (!empty($scanResult['ok']) && isset($scanResult['safe']) && $scanResult['safe'] === false) {
                                notify_admin_infected_upload_admin($field, 'VirusTotal detected', $agentId);
                                notify_admin_infected_upload_admin($field, 'VirusTotal detected', $agentId);
                                $error = "⚠️ فایل $field آلوده است (VirusTotal). لطفاً تصویر دیگری انتخاب کنید.";
                                continue;
                            }
                        } catch (Throwable $e) {
                            error_log('VirusTotal scan error: ' . $e->getMessage());
                        }
                    }
                }

                // ─── بازپردازش تصویر ───
                $image = @imagecreatefromstring(file_get_contents($_FILES[$field]['tmp_name']));
                if ($image === false) {
                    notify_admin_infected_upload_admin($field, 're-encode failed', $agentId);
                    $error = "⚠️ فایل $field یک تصویر معتبر نیست.";
                    continue;
                }
                $filename = $field . '_' . bin2hex(random_bytes(8)) . '.jpg';
                imagejpeg($image, $uploadDir . $filename, 85);
                imagedestroy($image);
                @unlink($_FILES[$field]['tmp_name']);

                $updates[] = "$field = ?";
                $params[] = '/assets/uploads/agents/' . $filename;
            }
        }

        if (empty($error) && !empty($updates)) {
            try {
                $params[] = $agentId;
                $sql = "UPDATE agent_profiles SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'مدارک با موفقیت به‌روزرسانی شدند.';
                // بارگذاری مجدد
                $stmt = $pdo->prepare("SELECT * FROM agent_profiles WHERE id = ?");
                $stmt->execute([$agentId]);
                $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $error = 'خطا: ' . $e->getMessage();
            }
        } elseif (empty($error)) {
            $error = 'هیچ مدرکی انتخاب نشد.';
        }
    }
}

$csrf = $_SESSION['admin_csrf'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✏️ ویرایش مدارک مشاور</h2>
        <a href="agents.php" class="btn btn-outline-secondary">← بازگشت</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></strong>
            — <?= htmlspecialchars($agent['agency_name']) ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="row g-4">
                    <?php
                    $docs = [
                        'avatar'                => 'تصویر پرسنلی',
                        'national_card_front'   => 'روی کارت ملی',
                        'national_card_back'    => 'پشت کارت ملی',
                        'agency_license_image'  => 'پروانه کسب',
                        'manager_photo'         => 'تصویر مدیر',
                        'contract_image'        => 'قرارداد مشاور',
                        'social_security_image' => 'تامین اجتماعی',
                    ];
                    foreach ($docs as $field => $label):
                    ?>
                        <div class="col-md-6">
                            <label class="form-label"><?= $label ?></label>
                            <?php if (!empty($agent[$field])): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars($agent[$field]) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="<?= $field ?>" class="form-control" accept="image/*">
                            <small class="text-muted"><?= !empty($agent[$field]) ? 'برای تغییر، فایل جدید انتخاب کنید.' : 'خالی است — آپلود کنید.' ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 d-flex justify-content-between">
                    <a href="agents.php" class="btn btn-outline-secondary">انصراف</a>
                    <button type="submit" class="btn btn-primary">💾 ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

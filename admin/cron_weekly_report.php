<?php
/**
 * PartoCMS - Weekly Security Report Cron
 * Usage: php cron_weekly_report.php
 * Cron: 0 9 * * 6 (هر شنبه ساعت ۹ صبح)
 */

$isCli = (php_sapi_name() === 'cli');

// ==================== Load Config ====================
$loaded = false;
foreach ([__DIR__.'/../config.php', __DIR__.'/includes/config.php', __DIR__.'/config.php'] as $cfg) {
    if (file_exists($cfg)) { require_once $cfg; $loaded = true; break; }
}
if (!$loaded) { fwrite(STDERR, "config.php not found\n"); exit(1); }

// ==================== DB ====================
$pdo = null;
if (function_exists('getDB')) {
    try { $pdo = getDB(); } catch (Throwable $e) { $pdo = null; }
}
if (!$pdo instanceof PDO) { fwrite(STDERR, "PDO failed\n"); exit(1); }

// ==================== Load Classes ====================
require_once __DIR__ . '/includes/weekly_reporter.php';
$tgPath = __DIR__ . '/includes/telegram_notifier.php';
if (file_exists($tgPath)) require_once $tgPath;

// ==================== Web Security ====================
if (!$isCli) {
    $secret = 'PMC_WEEKLY_' . md5(__FILE__);
    if (($_GET['key'] ?? '') !== $secret) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// ==================== Log Helper ====================
function wrLog($msg) {
    global $isCli;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $isCli ? ($line . PHP_EOL) : ($line . "\n");

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/weekly_report.log', $line . PHP_EOL, FILE_APPEND);
}

wrLog('=======================================================');
wrLog('Starting weekly security report');
wrLog('=======================================================');

// ==================== Init ====================
$siteName = function_exists('getSetting') ? getSetting('site_name', 'PartoCMS') : 'PartoCMS';
$siteUrl = defined('SITE_URL') ? SITE_URL : '';
$adminEmail = function_exists('getSetting') ? getSetting('admin_email', '') : '';

if (empty($adminEmail)) {
    wrLog('WARNING: admin_email not set in settings');
    wrLog('Tip: set admin_email via settings page');
    exit(0);
}

wrLog('Admin email: ' . $adminEmail);

// ==================== Generate & Send ====================
try {
    $reporter = new WeeklyReporter($pdo, $siteName, $siteUrl);
    $result = $reporter->sendReport($adminEmail);

    if (!empty($result['ok'])) {
        wrLog('SUCCESS: Report sent to ' . $adminEmail);
        wrLog('Subject: ' . $result['subject']);
        wrLog('Total events: ' . $result['stats']['total_events']);
        wrLog('Malware blocked: ' . $result['stats']['malware_blocked']);
        wrLog('Login attempts: ' . $result['stats']['login_attempts']);

        // Log to DB
        try {
            $pdo->prepare("INSERT INTO security_logs (type, message, ip, created_at) VALUES ('integrity', :m, 'cron', NOW())")
                ->execute([':m' => 'Weekly report sent to ' . $adminEmail]);
        } catch (Throwable $e) {}

        // Send Telegram notification
        if (class_exists('TelegramNotifier')) {
            try {
                $token = getSetting('telegram_bot_token', '');
                $chatId = getSetting('telegram_chat_id', '');
                if ($token && $chatId) {
                    $tg = new TelegramNotifier($token, $chatId);
                    $msg  = "📧 <b>Weekly Security Report</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━\n\n";
                    $msg .= "📤 Sent to: " . htmlspecialchars($adminEmail) . "\n";
                    $msg .= "📅 Period: " . $result['stats']['period_start'] . " → " . $result['stats']['period_end'] . "\n\n";
                    $msg .= "📊 <b>Summary:</b>\n";
                    $msg .= "• Events: " . $result['stats']['total_events'] . "\n";
                    $msg .= "• Malware: " . $result['stats']['malware_blocked'] . "\n";
                    $msg .= "• Login fails: " . $result['stats']['login_failed'] . "\n";
                    $msg .= "• Files monitored: " . $result['stats']['files_monitored'] . "\n";
                    $tg->sendMessage($msg);
                    wrLog('Telegram notification sent');
                }
            } catch (Throwable $e) {
                wrLog('Telegram error: ' . $e->getMessage());
            }
        }
    } else {
        wrLog('FAILED: mail() returned false');
        wrLog('Check if PHP mail() is configured on your server');
        wrLog('Tip: On Termux, mail() may not work without sendmail');
    }
} catch (Throwable $e) {
    wrLog('ERROR: ' . $e->getMessage());
}

wrLog('=======================================================');
wrLog('Weekly report finished');
wrLog('=======================================================');

if ($isCli) exit(0);

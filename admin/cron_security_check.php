<?php
/**
 * ============================================================================
 * PartoCMS - Automated Security Cron Job
 * ============================================================================
 * Full-featured security scanner that runs periodically to detect:
 *   - Modified, added, or deleted project files (File Integrity Monitoring)
 *   - Malicious code patterns (backdoors, RCE, SQL injection, XSS, etc.)
 *   - Suspicious behavior
 *
 * Sends alerts via:
 *   - Telegram Bot API
 *   - Email (if mail() is available)
 *
 * Storage:
 *   - Database (security_logs table)
 *   - Log file (logs/cron.log)
 *
 * Usage:
 *   CLI:   php cron_security_check.php
 *   Cron:  Every 6 hours via crontab
 *   URL:   https://site.com/admin/cron_security_check.php?key=SECRET_KEY
 * ============================================================================
 */

// ==================== RUNTIME DETECTION ====================
$isCli = (php_sapi_name() === 'cli');

// ==================== INITIALIZE ALL VARIABLES ====================
$changes         = [];
$malicious       = [];
$totalChanges    = 0;
$totalMalicious  = 0;
$total           = 0;
$telegramSent    = false;
$emailSent       = false;
$startTime       = microtime(true);
$pdo             = null;
$fim             = null;
$configLoaded    = false;
$fimLoaded       = false;
$tgLoaded        = false;
$projectRoot     = realpath(__DIR__ . '/..');

// ==================== ERROR HANDLING ====================
// Force display of errors to stderr in CLI, suppress in Web
if ($isCli) {
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ==================== LOAD CONFIGURATION ====================
$configPaths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/includes/config.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../core/config.php',
    __DIR__ . '/../app/config.php',
];

foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        try {
            require_once $configPath;
            $configLoaded = true;
            break;
        } catch (Throwable $e) {
            // Continue to next path
        }
    }
}

if (!$configLoaded) {
    $msg = 'FATAL: config.php not found in any expected location';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    die($msg);
}

// ==================== ESTABLISH DB CONNECTION ====================
if (function_exists('getDB')) {
    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        $pdo = null;
    }
}

// Fallback: look for global $pdo
if (!$pdo instanceof PDO && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
}

if (!$pdo instanceof PDO) {
    $msg = 'FATAL: Database connection (PDO) could not be established';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    die($msg);
}

// ==================== LOAD SECURITY CLASSES ====================
$fimPath = __DIR__ . '/includes/fim.php';
if (file_exists($fimPath)) {
    try {
        require_once $fimPath;
        $fimLoaded = class_exists('FileIntegrityMonitor');
    } catch (Throwable $e) {
        $fimLoaded = false;
    }
}

$tgPath = __DIR__ . '/includes/telegram_notifier.php';
if (file_exists($tgPath)) {
    try {
        require_once $tgPath;
        $tgLoaded = class_exists('TelegramNotifier');
    } catch (Throwable $e) {
        $tgLoaded = false;
    }
}

// ==================== WEB SECURITY ====================
if (!$isCli) {
    // Optional secret key check for web access
    $secretKey = 'PMC_CRON_' . md5(__FILE__ . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $providedKey = $_GET['key'] ?? '';

    if (!hash_equals($secretKey, $providedKey)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        die("403 Forbidden\n\nTo run via URL, use: ?key=" . $secretKey);
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
}

// ==================== LOG HELPER ====================
function cronLog($message, $level = 'INFO') {
    global $isCli, $projectRoot;

    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] [' . $level . '] ' . $message;

    // Output to stdout (CLI) or browser (Web)
    if ($isCli) {
        echo $line . PHP_EOL;
    } else {
        echo $line . "\n";
        if (ob_get_level() > 0) {
            @ob_flush();
            @flush();
        }
    }

    // Also append to log file
    $logDir = $projectRoot . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/cron.log';

    // Rotate log if larger than 5MB
    if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
        @rename($logFile, $logFile . '.' . date('Ymd-His') . '.old');
    }

    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ==================== START ====================
cronLog('=========================================================');
cronLog('Starting security scan');
cronLog('Runtime: ' . ($isCli ? 'CLI' : 'Web'));
cronLog('Project root: ' . $projectRoot);
cronLog('PHP version: ' . PHP_VERSION);
cronLog('---------------------------------------------------------');

// ==================== CHECK FIM AVAILABILITY ====================
if (!$fimLoaded) {
    cronLog('FATAL: FileIntegrityMonitor class not available', 'ERROR');
    cronLog('Hint: check that admin/includes/fim.php exists and has no syntax errors', 'ERROR');
    cronLog('Scan aborted');
    if ($isCli) exit(1);
    exit();
}

try {
    $fim = new FileIntegrityMonitor($pdo, $projectRoot);
    cronLog('FileIntegrityMonitor initialized');
} catch (Throwable $e) {
    cronLog('FATAL: FIM initialization failed: ' . $e->getMessage(), 'ERROR');
    if ($isCli) exit(1);
    exit();
}

// ==================== STEP 1: INTEGRITY CHECK ====================
cronLog('---------------------------------------------------------');
cronLog('STEP 1/3: File integrity check');
cronLog('---------------------------------------------------------');

try {
    $changes = $fim->checkIntegrity();
    if (!is_array($changes)) {
        $changes = [];
    }
    $totalChanges = count($changes);
    cronLog('Changed files detected: ' . $totalChanges, $totalChanges > 0 ? 'WARN' : 'INFO');

    if ($totalChanges > 0) {
        foreach ($changes as $index => $change) {
            if ($index >= 20) {
                cronLog('  ... and ' . ($totalChanges - 20) . ' more', 'WARN');
                break;
            }
            $type = isset($change['type']) ? $change['type'] : 'UNKNOWN';
            $file = isset($change['file']) ? $change['file'] : 'UNKNOWN';
            cronLog('  [' . $type . '] ' . $file, 'WARN');
        }
    }
} catch (Throwable $e) {
    cronLog('ERROR during integrity check: ' . $e->getMessage(), 'ERROR');
    $changes = [];
    $totalChanges = 0;
}

// ==================== STEP 2: MALICIOUS PATTERN SCAN ====================
cronLog('---------------------------------------------------------');
cronLog('STEP 2/3: Malicious pattern scan');
cronLog('---------------------------------------------------------');

try {
    $malicious = $fim->scanForMaliciousPatterns();
    if (!is_array($malicious)) {
        $malicious = [];
    }
    $totalMalicious = count($malicious);
    cronLog('Malicious patterns found: ' . $totalMalicious, $totalMalicious > 0 ? 'ERROR' : 'INFO');

    if ($totalMalicious > 0) {
        foreach ($malicious as $index => $item) {
            if ($index >= 20) {
                cronLog('  ... and ' . ($totalMalicious - 20) . ' more', 'WARN');
                break;
            }
            $file    = isset($item['file'])    ? $item['file']    : 'UNKNOWN';
            $line    = isset($item['line'])    ? $item['line']    : '?';
            $pattern = isset($item['pattern']) ? $item['pattern'] : 'UNKNOWN';
            cronLog('  ' . $file . ':' . $line . ' => ' . $pattern, 'ERROR');
        }
    }
} catch (Throwable $e) {
    cronLog('ERROR during malicious pattern scan: ' . $e->getMessage(), 'ERROR');
    $malicious = [];
    $totalMalicious = 0;
}

// ==================== COMBINED TOTAL ====================
$total = $totalChanges + $totalMalicious;

cronLog('---------------------------------------------------------');
cronLog('SUMMARY: ' . $total . ' issue(s) detected (' . $totalChanges . ' changes, ' . $totalMalicious . ' malicious)');
cronLog('---------------------------------------------------------');

// ==================== STEP 3: SAVE LOG TO DATABASE ====================
cronLog('STEP 3/3: Save log to database');

try {
    $logMessage = 'Cron scan | Changes: ' . $totalChanges . ' | Malicious: ' . $totalMalicious;
    $stmt = $pdo->prepare("INSERT INTO security_logs (type, message, ip, created_at) VALUES ('scan', :msg, :ip, NOW())");
    $stmt->execute([
        ':msg' => $logMessage,
        ':ip'  => $isCli ? 'CLI' : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
    ]);
    cronLog('Log saved to database (ID: ' . $pdo->lastInsertId() . ')');
} catch (Throwable $e) {
    cronLog('ERROR saving to database: ' . $e->getMessage(), 'ERROR');
}

// ==================== STEP 4: SEND ALERTS IF NEEDED ====================
if ($total > 0) {
    cronLog('---------------------------------------------------------');
    cronLog('Sending alerts (' . $total . ' issue(s))...');
    cronLog('---------------------------------------------------------');

    // ---------- 4.1 Telegram Alert ----------
    if ($tgLoaded) {
        try {
            $token  = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
            $chatId = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';

            if (!empty($token) && !empty($chatId)) {
                $telegram = new TelegramNotifier($token, $chatId);

                // Build rich alert message
                $alert  = "\xF0\x9F\x9A\xA8 <b>PartoCMS Security Alert</b>\n";
                $alert .= "\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\n\n";
                $alert .= "\xF0\x9F\x95\x90 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
                $alert .= "\xF0\x9F\x8C\x90 <b>Site:</b> " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . "\n";
                $alert .= "\xF0\x9F\x93\x8A <b>Total issues:</b> " . $total . "\n\n";

                // Changed files section
                if ($totalChanges > 0) {
                    $alert .= "\xF0\x9F\x93\x81 <b>Changed files (" . $totalChanges . "):</b>\n";
                    $shown = 0;
                    foreach ($changes as $change) {
                        if ($shown >= 10) {
                            $alert .= "\xE2\x80\xA2 and " . ($totalChanges - 10) . " more...\n";
                            break;
                        }
                        $type = isset($change['type']) ? $change['type'] : '?';
                        $file = isset($change['file']) ? $change['file'] : '?';
                        $alert .= "\xE2\x80\xA2 [" . $type . "] <code>" . htmlspecialchars($file) . "</code>\n";
                        $shown++;
                    }
                    $alert .= "\n";
                }

                // Malicious patterns section
                if ($totalMalicious > 0) {
                    $alert .= "\xF0\x9F\xA6\xA0 <b>Malicious patterns (" . $totalMalicious . "):</b>\n";
                    $shown = 0;
                    foreach ($malicious as $item) {
                        if ($shown >= 10) {
                            $alert .= "\xE2\x80\xA2 and " . ($totalMalicious - 10) . " more...\n";
                            break;
                        }
                        $file = isset($item['file']) ? $item['file'] : '?';
                        $line = isset($item['line']) ? $item['line'] : '?';
                        $pat  = isset($item['pattern']) ? $item['pattern'] : '?';
                        $alert .= "\xE2\x80\xA2 <code>" . htmlspecialchars($file) . ":" . $line . "</code>\n";
                        $alert .= "  \xE2\x86\xB3 " . htmlspecialchars($pat) . "\n";
                        $shown++;
                    }
                }

                // Footer
                $alert .= "\n\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\n";
                $siteUrl = defined('SITE_URL') ? SITE_URL : '';
                if (!empty($siteUrl)) {
                    $alert .= "\xF0\x9F\x94\x97 <a href=\"" . $siteUrl . "/admin/security_dashboard.php\">Open Security Dashboard</a>";
                }

                $result = $telegram->sendMessage($alert);

                if (!empty($result['ok'])) {
                    cronLog('Telegram alert sent successfully');
                    $telegramSent = true;
                } else {
                    $err = $result['error'] ?? ($result['description'] ?? json_encode($result));
                    cronLog('Telegram alert FAILED: ' . $err, 'ERROR');
                }
            } else {
                cronLog('Telegram not configured (token or chat_id missing)', 'WARN');
            }
        } catch (Throwable $e) {
            cronLog('Telegram send exception: ' . $e->getMessage(), 'ERROR');
        }
    } else {
        cronLog('TelegramNotifier class not loaded', 'WARN');
    }

    // ---------- 4.2 Email Alert ----------
    if (function_exists('getSetting') && function_exists('mail')) {
        try {
            $adminEmail = getSetting('admin_email', '');
            if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $subject = '[PartoCMS] Security Alert - ' . $total . ' issue(s) detected';

                $body  = "PartoCMS Security Alert\n";
                $body .= str_repeat('=', 50) . "\n\n";
                $body .= "Time: " . date('Y-m-d H:i:s') . "\n";
                $body .= "Site: " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . "\n";
                $body .= "Total issues: " . $total . "\n\n";

                if ($totalChanges > 0) {
                    $body .= "Changed files (" . $totalChanges . "):\n";
                    foreach (array_slice($changes, 0, 30) as $change) {
                        $type = isset($change['type']) ? $change['type'] : '?';
                        $file = isset($change['file']) ? $change['file'] : '?';
                        $body .= "  [" . $type . "] " . $file . "\n";
                    }
                    $body .= "\n";
                }

                if ($totalMalicious > 0) {
                    $body .= "Malicious patterns (" . $totalMalicious . "):\n";
                    foreach (array_slice($malicious, 0, 30) as $item) {
                        $file = isset($item['file']) ? $item['file'] : '?';
                        $line = isset($item['line']) ? $item['line'] : '?';
                        $pat  = isset($item['pattern']) ? $item['pattern'] : '?';
                        $body .= "  " . $file . ":" . $line . " => " . $pat . "\n";
                    }
                }

                $headers  = "From: PartoCMS Security <noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (@mail($adminEmail, $subject, $body, $headers)) {
                    cronLog('Email alert sent to: ' . $adminEmail);
                    $emailSent = true;
                } else {
                    cronLog('Email send failed (mail() returned false)', 'WARN');
                }
            }
        } catch (Throwable $e) {
            cronLog('Email send exception: ' . $e->getMessage(), 'ERROR');
        }
    }
} else {
    cronLog('All clean - no alerts required');
}

// ==================== FINALIZE ====================
$duration = round(microtime(true) - $startTime, 3);

cronLog('---------------------------------------------------------');
cronLog('Scan completed in ' . $duration . 's');
cronLog('Changes: ' . $totalChanges . ' | Malicious: ' . $totalMalicious);
cronLog('Alerts: Telegram=' . ($telegramSent ? 'yes' : 'no') . ' | Email=' . ($emailSent ? 'yes' : 'no'));
cronLog('=========================================================');

if ($isCli) {
    exit(0);
}

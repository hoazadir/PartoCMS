<?php
/**
 * PartoCMS - Translation Queue Worker
 * پردازش صف ترجمه + اطلاع Telegram
 *
 * Usage:
 *   php cron_translation_queue.php          ← ۱ job
 *   php cron_translation_queue.php 5        ← ۵ job
 *   php cron_translation_queue.php 5 60     ← ۵ job، حداکثر ۶۰ ثانیه
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/queue_manager.php';
require_once __DIR__ . '/includes/multi_translator.php';
require_once __DIR__ . '/includes/auto_translator.php';

$maxJobs    = (int)($argv[1] ?? 1);
$timeLimit  = (int)($argv[2] ?? 90);
$startTime  = time();

$logFile = __DIR__ . '/../logs/translation_queue.log';
@mkdir(dirname($logFile), 0755, true);

function qlog($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

qlog("═══════ Queue Worker Started (PID " . getmypid() . ", max: $maxJobs, limit: {$timeLimit}s) ═══════");

try {
    $pdo = getDB();
    $queue = new QueueManager($pdo);

    // ============================================================
    //   Telegram init
    // ============================================================
    $telegram = null;
    try {
        $token  = trim((string)$pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_bot_token'")->fetchColumn());
        $chatId = trim((string)$pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'telegram_chat_id'")->fetchColumn());

        if ($token && $chatId) {
            $tgPath = __DIR__ . '/includes/telegram_notifier.php';
            if (file_exists($tgPath)) {
                require_once $tgPath;
                if (class_exists('TelegramNotifier')) {
                    $telegram = new TelegramNotifier($token, $chatId);
                }
            }
        }
    } catch (Throwable $e) {
        $telegram = null;
    }

    // ریست گیرکرده‌ها
    $reset = $queue->resetStuck();
    if ($reset > 0) {
        qlog("♻️ Reset $reset stuck job(s)");
    }

    $processed = 0;
    $succeeded = 0;
    $failed = 0;
    $processedContentIds = []; // برای چک نهایی

    while ($processed < $maxJobs && (time() - $startTime) < $timeLimit) {
        $job = $queue->getNextJob();
        if (!$job) {
            qlog("💤 No pending jobs");
            break;
        }

        $processed++;
        $processedContentIds[(int)$job['content_id']] = true;

        qlog("▶️ Job #{$job['id']}: content={$job['content_id']} → {$job['language_code']} (attempt {$job['attempts']})");

        try {
            $mt = new MultiTranslator($pdo);
            $result = $mt->translateContent(
                (int)$job['content_id'],
                $job['language_code'],
                $job['user_id'] ? (int)$job['user_id'] : null
            );

            if (!empty($result['ok'])) {
                $queue->markDone((int)$job['id']);
                $succeeded++;
                $avg = $result['avg_score'] ?? 0;
                qlog("✅ Job #{$job['id']} done (avg: $avg)");
            } else {
                $err = $result['error'] ?? 'unknown';
                $queue->markFailed((int)$job['id'], $err);
                $failed++;
                qlog("❌ Job #{$job['id']} failed: $err");
            }
        } catch (Throwable $e) {
            $queue->markFailed((int)$job['id'], $e->getMessage());
            $failed++;
            qlog("❌ Job #{$job['id']} exception: " . $e->getMessage());
        }

        usleep(200000);
    }

    $elapsed = time() - $startTime;
    qlog("✅ Worker done — processed: $processed, ok: $succeeded, fail: $failed, time: {$elapsed}s");

    // ============================================================
    //   Telegram Notification — فقط اگه همه jobهای محتوا تموم شدن
    // ============================================================
    if ($telegram && !empty($processedContentIds)) {
        foreach (array_keys($processedContentIds) as $cid) {
            try {
                // چک pending/processing
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM translation_queue
                    WHERE content_id = ?
                      AND status IN ('pending', 'processing')
                ");
                $stmt->execute([$cid]);
                $stillPending = (int)$stmt->fetchColumn();

                if ($stillPending > 0) {
                    qlog("📢 Content #$cid — هنوز $stillPending job در صف");
                    continue;
                }

                // همه تموم شد
                $stmt = $pdo->prepare("SELECT title FROM content_items WHERE id = ? LIMIT 1");
                $stmt->execute([$cid]);
                $title = $stmt->fetchColumn() ?: "#$cid";

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_translations WHERE content_id = ?");
                $stmt->execute([$cid]);
                $transCount = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_cnt,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as fail_cnt
                    FROM translation_queue WHERE content_id = ?
                ");
                $stmt->execute([$cid]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);

                $icon = ((int)$counts['fail_cnt'] > 0) ? '⚠️' : '✅';

                $msg = "{$icon} <b>ترجمه خودکار کامل شد</b>\n";
                $msg .= "━━━━━━━━━━━━━━━━\n";
                $msg .= "📝 مقاله: <b>" . htmlspecialchars(mb_substr($title, 0, 60)) . "</b>\n";
                $msg .= "🆔 ID: <code>{$cid}</code>\n";
                $msg .= "🌍 ترجمه‌ها: <b>{$transCount}</b> زبان\n";
                $msg .= "✅ موفق: {$counts['done_cnt']}\n";
                if ((int)$counts['fail_cnt'] > 0) {
                    $msg .= "❌ خطا: {$counts['fail_cnt']}\n";
                }
                $msg .= "⏱ زمان این اجرا: {$elapsed}s\n";
                $msg .= "🕐 " . date('Y-m-d H:i:s');

                $telegram->sendMessage($msg);
                qlog("📢 Telegram notification sent for content #$cid");

            } catch (Throwable $e) {
                qlog("⚠️ Telegram error for #$cid: " . $e->getMessage());
            }
        }
    }

} catch (Throwable $e) {
    qlog("❌ Fatal: " . $e->getMessage());
    exit(1);
}

exit(0);

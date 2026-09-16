<?php
/**
 * PartoCMS - Translation Queue Worker
 * پردازش صف ترجمه
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

    $reset = $queue->resetStuck();
    if ($reset > 0) {
        qlog("♻️ Reset $reset stuck job(s)");
    }

    $processed = 0;
    $succeeded = 0;
    $failed = 0;

    while ($processed < $maxJobs && (time() - $startTime) < $timeLimit) {
        $job = $queue->getNextJob();
        if (!$job) {
            qlog("💤 No pending jobs");
            break;
        }

        $processed++;
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

} catch (Throwable $e) {
    qlog("❌ Fatal: " . $e->getMessage());
    exit(1);
}

exit(0);

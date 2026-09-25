<?php
/**
 * PartoCMS - Whisper Queue Cron Runner
 * 
 * مسئولیت: بررسی صف و اجرای Worker
 * 
 * این فایل هر دقیقه توسط Cron اجرا می‌شود.
 * 
 * @version 2.0
 * @date 2026-09-21
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);

$queueDir = sys_get_temp_dir() . '/whisper_queue';
$lockFile = sys_get_temp_dir() . '/whisper_worker.lock';

// ═══════════════════════════════════════════════════════════
//  ۱. بررسی Worker در حال اجرا (با PID Lock)
// ═══════════════════════════════════════════════════════════
if (file_exists($lockFile)) {
    $pid = (int) trim(file_get_contents($lockFile));
    if ($pid > 0 && function_exists('posix_kill') && @posix_kill($pid, 0)) {
        // Worker در حال اجراست
        exit(0);
    }
    @unlink($lockFile); // PID قدیمی
}

// ═══════════════════════════════════════════════════════════
//  ۲. بررسی پوشه صف
// ═══════════════════════════════════════════════════════════
if (!is_dir($queueDir)) {
    exit(0);
}

$files = glob($queueDir . '/*.json');
if (empty($files)) {
    exit(0);
}

// ═══════════════════════════════════════════════════════════
//  ۳. پردازش اولین فایل صف
// ═══════════════════════════════════════════════════════════
foreach ($files as $queueFile) {
    $data = json_decode(file_get_contents($queueFile), true);

    if (!$data || empty($data['model_name'])) {
        @unlink($queueFile);
        continue;
    }

    $statusFile   = $data['status_file'] ?? '';
    $workerScript = $data['worker_script'] ?? '';
    $sourcesFile  = $data['sources_file'] ?? '';
    $target       = $data['target'] ?? '';
    $logFileW     = sys_get_temp_dir() . '/whisper_worker_' . md5($data['model_name']) . '.log';

    // ─── چک وضعیت قبلی ───
    if ($statusFile && file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true);
        if ($status && in_array($status['status'] ?? '', ['success', 'error'], true)) {
            @unlink($queueFile);
            continue;
        }
    }

    // ─── چک age (بیش از ۱ ساعت) ───
    $queued = strtotime($data['queued_at'] ?? 'now');
    if (time() - $queued > 3600) {
        @unlink($queueFile);
        if ($statusFile) {
            @file_put_contents($statusFile, json_encode([
                'status' => 'error',
                'error'  => 'منقضی شده (بیش از ۱ ساعت)',
            ]));
        }
        continue;
    }

    // ─── چک فایل‌های ضروری ───
    if (!file_exists($workerScript) || !file_exists($sourcesFile)) {
        @unlink($queueFile);
        continue;
    }

    // ═══ اجرای Worker در پس‌زمینه ═══
    $phpBin = PHP_BINARY ?: 'php';

    $cmd = escapeshellarg($phpBin) . ' ' .
           escapeshellarg($workerScript) . ' ' .
           escapeshellarg($sourcesFile) . ' ' .
           escapeshellarg($target) . ' ' .
           escapeshellarg($statusFile) .
           ' >> ' . escapeshellarg($logFileW) . ' 2>&1';

    // اجرا در پس‌زمینه (بدون انتظار)
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $logFileW, 'a'],
        2 => ['file', $logFileW, 'a'],
    ];

    $process = @proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

    if (is_resource($process)) {
        if (isset($pipes[0])) {
            fclose($pipes[0]);
        }
        // رها کردن process — Cron فوری برمی‌گردد
    } else {
        // fallback: exec با &
        @exec($cmd . ' > /dev/null 2>&1 &');
    }

    // فقط یک Worker در هر دور
    break;
}

<?php
/**
 * PartoCMS - Whisper Download Worker
 * 
 * مسئولیت: دانلود مدل Whisper با Resume و Retry
 * 
 * Usage: php whisper_worker.php <sources_file> <target> <status_file>
 * 
 * @version 2.0
 * @date 2026-09-21
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);

// ═══════════════════════════════════════════════════════════
//  آرگومان‌ها
// ═══════════════════════════════════════════════════════════
$sourcesFile = $argv[1] ?? '';
$target      = $argv[2] ?? '';
$statusFile  = $argv[3] ?? '';

if (empty($sourcesFile) || empty($target) || empty($statusFile)) {
    exit(1);
}

// ═══════════════════════════════════════════════════════════
//  PID Lock — جلوگیری از اجرای همزمان
// ═══════════════════════════════════════════════════════════
$lockFile = sys_get_temp_dir() . '/whisper_worker.lock';

if (file_exists($lockFile)) {
    $oldPid = (int) trim(file_get_contents($lockFile));
    if ($oldPid > 0 && function_exists('posix_kill') && @posix_kill($oldPid, 0)) {
        // Worker دیگری در حال اجراست
        exit(0);
    }
    @unlink($lockFile);
}

file_put_contents($lockFile, getmypid());
register_shutdown_function(function () use ($lockFile) {
    @unlink($lockFile);
});

// ═══════════════════════════════════════════════════════════
//  Atomic Status Writer
// ═══════════════════════════════════════════════════════════
function writeStatus(string $statusFile, array $data): void {
    $tmp = $statusFile . '.tmp';
    $data['updated_at'] = date('H:i:s');
    @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE));
    @rename($tmp, $statusFile);
}

// ═══════════════════════════════════════════════════════════
//  بررسی فایل منابع
// ═══════════════════════════════════════════════════════════
if (!file_exists($sourcesFile)) {
    writeStatus($statusFile, [
        'status' => 'error',
        'error'  => 'فایل منابع یافت نشد',
    ]);
    exit(1);
}

$sources = json_decode(file_get_contents($sourcesFile), true);
if (empty($sources) || !is_array($sources)) {
    writeStatus($statusFile, [
        'status' => 'error',
        'error'  => 'لیست منابع نامعتبر',
    ]);
    exit(1);
}

// ═══════════════════════════════════════════════════════════
//  پیدا کردن اولین منبع در دسترس
// ═══════════════════════════════════════════════════════════
$workingSource = null;
$totalSize = 0;

foreach ($sources as $source) {
    writeStatus($statusFile, [
        'status'     => 'checking',
        'source'     => $source['name'] ?? '?',
        'downloaded' => 0,
        'total'      => 0,
        'percent'    => 0,
    ]);

    $ch = curl_init($source['url']);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    if (in_array($code, [200, 301, 302], true)) {
        $workingSource = $source;
        $totalSize = $size;
        break;
    }
}

if (!$workingSource) {
    writeStatus($statusFile, [
        'status' => 'error',
        'error'  => 'هیچ منبع دانلودی در دسترس نیست',
    ]);
    exit(1);
}

// ═══════════════════════════════════════════════════════════
//  دانلود با Resume + Retry
// ═══════════════════════════════════════════════════════════
$maxRetries = 20;
$retry = 0;
$success = false;

while (!$success && $retry < $maxRetries) {
    $retry++;

    $existingSize = file_exists($target) ? filesize($target) : 0;

    // اگر کامل است
    if ($totalSize > 0 && $existingSize >= $totalSize) {
        $success = true;
        break;
    }

    // باز کردن فایل
    $mode = ($existingSize > 0) ? 'a' : 'w';
    $fp = @fopen($target, $mode);
    if (!$fp) {
        writeStatus($statusFile, [
            'status' => 'error',
            'error'  => 'خطا در باز کردن فایل مقصد',
        ]);
        exit(1);
    }

    $ch = curl_init($workingSource['url']);

    if ($existingSize > 0) {
        curl_setopt($ch, CURLOPT_RANGE, $existingSize . '-');
    }

    curl_setopt_array($ch, [
        CURLOPT_FILE            => $fp,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_TIMEOUT         => 300,
        CURLOPT_CONNECTTIMEOUT  => 15,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_LOW_SPEED_LIMIT => 50,
        CURLOPT_LOW_SPEED_TIME  => 30,
        CURLOPT_PROGRESSFUNCTION => function ($ch, $dlSize, $downloaded, $ulSize, $uploaded) use ($statusFile, $totalSize, $existingSize, $workingSource, $retry) {
            static $lastUpdate = 0;
            $now = microtime(true);
            if ($now - $lastUpdate < 1) return 0;
            $lastUpdate = $now;

            $currentTotal = $existingSize + $downloaded;
            $percent = $totalSize > 0 ? round(($currentTotal / $totalSize) * 100, 1) : 0;

            writeStatus($statusFile, [
                'status'     => 'downloading',
                'source'     => $workingSource['name'] ?? '?',
                'downloaded' => $currentTotal,
                'total'      => $totalSize,
                'percent'    => $percent,
                'retry'      => $retry,
            ]);
            return 0;
        },
        CURLOPT_NOPROGRESS => false,
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);

    $newSize = file_exists($target) ? filesize($target) : 0;

    // کامل شد
    if ($totalSize > 0 && $newSize >= $totalSize) {
        $success = true;
        break;
    }

    // پیشرفت داشت → دوباره تلاش
    if (in_array($httpCode, [200, 206], true) && $newSize > $existingSize) {
        continue;
    }

    // پیشرفت نداشت → صبر کوتاه
    sleep(2);
}

// ═══════════════════════════════════════════════════════════
//  نتیجه نهایی
// ═══════════════════════════════════════════════════════════
if ($success) {
    $finalSize = filesize($target);
    writeStatus($statusFile, [
        'status'     => 'success',
        'message'    => 'دانلود کامل شد',
        'size'       => $finalSize,
        'downloaded' => $finalSize,
        'total'      => $finalSize,
        'percent'    => 100,
        'source'     => $workingSource['name'] ?? '?',
        'retries'    => $retry,
    ]);
    @unlink($sourcesFile);
    exit(0);
} else {
    $size = file_exists($target) ? filesize($target) : 0;
    writeStatus($statusFile, [
        'status'  => 'error',
        'error'   => "پس از {$retry} تلاش ناموفق",
        'size'    => $size,
        'partial' => $size > 0,
    ]);
    exit(1);
}

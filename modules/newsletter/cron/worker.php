<?php
/**
 * PartoCMS - Newsletter Cron Worker (Entry Point)
 *
 * CLI-only script. Run from crontab or manually:
 *
 *   php modules/newsletter/cron/worker.php
 *   php modules/newsletter/cron/worker.php --batch=50 --stuck=600
 *   php modules/newsletter/cron/worker.php --dry-run
 *   php modules/newsletter/cron/worker.php --help
 *
 * Recommended crontab entry (every 2 minutes):
 *   *\/2 * * * * cd /path/to/PartoCMS && php modules/newsletter/cron/worker.php >> /dev/null 2>&1
 *
 * @package PartoCMS\Newsletter\Cron
 */

declare(strict_types=1);

// ─── CLI-only guard ───
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from CLI.');
}

// ─── Locate project root (walk up 3 levels: cron -> newsletter -> modules -> root) ───
$projectRoot = dirname(__DIR__, 3);
$configFile  = $projectRoot . '/config.php';
$moduleBoot  = $projectRoot . '/modules/newsletter/bootstrap.php';
$vendorAuto  = $projectRoot . '/vendor/autoload.php';

if (!is_file($configFile) || !is_file($moduleBoot)) {
    fwrite(STDERR, "[FATAL] Cannot locate project files. Expected at: {$projectRoot}\n");
    exit(2);
}

// ─── Parse CLI options ───
$opts = getopt('', [
    'batch::',
    'stuck::',
    'max-batches::',
    'dry-run',
    'verbose',
    'help',
]);

if (isset($opts['help'])) {
    echo <<<HELP
Newsletter Worker — PartoCMS

Usage:
  php worker.php [options]

Options:
  --batch=N         Emails per batch (default: 50)
  --stuck=N         Stuck timeout in seconds (default: 600)
  --max-batches=N   Max batches per campaign per run (default: 100)
  --dry-run         Inspect without making changes
  --verbose         Also print log lines to STDERR
  --help            Show this help

Exit codes:
  0  Success (at least one phase ran)
  1  Could not acquire lock (another worker is running)
  2  Fatal error (config, DB, mailer)
  3  At least one phase failed but worker kept going

HELP;
    exit(0);
}

$batchSize    = isset($opts['batch'])       ? max(1, (int) $opts['batch'])       : 50;
$stuckTimeout = isset($opts['stuck'])       ? max(60, (int) $opts['stuck'])     : 600;
$maxBatches   = isset($opts['max-batches']) ? max(1, (int) $opts['max-batches']) : 100;
$dryRun       = isset($opts['dry-run']);
$verbose      = isset($opts['verbose']);

// ─── Lock file (prevent concurrent runs) ───
$lockFile = __DIR__ . '/worker.lock';
$lockFp   = fopen($lockFile, 'c');
if ($lockFp === false || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "[SKIP] Another worker is already running (lock: {$lockFile})\n");
    exit(1);
}

// ─── Load project + module ───
require_once $configFile;
require_once $moduleBoot;
if (is_file($vendorAuto)) {
    require_once $vendorAuto;
}

use PartoCMS\Newsletter\Application\Cron\WorkerRunner;
use PartoCMS\Newsletter\Infrastructure\Mailer\MailerFactory;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoCampaignRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSendLogRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;
use PartoCMS\Newsletter\Domain\Service\SendService;

// ─── Wire dependencies (no auth, no session) ───
try {
    $pdo          = getDB();
    $campaignRepo = new PdoCampaignRepository($pdo);
    $subRepo      = new PdoSubscriberRepository($pdo);
    $logRepo      = new PdoSendLogRepository($pdo);
    $mailer       = MailerFactory::fromDatabase($pdo)->build();

    $sendSvc = new SendService(
        $campaignRepo,
        $subRepo,
        $logRepo,
        $mailer
    );

    $logFile = __DIR__ . '/worker.log';
    $runner  = new WorkerRunner(
        $campaignRepo,
        $logRepo,
        $sendSvc,
        $logFile,
        $verbose
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "[FATAL] Wiring failed: {$e->getMessage()}\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(2);
}

// ─── Run ───
try {
    $result = $runner->run([
        'stuck_timeout'            => $stuckTimeout,
        'batch_size'               => $batchSize,
        'max_batches_per_campaign' => $maxBatches,
        'dry_run'                  => $dryRun,
    ]);
} catch (\Throwable $e) {
    fwrite(STDERR, "[FATAL] Worker crashed: {$e->getMessage()}\n");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(2);
}

// ─── Release lock ───
flock($lockFp, LOCK_UN);
fclose($lockFp);

// ─── Summary to STDOUT (useful when running manually) ───
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

// ─── Exit code ───
if (!empty($result['errors'])) {
    exit(3);
}
exit(0);

<?php
/**
 * PartoCMS - Newsletter Cron Worker Runner
 *
 * Orchestrates a single worker cycle:
 *   1. Recover campaigns stuck in "sending" state
 *   2. Start scheduled campaigns whose time has arrived
 *   3. Drain all sending campaigns in batches
 *
 * Pure application layer — depends only on repository interfaces
 * and the SendService. No HTTP, no session, no auth.
 *
 * @package PartoCMS\Newsletter\Application\Cron
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Application\Cron;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\Repository\CampaignRepositoryInterface;
use PartoCMS\Newsletter\Domain\Repository\SendLogRepositoryInterface;
use PartoCMS\Newsletter\Domain\Service\SendService;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;
use Throwable;

final class WorkerRunner
{
    private CampaignRepositoryInterface $campaignRepo;
    private SendLogRepositoryInterface  $logRepo;
    private SendService                 $sendSvc;
    private ?string                     $logFile;
    private bool                        $verbose;

    public function __construct(
        CampaignRepositoryInterface $campaignRepo,
        SendLogRepositoryInterface  $logRepo,
        SendService                 $sendSvc,
        ?string                     $logFile = null,
        bool                        $verbose = false
    ) {
        $this->campaignRepo = $campaignRepo;
        $this->logRepo      = $logRepo;
        $this->sendSvc      = $sendSvc;
        $this->logFile      = $logFile;
        $this->verbose      = $verbose;
    }

    /**
     * Execute a full worker cycle.
     *
     * @param array{
     *     stuck_timeout?: int,
     *     batch_size?: int,
     *     max_batches_per_campaign?: int,
     *     dry_run?: bool
     * } $options
     *
     * @return array{
     *     started_at: string,
     *     finished_at: string,
     *     duration: float,
     *     recovered: int,
     *     due_started: int,
     *     due_details: array<int, array{queued: int}>,
     *     campaigns_processed: int,
     *     emails_sent: int,
     *     emails_failed: int,
     *     batches: int,
     *     errors: string[]
     * }
     */
    public function run(array $options = []): array
    {
        $stuckTimeout = (int)  ($options['stuck_timeout']            ?? 600);
        $batchSize    = (int)  ($options['batch_size']               ?? 50);
        $maxBatches   = (int)  ($options['max_batches_per_campaign'] ?? 100);
        $dryRun       = (bool) ($options['dry_run']                  ?? false);

        $startedAt = new DateTimeImmutable();
        $startTs   = microtime(true);

        $this->log('info', '════════════════════════════════════════════');
        $this->log('info', sprintf(
            'Worker started%s — batch=%d, stuck_timeout=%ds, max_batches=%d',
            $dryRun ? ' [DRY-RUN]' : '',
            $batchSize,
            $stuckTimeout,
            $maxBatches
        ));

        $result = [
            'started_at'          => $startedAt->format('Y-m-d H:i:s'),
            'finished_at'         => '',
            'duration'            => 0.0,
            'recovered'           => 0,
            'due_started'         => 0,
            'due_details'         => [],
            'campaigns_processed' => 0,
            'emails_sent'         => 0,
            'emails_failed'       => 0,
            'batches'             => 0,
            'errors'              => [],
        ];

        if ($dryRun) {
            $this->dryRunReport($stuckTimeout);
            $result['finished_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $result['duration']    = round(microtime(true) - $startTs, 3);
            $this->log('info', 'DRY-RUN finished — no data was changed');
            return $result;
        }

        try {
            $result['recovered'] = $this->recoverStuck($stuckTimeout);

            [$started, $details] = $this->processDue($batchSize);
            $result['due_started'] = $started;
            $result['due_details'] = $details;

            $drain = $this->drainSending($batchSize, $maxBatches);
            $result['campaigns_processed'] = $drain['campaigns'];
            $result['emails_sent']         = $drain['sent'];
            $result['emails_failed']       = $drain['failed'];
            $result['batches']             = $drain['batches'];
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
            $this->log('error', 'Fatal: ' . $e->getMessage());
        }

        $result['finished_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $result['duration']    = round(microtime(true) - $startTs, 3);

        $this->log('info', sprintf(
            'Worker finished in %.2fs — recovered=%d, started=%d, campaigns=%d, sent=%d, failed=%d, batches=%d',
            $result['duration'],
            $result['recovered'],
            $result['due_started'],
            $result['campaigns_processed'],
            $result['emails_sent'],
            $result['emails_failed'],
            $result['batches']
        ));

        return $result;
    }

    // ─────────────────────────────────────────────────────────
    //  Phases
    // ─────────────────────────────────────────────────────────

    private function recoverStuck(int $timeout): int
    {
        $this->log('info', "Phase 1 — recover stuck (timeout={$timeout}s)");

        try {
            $count = $this->sendSvc->recoverStuckCampaigns($timeout);
            $this->log('info', "  → recovered {$count}");
            return $count;
        } catch (Throwable $e) {
            $this->log('error', '  recoverStuck failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @return array{0: int, 1: array<int, array{queued: int}>}
     */
    private function processDue(int $batchSize): array
    {
        $this->log('info', 'Phase 2 — process due (scheduled)');

        try {
            $r = $this->sendSvc->processDueCampaigns($batchSize);
            $this->log('info', "  → started {$r['started']} campaign(s)");
            return [(int) $r['started'], (array) $r['details']];
        } catch (Throwable $e) {
            $this->log('error', '  processDue failed: ' . $e->getMessage());
            return [0, []];
        }
    }

    /**
     * @return array{campaigns: int, sent: int, failed: int, batches: int}
     */
    private function drainSending(int $batchSize, int $maxBatches): array
    {
        $this->log('info', 'Phase 3 — drain sending campaigns');

        $total = $this->campaignRepo->count(CampaignStatus::SENDING);
        if ($total === 0) {
            $this->log('info', '  → none sending');
            return ['campaigns' => 0, 'sent' => 0, 'failed' => 0, 'batches' => 0];
        }

        $this->log('info', "  → {$total} sending campaign(s)");

        $perPage = 50;
        $pages   = (int) ceil($total / $perPage);
        $stats   = ['campaigns' => 0, 'sent' => 0, 'failed' => 0, 'batches' => 0];

        for ($page = 1; $page <= $pages; $page++) {
            $campaigns = $this->campaignRepo->findPaginated(
                $page,
                $perPage,
                ['status' => CampaignStatus::SENDING]
            );

            foreach ($campaigns as $campaign) {
                $id = $campaign->id();
                if ($id === null) {
                    continue;
                }

                $stats['campaigns']++;
                $batchCount = 0;

                while ($batchCount < $maxBatches) {
                    try {
                        $r = $this->sendSvc->processQueue($id, $batchSize);
                    } catch (Throwable $e) {
                        $this->log('error', "    #{$id} batch failed: " . $e->getMessage());
                        break;
                    }

                    $stats['batches']++;
                    $stats['sent']   += (int) $r['sent'];
                    $stats['failed'] += (int) $r['failed'];
                    $batchCount++;

                    $this->log('info', sprintf(
                        '    #%d batch %d — sent=%d, failed=%d, remaining=%d',
                        $id, $batchCount, $r['sent'], $r['failed'], $r['remaining']
                    ));

                    if (!empty($r['campaign_completed']) || (int) $r['remaining'] === 0) {
                        break;
                    }
                    if ((int) $r['processed'] === 0) {
                        $this->log('warn', "    #{$id} no progress, aborting this campaign");
                        break;
                    }

                    // Small throttle between batches to avoid hammering SMTP.
                    usleep(250_000); // 0.25s
                }
            }
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────
    //  Dry-run
    // ─────────────────────────────────────────────────────────

    private function dryRunReport(int $stuckTimeout): void
    {
        $this->log('info', 'DRY-RUN — inspecting without changes');

        try {
            $stuckBefore = (new DateTimeImmutable())->modify("-{$stuckTimeout} seconds");
            $stuck       = $this->campaignRepo->findStuck($stuckBefore);
            $this->log('info', '  would recover stuck: ' . count($stuck));
        } catch (Throwable $e) {
            $this->log('error', '  findStuck failed: ' . $e->getMessage());
        }

        try {
            $due = $this->campaignRepo->findDueForSending(new DateTimeImmutable());
            $this->log('info', '  would start due campaigns: ' . count($due));
        } catch (Throwable $e) {
            $this->log('error', '  findDueForSending failed: ' . $e->getMessage());
        }

        try {
            $sending = $this->campaignRepo->count(CampaignStatus::SENDING);
            $this->log('info', '  would drain sending campaigns: ' . $sending);
        } catch (Throwable $e) {
            $this->log('error', '  count(SENDING) failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Logging
    // ─────────────────────────────────────────────────────────

    private function log(string $level, string $msg): void
    {
        $line = sprintf(
            '[%s] [%s] %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $msg
        );

        if ($this->logFile !== null && $this->logFile !== '') {
            @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        if ($this->verbose) {
            fwrite(STDERR, $line . PHP_EOL);
        }
    }
}

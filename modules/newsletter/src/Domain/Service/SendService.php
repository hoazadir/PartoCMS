<?php
/**
 * PartoCMS - Newsletter Send Service (Worker)
 *
 * Processes queued SendLog records: sends emails via MailerInterface.
 * Designed for background workers (cron / queue).
 *
 * @package PartoCMS\Newsletter\Domain\Service
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Service;

use PartoCMS\Newsletter\Domain\Entity\Campaign;
use PartoCMS\Newsletter\Domain\Entity\Subscriber;
use PartoCMS\Newsletter\Domain\Repository\CampaignRepositoryInterface;
use PartoCMS\Newsletter\Domain\Repository\SendLogRepositoryInterface;
use PartoCMS\Newsletter\Domain\Repository\SubscriberRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;
use PartoCMS\Newsletter\Domain\ValueObject\SendStatus;
use PartoCMS\Newsletter\Infrastructure\Mailer\MailerInterface;
use Throwable;

final class SendService
{
    private CampaignRepositoryInterface   $campaignRepo;
    private SubscriberRepositoryInterface $subscriberRepo;
    private SendLogRepositoryInterface    $logRepo;
    private MailerInterface               $mailer;

    public function __construct(
        CampaignRepositoryInterface   $campaignRepo,
        SubscriberRepositoryInterface $subscriberRepo,
        SendLogRepositoryInterface    $logRepo,
        MailerInterface               $mailer
    ) {
        $this->campaignRepo   = $campaignRepo;
        $this->subscriberRepo = $subscriberRepo;
        $this->logRepo        = $logRepo;
        $this->mailer         = $mailer;
    }

    /**
     * Process the next batch of queued emails for a campaign.
     *
     * @return array{
     *     processed: int,
     *     sent: int,
     *     failed: int,
     *     remaining: int,
     *     campaign_completed: bool
     * }
     */
    public function processQueue(int $campaignId, int $batchSize = 50): array
    {
        $campaign = $this->campaignRepo->findById($campaignId);
        if ($campaign === null) {
            throw new \RuntimeException("Campaign #{$campaignId} not found.");
        }

        if ($campaign->status() !== CampaignStatus::SENDING) {
            return [
                'processed'          => 0,
                'sent'               => 0,
                'failed'             => 0,
                'remaining'          => 0,
                'campaign_completed' => false,
            ];
        }

        $batch = $this->logRepo->fetchQueued($campaignId, $batchSize);

        $sent   = 0;
        $failed = 0;

        foreach ($batch as $log) {
            $subscriber = $this->subscriberRepo->findById($log->subscriberId());
            if ($subscriber === null) {
                $log->markAsFailed('subscriber_not_found');
                $this->logRepo->save($log);
                $failed++;
                continue;
            }

            try {
                $messageId = $this->mailer->send(
                    to: $subscriber->email()->value(),
                    toName: $subscriber->name(),
                    subject: $campaign->subject(),
                    bodyHtml: $campaign->bodyHtml(),
                    bodyText: $campaign->bodyText(),
                    preheader: $campaign->preheader()
                );

                $log->markAsSent($messageId);
                $this->logRepo->save($log);
                $sent++;
            } catch (Throwable $e) {
                $log->markAsFailed($e->getMessage());
                $this->logRepo->save($log);
                $failed++;
            }
        }

        // Update campaign counters
        if ($sent > 0)   $campaign->incrementSent($sent);
        if ($failed > 0) $campaign->incrementFailed($failed);
        $this->campaignRepo->save($campaign);

        // Check completion
        $remaining = $this->logRepo->count($campaignId, SendStatus::QUEUED);
        $completed = false;

        if ($remaining === 0) {
            $campaign->complete();
            $this->campaignRepo->save($campaign);
            $completed = true;
        }

        return [
            'processed'          => count($batch),
            'sent'               => $sent,
            'failed'             => $failed,
            'remaining'          => $remaining,
            'campaign_completed' => $completed,
        ];
    }

    /**
     * Process all due scheduled campaigns.
     */
    public function processDueCampaigns(int $batchSize = 50): array
    {
        $now = new \DateTimeImmutable();
        $due = $this->campaignRepo->findDueForSending($now);

        $started = 0;
        $details = [];

        foreach ($due as $campaign) {
            $campaign->startSending();
            $this->campaignRepo->save($campaign);

            $subscribers = $this->subscriberRepo->findAllActive();
            $logs = [];
            foreach ($subscribers as $s) {
                if ($this->logRepo->existsForCampaign($campaign->id(), $s->id())) {
                    continue;
                }
                $logs[] = \PartoCMS\Newsletter\Domain\Entity\SendLog::queue(
                    $campaign->id(),
                    $s->id()
                );
            }
            if (!empty($logs)) {
                $this->logRepo->saveMany($logs);
            }

            $details[$campaign->id()] = ['queued' => count($logs)];
            $started++;
        }

        return [
            'started' => $started,
            'details' => $details,
        ];
    }

    /**
     * Recover campaigns stuck in "sending" state.
     */
    public function recoverStuckCampaigns(int $timeoutSeconds = 300): int
    {
        $before = (new \DateTimeImmutable())->modify("-{$timeoutSeconds} seconds");
        $stuck  = $this->campaignRepo->findStuck($before);

        foreach ($stuck as $campaign) {
            $campaign->fail('timeout_recovery');
            $this->campaignRepo->save($campaign);
        }

        return count($stuck);
    }

    /**
     * Record an open event.
     */
    public function recordOpen(int $campaignId, int $subscriberId): bool
    {
        $log = $this->logRepo->findByCampaignAndSubscriber($campaignId, $subscriberId);
        if ($log === null) {
            return false;
        }

        $wasOpened = $log->isOpened();
        $log->markAsOpened();
        $this->logRepo->save($log);

        if (!$wasOpened) {
            $campaign = $this->campaignRepo->findById($campaignId);
            if ($campaign !== null) {
                $campaign->incrementOpen();
                $this->campaignRepo->save($campaign);
            }
        }

        return true;
    }

    /**
     * Record a click event.
     */
    public function recordClick(int $campaignId, int $subscriberId): bool
    {
        $log = $this->logRepo->findByCampaignAndSubscriber($campaignId, $subscriberId);
        if ($log === null) {
            return false;
        }

        $wasClicked = $log->isClicked();
        $log->markAsClicked();
        $this->logRepo->save($log);

        if (!$wasClicked) {
            $campaign = $this->campaignRepo->findById($campaignId);
            if ($campaign !== null) {
                $campaign->incrementClick();
                $this->campaignRepo->save($campaign);
            }
        }

        return true;
    }
}

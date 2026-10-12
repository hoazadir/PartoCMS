<?php
/**
 * PartoCMS - Newsletter Campaign Service
 *
 * Orchestrates campaign lifecycle: create, schedule, send (queue),
 * pause/resume, complete. Bridges Campaign ↔ SendLog.
 *
 * @package PartoCMS\Newsletter\Domain\Service
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Service;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\Entity\Campaign;
use PartoCMS\Newsletter\Domain\Entity\SendLog;
use PartoCMS\Newsletter\Domain\Repository\CampaignRepositoryInterface;
use PartoCMS\Newsletter\Domain\Repository\SendLogRepositoryInterface;
use PartoCMS\Newsletter\Domain\Repository\SubscriberRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

final class CampaignService
{
    private CampaignRepositoryInterface    $campaignRepo;
    private SubscriberRepositoryInterface  $subscriberRepo;
    private SendLogRepositoryInterface     $logRepo;

    public function __construct(
        CampaignRepositoryInterface   $campaignRepo,
        SubscriberRepositoryInterface $subscriberRepo,
        SendLogRepositoryInterface    $logRepo
    ) {
        $this->campaignRepo   = $campaignRepo;
        $this->subscriberRepo = $subscriberRepo;
        $this->logRepo        = $logRepo;
    }

    /**
     * Create a new draft campaign.
     */
    public function createCampaign(
        string $title,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $preheader = null,
        ?int $createdBy = null
    ): Campaign {
        $campaign = Campaign::create(
            title: $title,
            subject: $subject,
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            preheader: $preheader,
            createdBy: $createdBy
        );
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Update campaign content (draft only).
     */
    public function updateCampaign(
        int $id,
        string $title,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null
    ): Campaign {
        $campaign = $this->findOrFail($id);
        $campaign->updateContent($title, $subject, $bodyHtml, $bodyText);
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Schedule campaign for a future datetime.
     */
    public function schedule(int $id, DateTimeImmutable $when): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->schedule($when);
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Cancel scheduling (back to draft).
     */
    public function unschedule(int $id): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->unschedule();
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Start sending: queue SendLog records for every active subscriber.
     * Does NOT actually send emails (that's SendService/worker).
     *
     * @return array{queued: int, campaign: Campaign}
     */
    public function startSending(int $id): array
    {
        $campaign = $this->findOrFail($id);

        // Transition to "sending"
        $campaign->startSending();
        $campaign = $this->campaignRepo->save($campaign);

        // Queue logs for active subscribers who aren't already queued
        $subscribers = $this->subscriberRepo->findAllActive();
        $logs = [];

        foreach ($subscribers as $s) {
            if ($this->logRepo->existsForCampaign($campaign->id(), $s->id())) {
                continue;
            }
            $logs[] = SendLog::queue($campaign->id(), $s->id());
        }

        if (!empty($logs)) {
            $this->logRepo->saveMany($logs);
        }

        return [
            'queued'   => count($logs),
            'campaign' => $campaign,
        ];
    }

    /**
     * Pause an in-progress campaign.
     */
    public function pause(int $id): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->pause();
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Resume a paused campaign.
     */
    public function resume(int $id): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->resume();
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Mark campaign as completed.
     * Called by worker when queue is empty.
     */
    public function complete(int $id): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->complete();
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Mark campaign as failed.
     */
    public function fail(int $id, string $reason = ''): Campaign
    {
        $campaign = $this->findOrFail($id);
        $campaign->fail($reason);
        return $this->campaignRepo->save($campaign);
    }

    /**
     * Delete campaign (cascades to logs via FK).
     */
    public function delete(int $id): void
    {
        $campaign = $this->findOrFail($id);
        $this->campaignRepo->delete($campaign);
    }

    /**
     * Get campaign with statistics.
     */
    public function findWithStats(int $id): ?array
    {
        $campaign = $this->campaignRepo->findById($id);
        if ($campaign === null) {
            return null;
        }
        return [
            'campaign'   => $campaign,
            'breakdown'  => $this->logRepo->getStatusBreakdown($id),
        ];
    }

    /**
     * Paginated list with filters.
     *
     * @return array{items: Campaign[], total: int, page: int, per_page: int, pages: int}
     */
    public function listCampaigns(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $items = $this->campaignRepo->findPaginated($page, $perPage, $filters);

        $statusFilter = null;
        if (!empty($filters['status']) && $filters['status'] instanceof CampaignStatus) {
            $statusFilter = $filters['status'];
        }
        $total = $this->campaignRepo->count($statusFilter);
        $pages = (int) ceil($total / $perPage);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Global statistics (for dashboard).
     */
    public function getGlobalStats(): array
    {
        return $this->campaignRepo->getGlobalStats();
    }

    /**
     * Find or throw if not exists.
     */
    private function findOrFail(int $id): Campaign
    {
        $campaign = $this->campaignRepo->findById($id);
        if ($campaign === null) {
            throw new \RuntimeException("Campaign #{$id} not found.");
        }
        return $campaign;
    }
}

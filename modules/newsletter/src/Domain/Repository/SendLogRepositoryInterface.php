<?php
/**
 * PartoCMS - Newsletter SendLog Repository Interface
 *
 * Contract for send-log persistence.
 *
 * @package PartoCMS\Newsletter\Domain\Repository
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Repository;

use PartoCMS\Newsletter\Domain\Entity\SendLog;
use PartoCMS\Newsletter\Domain\ValueObject\SendStatus;

interface SendLogRepositoryInterface
{
    public function findById(int $id): ?SendLog;

    /**
     * Find send log by campaign + subscriber (unique pair).
     */
    public function findByCampaignAndSubscriber(int $campaignId, int $subscriberId): ?SendLog;

    public function save(SendLog $log): SendLog;

    /**
     * Bulk queue inserts (for campaign sending).
     *
     * @param SendLog[] $logs
     */
    public function saveMany(array $logs): void;

    public function delete(SendLog $log): void;

    /**
     * Delete all logs for a campaign (cascade manual).
     */
    public function deleteByCampaign(int $campaignId): void;

    public function count(int $campaignId, ?SendStatus $status = null): int;

    /**
     * Get next queued logs (for worker to process).
     *
     * @return SendLog[]
     */
    public function fetchQueued(int $campaignId, int $limit = 50): array;

    /**
     * Per-campaign breakdown of statuses.
     *
     * @return array<string, int>  e.g. ['sent' => 10, 'failed' => 2, ...]
     */
    public function getStatusBreakdown(int $campaignId): array;

    /**
     * Check whether a subscriber has already been queued for a campaign.
     */
    public function existsForCampaign(int $campaignId, int $subscriberId): bool;
}

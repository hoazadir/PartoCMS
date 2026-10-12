<?php
/**
 * PartoCMS - Newsletter Campaign Repository Interface
 *
 * Contract for campaign persistence.
 *
 * @package PartoCMS\Newsletter\Domain\Repository
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Repository;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\Entity\Campaign;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

interface CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign;

    public function save(Campaign $campaign): Campaign;

    public function delete(Campaign $campaign): void;

    public function count(?CampaignStatus $status = null): int;

    /**
     * @param array $filters Supported keys: status, search
     * @return Campaign[]
     */
    public function findPaginated(int $page, int $perPage, array $filters = []): array;

    /**
     * Find campaigns that are scheduled and due to send.
     *
     * @return Campaign[]
     */
    public function findDueForSending(DateTimeImmutable $now): array;

    /**
     * Find campaigns stuck in "sending" state (for recovery).
     *
     * @return Campaign[]
     */
    public function findStuck(DateTimeImmutable $before): array;

    /**
     * Aggregate stats across all campaigns.
     *
     * @return array{
     *     total: int,
     *     sent: int,
     *     total_sent_emails: int,
     *     total_failed: int,
     *     total_opened: int,
     *     total_clicked: int
     * }
     */
    public function getGlobalStats(): array;
}

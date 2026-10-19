<?php
/**
 * PartoCMS — Team Module
 * Repository Interface: TeamMemberRepositoryInterface
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Domain\Repository;

use PartoCMS\Team\Domain\Entity\TeamMember;

interface TeamMemberRepositoryInterface
{
    // Write
    public function create(TeamMember $member): int;
    public function update(TeamMember $member): bool;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): bool;
    public function toggleFeatured(int $id): bool;
    public function updateSortOrder(int $id, int $order): bool;

    // Read
    public function findById(int $id): ?TeamMember;
    
    /**
     * @return TeamMember[]
     */
    public function findAll(array $filters = []): array;
    
    /**
     * @return TeamMember[]
     */
    public function findPublished(array $filters = []): array;
    
    public function count(array $filters = []): int;
    
    public function getStats(): array;

    // Bulk
    public function bulkUpdateStatus(array $ids, string $status): int;
    public function bulkDelete(array $ids): int;
}

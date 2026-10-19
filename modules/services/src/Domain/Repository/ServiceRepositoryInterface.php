<?php
/**
 * PartoCMS — Services Module
 * Repository Interface: ServiceRepositoryInterface
 */

declare(strict_types=1);

namespace PartoCMS\Services\Domain\Repository;

use PartoCMS\Services\Domain\Entity\ServiceItem;

interface ServiceRepositoryInterface
{
    public function create(ServiceItem $service): int;
    public function update(ServiceItem $service): bool;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): bool;
    public function toggleFeatured(int $id): bool;
    public function updateSortOrder(int $id, int $order): bool;

    public function findById(int $id): ?ServiceItem;
    public function findBySlug(string $slug): ?ServiceItem;
    public function findAll(array $filters = []): array;
    public function findPublished(array $filters = []): array;
    public function count(array $filters = []): int;
    public function getStats(): array;
    public function getCategories(): array;

    public function bulkUpdateStatus(array $ids, string $status): int;
    public function bulkDelete(array $ids): int;
}

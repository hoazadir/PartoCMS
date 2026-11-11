<?php
/**
 * PartoCMS — Properties Module
 * Repository Interface: PropertyRepositoryInterface
 */

declare(strict_types=1);

namespace PartoCMS\Properties\Domain\Repository;

use PartoCMS\Properties\Domain\Entity\Property;

interface PropertyRepositoryInterface
{
    public function create(Property $property): int;
    public function update(Property $property): bool;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): bool;
    public function toggleFeatured(int $id): bool;
    public function updateSortOrder(int $id, int $order): bool;

    public function findById(int $id): ?Property;
    public function findBySlug(string $slug): ?Property;
    public function findAll(array $filters = []): array;
    public function findPublished(array $filters = []): array;
    public function count(array $filters = []): int;

    public function getStats(): array;
    public function getCities(): array;
    public function getRegions(?string $city = null): array;
    public function getAmenitiesList(): array;

    public function bulkUpdateStatus(array $ids, string $status): int;
    public function bulkDelete(array $ids): int;
}

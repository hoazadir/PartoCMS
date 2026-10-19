<?php
/**
 * PartoCMS — Portfolio Module
 * Repository Interface: PortfolioRepositoryInterface
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Domain\Repository;

use PartoCMS\Portfolio\Domain\Entity\PortfolioItem;

interface PortfolioRepositoryInterface
{
    public function create(PortfolioItem $item): int;
    public function update(PortfolioItem $item): bool;
    public function delete(int $id): bool;
    public function updateStatus(int $id, string $status): bool;
    public function toggleFeatured(int $id): bool;
    public function updateSortOrder(int $id, int $order): bool;

    public function findById(int $id): ?PortfolioItem;
    public function findBySlug(string $slug): ?PortfolioItem;
    public function findAll(array $filters = []): array;
    public function findPublished(array $filters = []): array;
    public function count(array $filters = []): int;
    public function getStats(): array;
    public function getCategories(): array;
    public function getAllTags(): array;

    public function bulkUpdateStatus(array $ids, string $status): int;
    public function bulkDelete(array $ids): int;
}

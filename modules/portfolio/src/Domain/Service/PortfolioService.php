<?php
/**
 * PartoCMS — Portfolio Module
 * Domain Service: PortfolioService
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Domain\Service;

use PartoCMS\Portfolio\Domain\Entity\PortfolioItem;
use PartoCMS\Portfolio\Domain\Exception\PortfolioException;
use PartoCMS\Portfolio\Domain\Repository\PortfolioRepositoryInterface;

final class PortfolioService
{
    public function __construct(
        private readonly PortfolioRepositoryInterface $repository
    ) {}

    public function createByAdmin(array $data, bool $publishImmediately = false): int
    {
        $gallery = is_array($data['gallery'] ?? null) ? $data['gallery'] : [];
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];

        $item = new PortfolioItem(
            null,
            (string) ($data['title'] ?? ''),
            $data['slug'] ?? null,
            $data['client_name'] ?? null,
            $data['description'] ?? null,
            $data['content'] ?? null,
            $data['thumbnail_url'] ?? null,
            $gallery,
            $data['category'] ?? null,
            $tags,
            $data['project_date'] ?? null,
            $data['project_url'] ?? null,
            $publishImmediately ? PortfolioItem::STATUS_PUBLISHED : PortfolioItem::STATUS_HIDDEN,
            (bool) ($data['is_featured'] ?? false),
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['language'] ?? 'fa-IR')
        );
        return $this->repository->create($item);
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $item = $this->getByIdOrFail($id);

        if (isset($data['title']))         $item->setTitle((string) $data['title']);
        if (isset($data['slug']))          $item->setSlug((string) $data['slug']);
        if (isset($data['client_name']))   $item->setClientName((string) $data['client_name']);
        if (isset($data['description']))   $item->setDescription((string) $data['description']);
        if (isset($data['content']))       $item->setContent((string) $data['content']);
        if (isset($data['thumbnail_url'])) $item->setThumbnailUrl((string) $data['thumbnail_url']);
        if (isset($data['gallery']))       $item->setGallery(is_array($data['gallery']) ? $data['gallery'] : []);
        if (isset($data['category']))      $item->setCategory((string) $data['category']);
        if (isset($data['tags']))          $item->setTags(is_array($data['tags']) ? $data['tags'] : []);
        if (isset($data['project_date']))  $item->setProjectDate((string) $data['project_date']);
        if (isset($data['project_url']))   $item->setProjectUrl((string) $data['project_url']);
        if (isset($data['language']))      $item->setLanguage((string) $data['language']);
        if (isset($data['sort_order']))    $item->setSortOrder((int) $data['sort_order']);

        if (isset($data['is_featured']) && (bool) $data['is_featured'] !== $item->isFeatured()) {
            $item->toggleFeatured();
        }
        if (isset($data['status'])) {
            $item->setStatus((string) $data['status']);
        }

        return $this->repository->update($item);
    }

    public function publish(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, PortfolioItem::STATUS_PUBLISHED);
    }

    public function hide(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, PortfolioItem::STATUS_HIDDEN);
    }

    public function toggleFeatured(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->toggleFeatured($id);
    }

    public function updateSortOrder(int $id, int $order): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateSortOrder($id, $order);
    }

    public function delete(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->delete($id);
    }

    public function bulkPublish(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, PortfolioItem::STATUS_PUBLISHED);
    }

    public function bulkHide(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, PortfolioItem::STATUS_HIDDEN);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    public function getById(int $id): ?PortfolioItem
    {
        return $this->repository->findById($id);
    }

    public function getByIdOrFail(int $id): PortfolioItem
    {
        $p = $this->repository->findById($id);
        if ($p === null) {
            throw PortfolioException::notFound($id);
        }
        return $p;
    }

    public function getBySlug(string $slug): ?PortfolioItem
    {
        return $this->repository->findBySlug($slug);
    }

    public function getAll(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    public function getPublished(array $filters = []): array
    {
        return $this->repository->findPublished($filters);
    }

    public function getFeatured(int $limit = 6): array
    {
        return $this->repository->findPublished(['is_featured' => true, 'limit' => $limit]);
    }

    public function getCount(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    public function getCategories(): array
    {
        return $this->repository->getCategories();
    }

    public function getAllTags(): array
    {
        return $this->repository->getAllTags();
    }

    public function formatForWidget(PortfolioItem $p): array
    {
        return [
            'id'            => $p->getId() ?? 0,
            'title'         => $p->getTitle(),
            'slug'          => $p->getSlug(),
            'client_name'   => $p->getClientName(),
            'description'   => $p->getDescription(),
            'content'       => $p->getContent(),
            'thumbnail_url' => $p->getThumbnailUrl(),
            'gallery'       => $p->getGallery(),
            'category'      => $p->getCategory(),
            'tags'          => $p->getTags(),
            'project_date'  => $p->getProjectDate(),
            'project_url'   => $p->getProjectUrl(),
            'is_featured'   => $p->isFeatured(),
        ];
    }
}

<?php
/**
 * PartoCMS — Services Module
 * Domain Service: ServiceService
 */

declare(strict_types=1);

namespace PartoCMS\Services\Domain\Service;

use PartoCMS\Services\Domain\Entity\ServiceItem;
use PartoCMS\Services\Domain\Exception\ServiceException;
use PartoCMS\Services\Domain\Repository\ServiceRepositoryInterface;

final class ServiceService
{
    public function __construct(
        private readonly ServiceRepositoryInterface $repository
    ) {}

    public function createByAdmin(array $data, bool $publishImmediately = false): int
    {
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $item = new ServiceItem(
            null,
            (string) ($data['title'] ?? ''),
            $data['slug'] ?? null,
            $data['description'] ?? null,
            $data['content'] ?? null,
            $data['icon'] ?? null,
            $data['image_url'] ?? null,
            isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            $data['price_unit'] ?? null,
            $features,
            $data['category'] ?? null,
            $publishImmediately ? ServiceItem::STATUS_PUBLISHED : ServiceItem::STATUS_HIDDEN,
            (bool) ($data['is_featured'] ?? false),
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['language'] ?? 'fa-IR')
        );
        return $this->repository->create($item);
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $item = $this->getByIdOrFail($id);

        if (isset($data['title']))       $item->setTitle((string) $data['title']);
        if (isset($data['slug']))        $item->setSlug((string) $data['slug']);
        if (isset($data['description'])) $item->setDescription((string) $data['description']);
        if (isset($data['content']))     $item->setContent((string) $data['content']);
        if (isset($data['icon']))        $item->setIcon((string) $data['icon']);
        if (isset($data['image_url']))   $item->setImageUrl((string) $data['image_url']);
        if (array_key_exists('price', $data)) {
            $price = $data['price'] !== '' && $data['price'] !== null ? (float) $data['price'] : null;
            $item->setPrice($price);
        }
        if (isset($data['price_unit']))  $item->setPriceUnit((string) $data['price_unit']);
        if (isset($data['features']))    $item->setFeatures(is_array($data['features']) ? $data['features'] : []);
        if (isset($data['category']))    $item->setCategory((string) $data['category']);
        if (isset($data['language']))    $item->setLanguage((string) $data['language']);
        if (isset($data['sort_order']))  $item->setSortOrder((int) $data['sort_order']);
        
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
        return $this->repository->updateStatus($id, ServiceItem::STATUS_PUBLISHED);
    }

    public function hide(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, ServiceItem::STATUS_HIDDEN);
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
        return $this->repository->bulkUpdateStatus($ids, ServiceItem::STATUS_PUBLISHED);
    }

    public function bulkHide(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, ServiceItem::STATUS_HIDDEN);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    public function getById(int $id): ?ServiceItem
    {
        return $this->repository->findById($id);
    }

    public function getByIdOrFail(int $id): ServiceItem
    {
        $s = $this->repository->findById($id);
        if ($s === null) {
            throw ServiceException::notFound($id);
        }
        return $s;
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

    public function formatForWidget(ServiceItem $s): array
    {
        return [
            'id'          => $s->getId() ?? 0,
            'title'       => $s->getTitle(),
            'description' => $s->getDescription(),
            'content'     => $s->getContent(),
            'icon'        => $s->getIcon(),
            'image_url'   => $s->getImageUrl(),
            'price'       => $s->getPrice(),
            'price_unit'  => $s->getPriceUnit(),
            'features'    => $s->getFeatures(),
            'category'    => $s->getCategory(),
            'is_featured' => $s->isFeatured(),
        ];
    }
}

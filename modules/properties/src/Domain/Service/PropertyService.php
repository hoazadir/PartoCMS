<?php
/**
 * PartoCMS — Properties Module
 * Domain Service: PropertyService
 */

declare(strict_types=1);

namespace PartoCMS\Properties\Domain\Service;

use PartoCMS\Properties\Domain\Entity\Property;
use PartoCMS\Properties\Domain\Exception\PropertyException;
use PartoCMS\Properties\Domain\Repository\PropertyRepositoryInterface;

final class PropertyService
{
    public function __construct(
        private readonly PropertyRepositoryInterface $repository
    ) {}

    public function createByAdmin(array $data, bool $publishImmediately = false): int
    {
        $property = new Property(
            null,
            (string) ($data['title'] ?? ''),
            $data['slug'] ?? null,
            $data['description'] ?? null,
            $data['content'] ?? null,
            (string) ($data['property_type'] ?? 'apartment'),
            (string) ($data['deal_type'] ?? 'sale'),
            isset($data['area']) ? (int) $data['area'] : null,
            isset($data['bedrooms']) ? (int) $data['bedrooms'] : null,
            isset($data['bathrooms']) ? (int) $data['bathrooms'] : null,
            isset($data['floor']) ? (int) $data['floor'] : null,
            isset($data['total_floors']) ? (int) $data['total_floors'] : null,
            isset($data['year_built']) ? (int) $data['year_built'] : null,
            $data['document_type'] ?? null,
            isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
            isset($data['rent_price']) && $data['rent_price'] !== '' ? (float) $data['rent_price'] : null,
            isset($data['deposit']) && $data['deposit'] !== '' ? (float) $data['deposit'] : null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['region'] ?? null,
            isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            $data['thumbnail_url'] ?? null,
            is_array($data['gallery'] ?? null) ? $data['gallery'] : [],
            is_array($data['amenities'] ?? null) ? $data['amenities'] : [],
            isset($data['agent_id']) && $data['agent_id'] !== '' ? (int) $data['agent_id'] : null,
            $publishImmediately ? Property::STATUS_PUBLISHED : Property::STATUS_PENDING,
            (bool) ($data['is_featured'] ?? false),
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['language'] ?? 'fa-IR'),
            $data['submitted_ip'] ?? null
        );
        return $this->repository->create($property);
    }

    public function submitFromFrontend(array $data, ?string $ip = null): int
    {
        $data['submitted_ip'] = $ip;
        return $this->createByAdmin($data, false);
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $p = $this->getByIdOrFail($id);

        if (isset($data['title']))         $p->setTitle((string) $data['title']);
        if (isset($data['slug']))          $p->setSlug((string) $data['slug']);
        if (isset($data['description']))   $p->setDescription((string) $data['description']);
        if (isset($data['content']))       $p->setContent((string) $data['content']);
        if (isset($data['property_type'])) $p->setPropertyType((string) $data['property_type']);
        if (isset($data['deal_type']))     $p->setDealType((string) $data['deal_type']);
        if (array_key_exists('area', $data))         $p->setArea($data['area'] !== '' ? (int) $data['area'] : null);
        if (array_key_exists('bedrooms', $data))     $p->setBedrooms($data['bedrooms'] !== '' ? (int) $data['bedrooms'] : null);
        if (array_key_exists('bathrooms', $data))    $p->setBathrooms($data['bathrooms'] !== '' ? (int) $data['bathrooms'] : null);
        if (array_key_exists('floor', $data))        $p->setFloor($data['floor'] !== '' ? (int) $data['floor'] : null);
        if (array_key_exists('total_floors', $data)) $p->setTotalFloors($data['total_floors'] !== '' ? (int) $data['total_floors'] : null);
        if (array_key_exists('year_built', $data))   $p->setYearBuilt($data['year_built'] !== '' ? (int) $data['year_built'] : null);
        if (isset($data['document_type'])) $p->setDocumentType((string) $data['document_type']);
        if (array_key_exists('price', $data))      $p->setPrice($data['price'] !== '' ? (float) $data['price'] : null);
        if (array_key_exists('rent_price', $data)) $p->setRentPrice($data['rent_price'] !== '' ? (float) $data['rent_price'] : null);
        if (array_key_exists('deposit', $data))    $p->setDeposit($data['deposit'] !== '' ? (float) $data['deposit'] : null);
        if (isset($data['address']))       $p->setAddress((string) $data['address']);
        if (isset($data['city']))          $p->setCity((string) $data['city']);
        if (isset($data['region']))        $p->setRegion((string) $data['region']);
        if (array_key_exists('latitude', $data))  $p->setLatitude($data['latitude'] !== '' ? (float) $data['latitude'] : null);
        if (array_key_exists('longitude', $data)) $p->setLongitude($data['longitude'] !== '' ? (float) $data['longitude'] : null);
        if (isset($data['thumbnail_url'])) $p->setThumbnailUrl((string) $data['thumbnail_url']);
        if (isset($data['gallery']))       $p->setGallery(is_array($data['gallery']) ? $data['gallery'] : []);
        if (isset($data['amenities']))     $p->setAmenities(is_array($data['amenities']) ? $data['amenities'] : []);
        if (array_key_exists('agent_id', $data)) $p->setAgentId($data['agent_id'] !== '' ? (int) $data['agent_id'] : null);
        if (isset($data['language']))      $p->setLanguage((string) $data['language']);
        if (isset($data['sort_order']))    $p->setSortOrder((int) $data['sort_order']);

        if (isset($data['is_featured']) && (bool) $data['is_featured'] !== $p->isFeatured()) {
            $p->toggleFeatured();
        }
        if (isset($data['status'])) {
            $p->setStatus((string) $data['status']);
        }

        return $this->repository->update($p);
    }

    public function publish(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Property::STATUS_PUBLISHED);
    }

    public function hide(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Property::STATUS_HIDDEN);
    }

    public function markAsSold(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Property::STATUS_SOLD);
    }

    public function markAsRented(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Property::STATUS_RENTED);
    }

    public function markAsPending(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, Property::STATUS_PENDING);
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
        return $this->repository->bulkUpdateStatus($ids, Property::STATUS_PUBLISHED);
    }

    public function bulkHide(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, Property::STATUS_HIDDEN);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    public function getById(int $id): ?Property
    {
        return $this->repository->findById($id);
    }

    public function getByIdOrFail(int $id): Property
    {
        $p = $this->repository->findById($id);
        if ($p === null) {
            throw PropertyException::notFound($id);
        }
        return $p;
    }

    public function getBySlug(string $slug): ?Property
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

    public function getCities(): array
    {
        return $this->repository->getCities();
    }

    public function getRegions(?string $city = null): array
    {
        return $this->repository->getRegions($city);
    }

    public function getAmenitiesList(): array
    {
        return $this->repository->getAmenitiesList();
    }
}

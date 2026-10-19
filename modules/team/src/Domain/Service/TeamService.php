<?php
/**
 * PartoCMS — Team Module
 * Domain Service: TeamService
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Domain\Service;

use InvalidArgumentException;
use PartoCMS\Team\Domain\Entity\TeamMember;
use PartoCMS\Team\Domain\Exception\TeamException;
use PartoCMS\Team\Domain\Repository\TeamMemberRepositoryInterface;

final class TeamService
{
    public function __construct(
        private readonly TeamMemberRepositoryInterface $repository
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Write Operations
    // ═══════════════════════════════════════════════════════════

    public function createByAdmin(array $data, bool $publishImmediately = false): int
    {
        $member = new TeamMember(
            null,
            (string) ($data['name'] ?? ''),
            $data['position'] ?? null,
            $data['department'] ?? null,
            $data['bio'] ?? null,
            $data['photo_url'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['linkedin'] ?? null,
            $data['twitter'] ?? null,
            $data['instagram'] ?? null,
            $publishImmediately ? TeamMember::STATUS_PUBLISHED : TeamMember::STATUS_HIDDEN,
            (int) ($data['sort_order'] ?? 0),
            (string) ($data['language'] ?? 'fa-IR'),
            (bool) ($data['is_featured'] ?? false)
        );

        return $this->repository->create($member);
    }

    public function updateByAdmin(int $id, array $data): bool
    {
        $member = $this->getByIdOrFail($id);

        if (isset($data['name']))         $member->setName((string) $data['name']);
        if (isset($data['position']))     $member->setPosition((string) $data['position']);
        if (isset($data['department']))   $member->setDepartment((string) $data['department']);
        if (isset($data['bio']))          $member->setBio((string) $data['bio']);
        if (array_key_exists('photo_url', $data))  $member->setPhotoUrl($data['photo_url']);
        if (array_key_exists('email', $data))      $member->setEmail($data['email']);
        if (isset($data['phone']))        $member->setPhone((string) $data['phone']);
        if (isset($data['linkedin']))     $member->setLinkedin((string) $data['linkedin']);
        if (isset($data['twitter']))      $member->setTwitter((string) $data['twitter']);
        if (isset($data['instagram']))    $member->setInstagram((string) $data['instagram']);
        if (isset($data['language']))     $member->setLanguage((string) $data['language']);
        if (isset($data['sort_order']))   $member->setSortOrder((int) $data['sort_order']);
        
        if (isset($data['is_featured']) && (bool) $data['is_featured'] !== $member->isFeatured()) {
            $member->toggleFeatured();
        }

        if (isset($data['status'])) {
            $member->setStatus((string) $data['status']);
        }

        return $this->repository->update($member);
    }

    public function publish(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, TeamMember::STATUS_PUBLISHED);
    }

    public function hide(int $id): bool
    {
        $this->getByIdOrFail($id);
        return $this->repository->updateStatus($id, TeamMember::STATUS_HIDDEN);
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

    // ═══════════════════════════════════════════════════════════
    // Bulk Operations
    // ═══════════════════════════════════════════════════════════

    public function bulkPublish(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, TeamMember::STATUS_PUBLISHED);
    }

    public function bulkHide(array $ids): int
    {
        return $this->repository->bulkUpdateStatus($ids, TeamMember::STATUS_HIDDEN);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->repository->bulkDelete($ids);
    }

    // ═══════════════════════════════════════════════════════════
    // Read Operations
    // ═══════════════════════════════════════════════════════════

    public function getById(int $id): ?TeamMember
    {
        return $this->repository->findById($id);
    }

    public function getByIdOrFail(int $id): TeamMember
    {
        $m = $this->repository->findById($id);
        if ($m === null) {
            throw TeamException::notFound($id);
        }
        return $m;
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
        return $this->repository->findPublished([
            'is_featured' => true,
            'limit' => $limit,
        ]);
    }

    public function getCount(array $filters = []): int
    {
        return $this->repository->count($filters);
    }

    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    // ═══════════════════════════════════════════════════════════
    // Format for Widget
    // ═══════════════════════════════════════════════════════════

    public function formatForWidget(TeamMember $m): array
    {
        return [
            'id'          => $m->getId() ?? 0,
            'name'        => $m->getName(),
            'position'    => $m->getPosition(),
            'department'  => $m->getDepartment(),
            'bio'         => $m->getBio(),
            'photo_url'   => $m->getPhotoUrl(),
            'email'       => $m->getEmail(),
            'phone'       => $m->getPhone(),
            'linkedin'    => $m->getLinkedin(),
            'twitter'     => $m->getTwitter(),
            'instagram'   => $m->getInstagram(),
            'is_featured' => $m->isFeatured(),
        ];
    }
}

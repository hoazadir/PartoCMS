<?php
/**
 * PartoCMS - Newsletter Subscriber Repository Interface
 *
 * Contract for subscriber persistence.
 * Implementations live in Infrastructure layer.
 *
 * @package PartoCMS\Newsletter\Domain\Repository
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Repository;

use PartoCMS\Newsletter\Domain\Entity\Subscriber;
use PartoCMS\Newsletter\Domain\ValueObject\Email;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;

interface SubscriberRepositoryInterface
{
    /**
     * Find one subscriber by its ID.
     */
    public function findById(int $id): ?Subscriber;

    /**
     * Find one subscriber by email address.
     */
    public function findByEmail(Email $email): ?Subscriber;

    /**
     * Find one subscriber by confirmation token.
     */
    public function findByToken(string $token): ?Subscriber;

    /**
     * Check if a subscriber with this email exists.
     */
    public function exists(Email $email): bool;

    /**
     * Insert new or update existing subscriber.
     * Returns the persisted entity (with ID assigned on insert).
     */
    public function save(Subscriber $subscriber): Subscriber;

    /**
     * Delete a subscriber.
     */
    public function delete(Subscriber $subscriber): void;

    /**
     * Count subscribers, optionally filtered by status.
     */
    public function count(?SubscriberStatus $status = null): int;

    /**
     * Get paginated list of subscribers.
     *
     * @param int   $page    1-based
     * @param int   $perPage
     * @param array $filters Supported keys: status, search
     * @return Subscriber[]
     */
    public function findPaginated(int $page, int $perPage, array $filters = []): array;

    /**
     * Find all active subscribers (for campaign sending).
     *
     * @return Subscriber[]
     */
    public function findAllActive(): array;
}

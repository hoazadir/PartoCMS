<?php
/**
 * PartoCMS - Newsletter Subscription Service
 *
 * Application service that orchestrates subscription use cases.
 * Pure domain logic — no HTTP, no DB coupling (uses repository interface).
 *
 * @package PartoCMS\Newsletter\Domain\Service
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Service;

use PartoCMS\Newsletter\Domain\Entity\Subscriber;
use PartoCMS\Newsletter\Domain\Repository\SubscriberRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\Email;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;
use PartoCMS\Newsletter\Support\TokenGenerator;

final class SubscriptionService
{
    private SubscriberRepositoryInterface $repo;
    private bool $doubleOptIn;

    public function __construct(
        SubscriberRepositoryInterface $repo,
        bool $doubleOptIn = false
    ) {
        $this->repo        = $repo;
        $this->doubleOptIn = $doubleOptIn;
    }

    /**
     * Subscribe an email to the newsletter.
     *
     * Idempotent: if already subscribed and active → returns existing.
     * If previously unsubscribed → reactivates (requires re-confirmation if double opt-in).
     *
     * @return array{success: bool, subscriber: ?Subscriber, message: string, needs_confirmation: bool}
     */
    public function subscribe(
        Email $email,
        ?string $name = null,
        ?string $source = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $existing = $this->repo->findByEmail($email);

        // ─── Case 1: already active ───
        if ($existing && $existing->status() === SubscriberStatus::ACTIVE) {
            return [
                'success'            => true,
                'subscriber'         => $existing,
                'message'            => 'already_subscribed',
                'needs_confirmation' => false,
            ];
        }

        // ─── Case 2: exists but unsubscribed/bounced → reactivate ───
        if ($existing) {
            $existing->rename($name);
            if ($this->doubleOptIn) {
                // Reset to pending for re-confirmation
                $existing = $this->resetForReconfirmation($existing);
                $this->repo->save($existing);
                return [
                    'success'            => true,
                    'subscriber'         => $existing,
                    'message'            => 'reconfirmation_required',
                    'needs_confirmation' => true,
                ];
            }
            $existing->confirm();
            $this->repo->save($existing);
            return [
                'success'            => true,
                'subscriber'         => $existing,
                'message'            => 'reactivated',
                'needs_confirmation' => false,
            ];
        }

        // ─── Case 3: brand new ───
        $token  = TokenGenerator::generate();
        $status = $this->doubleOptIn
            ? SubscriberStatus::PENDING
            : SubscriberStatus::ACTIVE;

        $subscriber = Subscriber::create(
            email: $email,
            token: $token,
            name: $name,
            status: $status,
            source: $source,
            ip: $ip,
            userAgent: $userAgent
        );

        if (!$this->doubleOptIn) {
            $subscriber->confirm();
        }

        $subscriber = $this->repo->save($subscriber);

        return [
            'success'            => true,
            'subscriber'         => $subscriber,
            'message'            => $this->doubleOptIn ? 'confirmation_sent' : 'subscribed',
            'needs_confirmation' => $this->doubleOptIn,
        ];
    }

    /**
     * Confirm subscription via token.
     */
    public function confirm(string $token): array
    {
        $subscriber = $this->repo->findByToken($token);
        if ($subscriber === null) {
            return ['success' => false, 'subscriber' => null, 'message' => 'invalid_token'];
        }

        if ($subscriber->status() === SubscriberStatus::ACTIVE) {
            return ['success' => true, 'subscriber' => $subscriber, 'message' => 'already_confirmed'];
        }

        $subscriber->confirm();
        $this->repo->save($subscriber);

        return ['success' => true, 'subscriber' => $subscriber, 'message' => 'confirmed'];
    }

    /**
     * Unsubscribe via token.
     */
    public function unsubscribe(string $token): array
    {
        $subscriber = $this->repo->findByToken($token);
        if ($subscriber === null) {
            return ['success' => false, 'subscriber' => null, 'message' => 'invalid_token'];
        }

        if ($subscriber->status() === SubscriberStatus::UNSUBSCRIBED) {
            return ['success' => true, 'subscriber' => $subscriber, 'message' => 'already_unsubscribed'];
        }

        $subscriber->unsubscribe();
        $this->repo->save($subscriber);

        return ['success' => true, 'subscriber' => $subscriber, 'message' => 'unsubscribed'];
    }

    /**
     * Unsubscribe directly by email (admin action).
     */
    public function unsubscribeByEmail(Email $email): array
    {
        $subscriber = $this->repo->findByEmail($email);
        if ($subscriber === null) {
            return ['success' => false, 'subscriber' => null, 'message' => 'not_found'];
        }
        if ($subscriber->status() === SubscriberStatus::UNSUBSCRIBED) {
            return ['success' => true, 'subscriber' => $subscriber, 'message' => 'already_unsubscribed'];
        }
        $subscriber->unsubscribe();
        $this->repo->save($subscriber);
        return ['success' => true, 'subscriber' => $subscriber, 'message' => 'unsubscribed'];
    }

    /**
     * Mark a subscriber as bounced (from SMTP webhook).
     */
    public function markBounced(Email $email): bool
    {
        $subscriber = $this->repo->findByEmail($email);
        if ($subscriber === null) {
            return false;
        }
        $subscriber->markAsBounced();
        $this->repo->save($subscriber);
        return true;
    }

    /**
     * Update subscriber profile (name only for now).
     */
    public function updateProfile(Email $email, ?string $name): bool
    {
        $subscriber = $this->repo->findByEmail($email);
        if ($subscriber === null) {
            return false;
        }
        $subscriber->rename($name);
        $this->repo->save($subscriber);
        return true;
    }

    /**
     * Get paginated subscribers (delegates to repo).
     *
     * @return array{items: Subscriber[], total: int, page: int, per_page: int, pages: int}
     */
    public function listSubscribers(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $items = $this->repo->findPaginated($page, $perPage, $filters);

        $statusFilter = null;
        if (!empty($filters['status']) && $filters['status'] instanceof SubscriberStatus) {
            $statusFilter = $filters['status'];
        }
        $total = $this->repo->count($statusFilter);
        $pages = (int) ceil($total / $perPage);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Statistics for dashboard.
     *
     * @return array{total: int, active: int, pending: int, unsubscribed: int, bounced: int}
     */
    public function getStats(): array
    {
        return [
            'total'        => $this->repo->count(),
            'active'       => $this->repo->count(SubscriberStatus::ACTIVE),
            'pending'      => $this->repo->count(SubscriberStatus::PENDING),
            'unsubscribed' => $this->repo->count(SubscriberStatus::UNSUBSCRIBED),
            'bounced'      => $this->repo->count(SubscriberStatus::BOUNCED),
        ];
    }

    /**
     * Reset existing subscriber for double opt-in re-confirmation.
     * NOTE: Subscriber entity lacks rotateToken() for now — this is a
     * minimal implementation. Will be refactored in phase 7.
     */
    private function resetForReconfirmation(Subscriber $subscriber): Subscriber
    {
        $subscriber->unsubscribe();
        return $subscriber;
    }
}

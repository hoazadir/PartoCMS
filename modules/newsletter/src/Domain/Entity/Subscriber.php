<?php
/**
 * PartoCMS - Newsletter Entity: Subscriber
 *
 * Represents a newsletter subscriber as a domain entity.
 * Encapsulates state and behavior — mutations go through methods.
 *
 * @package PartoCMS\Newsletter\Domain\Entity
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Entity;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\ValueObject\Email;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;

final class Subscriber
{
    private ?int $id;
    private Email $email;
    private ?string $name;
    private SubscriberStatus $status;
    private string $token;
    private ?DateTimeImmutable $confirmedAt;
    private ?DateTimeImmutable $unsubscribedAt;
    private ?string $source;
    private ?string $ip;
    private ?string $userAgent;
    private array $metadata;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ?int $id,
        Email $email,
        ?string $name,
        SubscriberStatus $status,
        string $token,
        ?DateTimeImmutable $confirmedAt,
        ?DateTimeImmutable $unsubscribedAt,
        ?string $source,
        ?string $ip,
        ?string $userAgent,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id             = $id;
        $this->email          = $email;
        $this->name           = $name;
        $this->status         = $status;
        $this->token          = $token;
        $this->confirmedAt    = $confirmedAt;
        $this->unsubscribedAt = $unsubscribedAt;
        $this->source         = $source;
        $this->ip             = $ip;
        $this->userAgent      = $userAgent;
        $this->metadata       = $metadata;
        $this->createdAt      = $createdAt;
        $this->updatedAt      = $updatedAt;
    }

    /**
     * Create a new subscriber (not yet persisted).
     */
    public static function create(
        Email $email,
        string $token,
        ?string $name = null,
        SubscriberStatus $status = SubscriberStatus::PENDING,
        ?string $source = null,
        ?string $ip = null,
        ?string $userAgent = null,
        array $metadata = []
    ): self {
        $now = new DateTimeImmutable();
        return new self(
            null,
            $email,
            $name,
            $status,
            $token,
            null,
            null,
            $source,
            $ip,
            $userAgent,
            $metadata,
            $now,
            $now
        );
    }

    /**
     * Reconstruct from DB row.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            Email::fromString($row['email']),
            $row['name'] ?? null,
            SubscriberStatus::from($row['status']),
            $row['token'],
            !empty($row['confirmed_at'])    ? new DateTimeImmutable($row['confirmed_at'])    : null,
            !empty($row['unsubscribed_at']) ? new DateTimeImmutable($row['unsubscribed_at']) : null,
            $row['source']    ?? null,
            $row['ip']        ?? null,
            $row['user_agent'] ?? null,
            !empty($row['metadata']) ? (json_decode($row['metadata'], true) ?: []) : [],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }

    // ─── Behavior ───

    public function confirm(): void
    {
        if ($this->status === SubscriberStatus::ACTIVE) {
            return; // idempotent
        }
        $this->status = SubscriberStatus::ACTIVE;
        $this->confirmedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function unsubscribe(): void
    {
        if ($this->status === SubscriberStatus::UNSUBSCRIBED) {
            return;
        }
        $this->status = SubscriberStatus::UNSUBSCRIBED;
        $this->unsubscribedAt = new DateTimeImmutable();
        $this->touch();
    }

    /**
     * 🆕 بازنشانی به حالت «در انتظار تأیید» (برای double opt-in مجدد)
     */
    public function resetToPending(): void
    {
        $this->status = SubscriberStatus::PENDING;
        $this->confirmedAt = null;
        $this->unsubscribedAt = null;
        $this->touch();
    }

    /**
     * 🆕 چرخش توکن (برای ارسال دوباره ایمیل تأیید)
     */
    public function rotateToken(string $newToken): void
    {
        $this->token = $newToken;
        $this->touch();
    }

    public function markAsBounced(): void
    {
        $this->status = SubscriberStatus::BOUNCED;
        $this->touch();
    }

    public function rename(?string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->touch();
    }

    // ─── Getters ───

    public function id(): ?int { return $this->id; }
    public function email(): Email { return $this->email; }
    public function name(): ?string { return $this->name; }
    public function status(): SubscriberStatus { return $this->status; }
    public function token(): string { return $this->token; }
    public function confirmedAt(): ?DateTimeImmutable { return $this->confirmedAt; }
    public function unsubscribedAt(): ?DateTimeImmutable { return $this->unsubscribedAt; }
    public function source(): ?string { return $this->source; }
    public function ip(): ?string { return $this->ip; }
    public function userAgent(): ?string { return $this->userAgent; }
    public function metadata(): array { return $this->metadata; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function isActive(): bool
    {
        return $this->status->canReceive();
    }

    public function isNew(): bool
    {
        return $this->id === null;
    }

    // ─── Helpers ───

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'email'           => $this->email->value(),
            'name'            => $this->name,
            'status'          => $this->status->value,
            'token'           => $this->token,
            'confirmed_at'    => $this->confirmedAt?->format('Y-m-d H:i:s'),
            'unsubscribed_at' => $this->unsubscribedAt?->format('Y-m-d H:i:s'),
            'source'          => $this->source,
            'ip'              => $this->ip,
            'user_agent'      => $this->userAgent,
            'metadata'        => $this->metadata ? json_encode($this->metadata, JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}

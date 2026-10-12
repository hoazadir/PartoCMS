<?php
/**
 * PartoCMS - Newsletter Entity: SendLog
 *
 * Represents a single send record (one campaign → one subscriber).
 * Tracks delivery lifecycle: queued → sent → opened → clicked.
 *
 * @package PartoCMS\Newsletter\Domain\Entity
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Entity;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\ValueObject\SendStatus;

final class SendLog
{
    private ?int $id;
    private int $campaignId;
    private int $subscriberId;
    private SendStatus $status;
    private ?string $messageId;
    private ?string $errorMessage;
    private ?DateTimeImmutable $sentAt;
    private ?DateTimeImmutable $openedAt;
    private ?DateTimeImmutable $clickedAt;
    private DateTimeImmutable $createdAt;

    private function __construct(
        ?int $id,
        int $campaignId,
        int $subscriberId,
        SendStatus $status,
        ?string $messageId,
        ?string $errorMessage,
        ?DateTimeImmutable $sentAt,
        ?DateTimeImmutable $openedAt,
        ?DateTimeImmutable $clickedAt,
        DateTimeImmutable $createdAt
    ) {
        $this->id           = $id;
        $this->campaignId   = $campaignId;
        $this->subscriberId = $subscriberId;
        $this->status       = $status;
        $this->messageId    = $messageId;
        $this->errorMessage = $errorMessage;
        $this->sentAt       = $sentAt;
        $this->openedAt     = $openedAt;
        $this->clickedAt    = $clickedAt;
        $this->createdAt    = $createdAt;
    }

    /**
     * Create a new log entry (queued).
     */
    public static function queue(int $campaignId, int $subscriberId): self
    {
        return new self(
            null,
            $campaignId,
            $subscriberId,
            SendStatus::QUEUED,
            null,
            null,
            null,
            null,
            null,
            new DateTimeImmutable()
        );
    }

    /**
     * Reconstruct from DB row.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['campaign_id'],
            (int) $row['subscriber_id'],
            SendStatus::from($row['status']),
            $row['message_id']   ?? null,
            $row['error_message'] ?? null,
            !empty($row['sent_at'])    ? new DateTimeImmutable($row['sent_at'])    : null,
            !empty($row['opened_at'])  ? new DateTimeImmutable($row['opened_at'])  : null,
            !empty($row['clicked_at']) ? new DateTimeImmutable($row['clicked_at']) : null,
            new DateTimeImmutable($row['created_at'])
        );
    }

    // ─── Behavior ───

    public function markAsSent(?string $messageId = null): void
    {
        if ($this->status !== SendStatus::QUEUED) {
            return; // idempotent
        }
        $this->status    = SendStatus::SENT;
        $this->messageId = $messageId;
        $this->sentAt    = new DateTimeImmutable();
    }

    public function markAsFailed(string $error): void
    {
        $this->status       = SendStatus::FAILED;
        $this->errorMessage = $error;
    }

    public function markAsBounced(): void
    {
        $this->status = SendStatus::BOUNCED;
    }

    /**
     * Record an open event (pixel tracking).
     * Idempotent — only first open is stored.
     */
    public function markAsOpened(): void
    {
        if ($this->openedAt !== null) {
            return;
        }
        $this->openedAt = new DateTimeImmutable();
        // Do NOT change status if already clicked
        if ($this->status === SendStatus::SENT) {
            $this->status = SendStatus::OPENED;
        }
    }

    /**
     * Record a click event.
     * Idempotent — only first click is stored.
     */
    public function markAsClicked(): void
    {
        if ($this->clickedAt !== null) {
            return;
        }
        $this->clickedAt = new DateTimeImmutable();
        $this->status    = SendStatus::CLICKED;
    }

    // ─── Getters ───

    public function id(): ?int { return $this->id; }
    public function campaignId(): int { return $this->campaignId; }
    public function subscriberId(): int { return $this->subscriberId; }
    public function status(): SendStatus { return $this->status; }
    public function messageId(): ?string { return $this->messageId; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    public function sentAt(): ?DateTimeImmutable { return $this->sentAt; }
    public function openedAt(): ?DateTimeImmutable { return $this->openedAt; }
    public function clickedAt(): ?DateTimeImmutable { return $this->clickedAt; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }

    public function isNew(): bool { return $this->id === null; }

    public function isDelivered(): bool
    {
        return in_array($this->status, [SendStatus::SENT, SendStatus::OPENED, SendStatus::CLICKED], true);
    }

    public function isOpened(): bool
    {
        return $this->openedAt !== null;
    }

    public function isClicked(): bool
    {
        return $this->clickedAt !== null;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'campaign_id'   => $this->campaignId,
            'subscriber_id' => $this->subscriberId,
            'status'        => $this->status->value,
            'message_id'    => $this->messageId,
            'error_message' => $this->errorMessage,
            'sent_at'       => $this->sentAt?->format('Y-m-d H:i:s'),
            'opened_at'     => $this->openedAt?->format('Y-m-d H:i:s'),
            'clicked_at'    => $this->clickedAt?->format('Y-m-d H:i:s'),
            'created_at'    => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}

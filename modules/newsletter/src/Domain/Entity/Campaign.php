<?php
/**
 * PartoCMS - Newsletter Entity: Campaign
 *
 * Represents a newsletter campaign as a domain entity.
 * Encapsulates state machine transitions and statistics.
 *
 * @package PartoCMS\Newsletter\Domain\Entity
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Entity;

use DateTimeImmutable;
use PartoCMS\Newsletter\Domain\Exception\CampaignException;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

final class Campaign
{
    private ?int $id;
    private string $title;
    private string $subject;
    private ?string $preheader;
    private string $bodyHtml;
    private ?string $bodyText;
    private CampaignStatus $status;
    private ?DateTimeImmutable $scheduledAt;
    private ?DateTimeImmutable $startedAt;
    private ?DateTimeImmutable $completedAt;
    private int $sentCount;
    private int $failedCount;
    private int $openCount;
    private int $clickCount;
    private ?int $createdBy;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ?int $id,
        string $title,
        string $subject,
        ?string $preheader,
        string $bodyHtml,
        ?string $bodyText,
        CampaignStatus $status,
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        int $sentCount,
        int $failedCount,
        int $openCount,
        int $clickCount,
        ?int $createdBy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id           = $id;
        $this->title        = $title;
        $this->subject      = $subject;
        $this->preheader    = $preheader;
        $this->bodyHtml     = $bodyHtml;
        $this->bodyText     = $bodyText;
        $this->status       = $status;
        $this->scheduledAt  = $scheduledAt;
        $this->startedAt    = $startedAt;
        $this->completedAt  = $completedAt;
        $this->sentCount    = $sentCount;
        $this->failedCount  = $failedCount;
        $this->openCount    = $openCount;
        $this->clickCount   = $clickCount;
        $this->createdBy    = $createdBy;
        $this->createdAt    = $createdAt;
        $this->updatedAt    = $updatedAt;
    }

    /**
     * Create a new campaign (not yet persisted).
     */
    public static function create(
        string $title,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $preheader = null,
        ?int $createdBy = null
    ): self {
        $title   = trim($title);
        $subject = trim($subject);

        if ($title === '') {
            throw CampaignException::emptyTitle();
        }
        if ($subject === '') {
            throw CampaignException::emptySubject();
        }
        if (trim($bodyHtml) === '') {
            throw CampaignException::emptyBody();
        }

        $now = new DateTimeImmutable();
        return new self(
            null,
            $title,
            $subject,
            $preheader,
            $bodyHtml,
            $bodyText,
            CampaignStatus::DRAFT,
            null,
            null,
            null,
            0,
            0,
            0,
            0,
            $createdBy,
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
            $row['title'],
            $row['subject'],
            $row['preheader'] ?? null,
            $row['body_html'],
            $row['body_text'] ?? null,
            CampaignStatus::from($row['status']),
            !empty($row['scheduled_at'])  ? new DateTimeImmutable($row['scheduled_at'])  : null,
            !empty($row['started_at'])    ? new DateTimeImmutable($row['started_at'])    : null,
            !empty($row['completed_at'])  ? new DateTimeImmutable($row['completed_at'])  : null,
            (int) ($row['sent_count']    ?? 0),
            (int) ($row['failed_count']  ?? 0),
            (int) ($row['open_count']    ?? 0),
            (int) ($row['click_count']   ?? 0),
            !empty($row['created_by']) ? (int) $row['created_by'] : null,
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }

    // ─── Behavior: content ───

    public function updateContent(string $title, string $subject, string $bodyHtml, ?string $bodyText = null): void
    {
        if (!$this->status->canEdit()) {
            throw CampaignException::cannotEdit($this->status);
        }
        if (trim($title) === '')   throw CampaignException::emptyTitle();
        if (trim($subject) === '') throw CampaignException::emptySubject();
        if (trim($bodyHtml) === '') throw CampaignException::emptyBody();

        $this->title    = trim($title);
        $this->subject  = trim($subject);
        $this->bodyHtml = $bodyHtml;
        $this->bodyText = $bodyText;
        $this->touch();
    }

    public function setPreheader(?string $preheader): void
    {
        if (!$this->status->canEdit()) {
            throw CampaignException::cannotEdit($this->status);
        }
        $this->preheader = $preheader;
        $this->touch();
    }

    // ─── Behavior: scheduling ───

    public function schedule(DateTimeImmutable $when): void
    {
        if (!$this->status->canTransitionTo(CampaignStatus::SCHEDULED)) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::SCHEDULED);
        }
        if ($when <= new DateTimeImmutable()) {
            throw CampaignException::scheduleInPast();
        }
        $this->status      = CampaignStatus::SCHEDULED;
        $this->scheduledAt = $when;
        $this->touch();
    }

    public function unschedule(): void
    {
        if ($this->status !== CampaignStatus::SCHEDULED) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::DRAFT);
        }
        $this->status      = CampaignStatus::DRAFT;
        $this->scheduledAt = null;
        $this->touch();
    }

    // ─── Behavior: sending lifecycle ───

    public function startSending(): void
    {
        if (!$this->status->canTransitionTo(CampaignStatus::SENDING)) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::SENDING);
        }
        $this->status    = CampaignStatus::SENDING;
        $this->startedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function pause(): void
    {
        if ($this->status !== CampaignStatus::SENDING) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::PAUSED);
        }
        $this->status = CampaignStatus::PAUSED;
        $this->touch();
    }

    public function resume(): void
    {
        if ($this->status !== CampaignStatus::PAUSED) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::SENDING);
        }
        $this->status = CampaignStatus::SENDING;
        $this->touch();
    }

    public function complete(): void
    {
        if ($this->status !== CampaignStatus::SENDING) {
            throw CampaignException::invalidTransition($this->status, CampaignStatus::SENT);
        }
        $this->status      = CampaignStatus::SENT;
        $this->completedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function fail(string $reason = ''): void
    {
        if ($this->status->isTerminal()) {
            return;
        }
        $this->status      = CampaignStatus::FAILED;
        $this->completedAt = new DateTimeImmutable();
        $this->touch();
    }

    // ─── Behavior: statistics ───

    public function incrementSent(int $by = 1): void
    {
        $this->sentCount += $by;
        $this->touch();
    }

    public function incrementFailed(int $by = 1): void
    {
        $this->failedCount += $by;
        $this->touch();
    }

    public function incrementOpen(): void
    {
        $this->openCount++;
        $this->touch();
    }

    public function incrementClick(): void
    {
        $this->clickCount++;
        $this->touch();
    }

    // ─── Getters ───

    public function id(): ?int { return $this->id; }
    public function title(): string { return $this->title; }
    public function subject(): string { return $this->subject; }
    public function preheader(): ?string { return $this->preheader; }
    public function bodyHtml(): string { return $this->bodyHtml; }
    public function bodyText(): ?string { return $this->bodyText; }
    public function status(): CampaignStatus { return $this->status; }
    public function scheduledAt(): ?DateTimeImmutable { return $this->scheduledAt; }
    public function startedAt(): ?DateTimeImmutable { return $this->startedAt; }
    public function completedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function sentCount(): int { return $this->sentCount; }
    public function failedCount(): int { return $this->failedCount; }
    public function openCount(): int { return $this->openCount; }
    public function clickCount(): int { return $this->clickCount; }
    public function createdBy(): ?int { return $this->createdBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function isNew(): bool { return $this->id === null; }

    /**
     * Open rate (as percentage).
     */
    public function openRate(): float
    {
        return $this->sentCount > 0 ? round(($this->openCount / $this->sentCount) * 100, 2) : 0.0;
    }

    /**
     * Click rate (as percentage).
     */
    public function clickRate(): float
    {
        return $this->sentCount > 0 ? round(($this->clickCount / $this->sentCount) * 100, 2) : 0.0;
    }

    // ─── Helpers ───

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'subject'      => $this->subject,
            'preheader'    => $this->preheader,
            'body_html'    => $this->bodyHtml,
            'body_text'    => $this->bodyText,
            'status'       => $this->status->value,
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'started_at'   => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'sent_count'   => $this->sentCount,
            'failed_count' => $this->failedCount,
            'open_count'   => $this->openCount,
            'click_count'  => $this->clickCount,
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}

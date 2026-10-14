<?php
/**
 * PartoCMS - Newsletter Campaign Status
 *
 * Enum representing the lifecycle of a newsletter campaign.
 * Uses PHP 8.1+ backed enum with helper methods.
 *
 * @package PartoCMS\Newsletter\Domain\ValueObject
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\ValueObject;

enum CampaignStatus: string
{
    case DRAFT     = 'draft';      // being composed
    case SCHEDULED = 'scheduled';  // will send at scheduled_at
    case SENDING   = 'sending';    // in progress
    case SENT      = 'sent';       // completed successfully
    case PAUSED    = 'paused';     // manually paused during send
    case FAILED    = 'failed';     // send aborted due to errors

    /**
     * Human-readable label (fa/en based on current locale).
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT     => 'پیش‌نویس',
            self::SCHEDULED => 'زمان‌بندی شده',
            self::SENDING   => 'در حال ارسال',
            self::SENT      => 'ارسال شده',
            self::PAUSED    => 'متوقف شده',
            self::FAILED    => 'ناموفق',
        };
    }

    /**
     * UI badge color (bootstrap context).
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT     => 'secondary',
            self::SCHEDULED => 'info',
            self::SENDING   => 'warning',
            self::SENT      => 'success',
            self::PAUSED    => 'dark',
            self::FAILED    => 'danger',
        };
    }

    /**
     * Can this campaign be edited?
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED], true);
    }

    /**
     * Can this campaign be sent?
     */
    public function canSend(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED], true);
    }

    /**
     * Is sending in progress?
     */
    public function isSending(): bool
    {
        return $this === self::SENDING;
    }

    /**
     * Is terminal? (no further transitions possible)
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED], true);
    }

    /**
     * Valid state transitions from current status.
     *
     * @return CampaignStatus[]
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::DRAFT     => [self::SCHEDULED, self::SENDING, self::FAILED],
            self::SCHEDULED => [self::DRAFT, self::SENDING, self::FAILED],
            self::SENDING   => [self::SENT, self::PAUSED, self::FAILED],
            self::PAUSED    => [self::SENDING, self::FAILED, self::DRAFT],
            self::SENT      => [],  // terminal
            self::FAILED    => [],  // terminal
        };
    }

    /**
     * Can transition to another status?
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * All values as array.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }

    /**
     * Statuses that are still in progress (not terminal).
     *
     * @return CampaignStatus[]
     */
    public static function inProgress(): array
    {
        return [self::DRAFT, self::SCHEDULED, self::SENDING, self::PAUSED];
    }

    /**
     * Try from string, null on failure.
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}

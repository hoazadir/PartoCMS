<?php
/**
 * PartoCMS - Newsletter Send Status
 *
 * Enum representing the lifecycle of a single send record.
 *
 * @package PartoCMS\Newsletter\Domain\ValueObject
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\ValueObject;

enum SendStatus: string
{
    case QUEUED   = 'queued';    // waiting in queue
    case SENT     = 'sent';      // delivered to SMTP
    case OPENED   = 'opened';    // recipient opened email
    case CLICKED  = 'clicked';   // recipient clicked link
    case FAILED   = 'failed';    // SMTP error
    case BOUNCED  = 'bounced';   // invalid address / hard bounce

    public function label(): string
    {
        $isFa = function_exists('getI18n')
            && method_exists(getI18n(), 'getCurrentLang')
            && str_starts_with(getI18n()->getCurrentLang(), 'fa');

        return $isFa
            ? match($this) {
                self::QUEUED  => 'در صف',
                self::SENT    => 'ارسال شده',
                self::OPENED  => 'باز شده',
                self::CLICKED => 'کلیک شده',
                self::FAILED  => 'ناموفق',
                self::BOUNCED => 'برگشتی',
            }
            : match($this) {
                self::QUEUED  => 'Queued',
                self::SENT    => 'Sent',
                self::OPENED  => 'Opened',
                self::CLICKED => 'Clicked',
                self::FAILED  => 'Failed',
                self::BOUNCED => 'Bounced',
            };
    }

    public function color(): string
    {
        return match($this) {
            self::QUEUED  => 'secondary',
            self::SENT    => 'info',
            self::OPENED  => 'primary',
            self::CLICKED => 'success',
            self::FAILED  => 'danger',
            self::BOUNCED => 'warning',
        };
    }

    public function isDelivered(): bool
    {
        return in_array($this, [self::SENT, self::OPENED, self::CLICKED], true);
    }

    public function isEngaged(): bool
    {
        return in_array($this, [self::OPENED, self::CLICKED], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CLICKED, self::FAILED, self::BOUNCED], true);
    }

    /** @return string[] */
    public static function all(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}

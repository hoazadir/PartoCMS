<?php
/**
 * PartoCMS - Newsletter Subscriber Status
 *
 * Enum representing the lifecycle status of a newsletter subscriber.
 * Uses PHP 8.1+ backed enum with helper methods.
 *
 * @package PartoCMS\Newsletter\Domain\ValueObject
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\ValueObject;

enum SubscriberStatus: string
{
    case PENDING      = 'pending';       // awaiting confirmation (double opt-in)
    case ACTIVE       = 'active';        // confirmed and subscribed
    case UNSUBSCRIBED = 'unsubscribed';  // user opted out
    case BOUNCED      = 'bounced';       // email bounced (invalid address)

    /**
     * Human-readable label (fa/en based on current locale).
     */
    public function label(): string
    {
        $isFa = function_exists('getI18n')
            && method_exists(getI18n(), 'getCurrentLang')
            && str_starts_with(getI18n()->getCurrentLang(), 'fa');

        return $isFa
            ? match($this) {
                self::PENDING      => 'در انتظار تأیید',
                self::ACTIVE       => 'فعال',
                self::UNSUBSCRIBED => 'لغو شده',
                self::BOUNCED      => 'برگشتی',
            }
            : match($this) {
                self::PENDING      => 'Pending',
                self::ACTIVE       => 'Active',
                self::UNSUBSCRIBED => 'Unsubscribed',
                self::BOUNCED      => 'Bounced',
            };
    }

    /**
     * UI badge color (bootstrap context).
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING      => 'warning',
            self::ACTIVE       => 'success',
            self::UNSUBSCRIBED => 'secondary',
            self::BOUNCED      => 'danger',
        };
    }

    /**
     * Can receive emails?
     */
    public function canReceive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Is terminal? (cannot transition further)
     */
    public function isTerminal(): bool
    {
        return $this === self::BOUNCED;
    }

    /**
     * All values as array (for DB enums, form selects).
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_map(fn(self $c) => $c->value, self::cases());
    }

    /**
     * Try from string, null on failure.
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}

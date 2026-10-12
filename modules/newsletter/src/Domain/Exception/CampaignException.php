<?php
/**
 * PartoCMS - Newsletter Campaign Exception
 *
 * Thrown for invalid campaign operations (state machine, validation).
 *
 * @package PartoCMS\Newsletter\Domain\Exception
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Exception;

use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

final class CampaignException extends NewsletterException
{
    public static function emptyTitle(): self
    {
        return new self('Campaign title cannot be empty.');
    }

    public static function emptySubject(): self
    {
        return new self('Campaign subject cannot be empty.');
    }

    public static function emptyBody(): self
    {
        return new self('Campaign body cannot be empty.');
    }

    public static function cannotEdit(CampaignStatus $current): self
    {
        return new self(
            sprintf('Campaign in status "%s" cannot be edited.', $current->value)
        );
    }

    public static function invalidTransition(CampaignStatus $from, CampaignStatus $to): self
    {
        return new self(
            sprintf(
                'Invalid campaign status transition: "%s" → "%s".',
                $from->value,
                $to->value
            )
        );
    }

    public static function scheduleInPast(): self
    {
        return new self('Cannot schedule campaign in the past.');
    }

    public static function notFound(int $id): self
    {
        return new self(sprintf('Campaign #%d not found.', $id));
    }

    public static function cannotSend(CampaignStatus $current): self
    {
        return new self(
            sprintf('Campaign in status "%s" cannot be sent.', $current->value)
        );
    }
}

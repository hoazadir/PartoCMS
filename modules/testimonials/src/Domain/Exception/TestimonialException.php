<?php
/**
 * PartoCMS — Testimonials Module
 * Domain Exception: TestimonialException
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Domain\Exception;

use RuntimeException;
use Throwable;

class TestimonialException extends RuntimeException
{
    // ═══════════════════════════════════════════════════════════
    // Factory Methods — برای ساخت Exception با پیام مشخص
    // ═══════════════════════════════════════════════════════════

    public static function notFound(int $id, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Testimonial with ID %d not found', $id),
            404,
            $previous
        );
    }

    public static function invalidStatus(string $status): self
    {
        return new self(
            sprintf('Invalid status: %s', $status),
            400
        );
    }

    public static function invalidRating(?int $rating): self
    {
        return new self(
            sprintf('Rating must be between %d and %d, got: %s', 1, 5, $rating ?? 'null'),
            400
        );
    }

    public static function cannotPublish(string $reason): self
    {
        return new self(
            sprintf('Cannot publish testimonial: %s', $reason),
            422
        );
    }

    public static function alreadyPublished(int $id): self
    {
        return new self(
            sprintf('Testimonial %d is already published', $id),
            409
        );
    }

    public static function databaseError(string $operation, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Database error during: %s', $operation),
            500,
            $previous
        );
    }

    public static function invalidInput(string $field, string $reason): self
    {
        return new self(
            sprintf('Invalid input for field "%s": %s', $field, $reason),
            400
        );
    }
}

<?php
/**
 * PartoCMS — Portfolio Module
 * Domain Exception: PortfolioException
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Domain\Exception;

use RuntimeException;
use Throwable;

class PortfolioException extends RuntimeException
{
    public static function notFound(int $id, ?Throwable $previous = null): self
    {
        return new self(sprintf('Portfolio item with ID %d not found', $id), 404, $previous);
    }

    public static function invalidStatus(string $status): self
    {
        return new self(sprintf('Invalid status: %s', $status), 400);
    }

    public static function databaseError(string $operation, ?Throwable $previous = null): self
    {
        return new self(sprintf('Database error during: %s', $operation), 500, $previous);
    }

    public static function invalidInput(string $field, string $reason): self
    {
        return new self(sprintf('Invalid input for field "%s": %s', $field, $reason), 400);
    }
}

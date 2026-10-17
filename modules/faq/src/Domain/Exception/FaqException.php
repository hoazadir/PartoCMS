<?php
/**
 * PartoCMS - FAQ Exception
 *
 * @package PartoCMS\Faq\Domain\Exception
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Domain\Exception;

use RuntimeException;

final class FaqException extends RuntimeException
{
    public static function notFound(int $id): self
    {
        return new self("سوال متداول با شناسه {$id} یافت نشد.");
    }
}

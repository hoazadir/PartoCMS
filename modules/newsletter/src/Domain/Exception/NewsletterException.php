<?php
/**
 * PartoCMS - Newsletter Base Exception
 *
 * Base class for all newsletter module exceptions.
 * Allows catching all module exceptions with a single catch clause.
 *
 * @package PartoCMS\Newsletter\Domain\Exception
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Exception;

use RuntimeException;

class NewsletterException extends RuntimeException
{
}

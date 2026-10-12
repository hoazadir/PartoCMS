<?php
/**
 * PartoCMS - Newsletter Value Object: Email
 *
 * Immutable value object representing a valid email address.
 * Handles validation, normalization, and access to parts.
 *
 * @package PartoCMS\Newsletter\Domain\ValueObject
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\ValueObject;

use InvalidArgumentException;
use PartoCMS\Newsletter\Domain\Exception\InvalidEmailException;

final class Email
{
    private const MAX_LENGTH = 254;

    private string $value;
    private string $localPart;
    private string $domain;

    private function __construct(string $value, string $localPart, string $domain)
    {
        $this->value     = $value;
        $this->localPart = $localPart;
        $this->domain    = $domain;
    }

    /**
     * Create an Email from a raw string.
     *
     * @throws InvalidEmailException
     */
    public static function fromString(string $input): self
    {
        // Normalize: trim + lowercase
        $normalized = mb_strtolower(trim($input));

        if ($normalized === '') {
            throw InvalidEmailException::empty();
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw InvalidEmailException::tooLong($normalized);
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmailException::invalidFormat($normalized);
        }

        // Split into local + domain
        $atPos = strrpos($normalized, '@');
        if ($atPos === false) {
            throw InvalidEmailException::invalidFormat($normalized);
        }

        $local  = substr($normalized, 0, $atPos);
        $domain = substr($normalized, $atPos + 1);

        // Extra checks
        if ($local === '' || $domain === '') {
            throw InvalidEmailException::invalidFormat($normalized);
        }

        if (strpos($domain, '.') === false) {
            throw InvalidEmailException::missingTld($normalized);
        }

        return new self($normalized, $local, $domain);
    }

    /**
     * Try to create — returns null on failure (no exception).
     */
    public static function tryFromString(string $input): ?self
    {
        try {
            return self::fromString($input);
        } catch (InvalidEmailException) {
            return null;
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function localPart(): string
    {
        return $this->localPart;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

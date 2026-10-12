<?php
/**
 * PartoCMS - Invalid Email Exception
 *
 * Thrown when an email address fails validation.
 *
 * @package PartoCMS\Newsletter\Domain\Exception
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Domain\Exception;

final class InvalidEmailException extends NewsletterException
{
    private string $email;

    private function __construct(string $email, string $message)
    {
        parent::__construct($message);
        $this->email = $email;
    }

    public static function empty(): self
    {
        return new self('', 'Email address cannot be empty.');
    }

    public static function tooLong(string $email): self
    {
        return new self(
            $email,
            sprintf('Email address exceeds maximum length (254). Given: %d chars.', strlen($email))
        );
    }

    public static function invalidFormat(string $email): self
    {
        return new self(
            $email,
            sprintf('Email address has invalid format: "%s".', $email)
        );
    }

    public static function missingTld(string $email): self
    {
        return new self(
            $email,
            sprintf('Email domain missing top-level domain: "%s".', $email)
        );
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

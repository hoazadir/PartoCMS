<?php
/**
 * PartoCMS - Newsletter Token Generator
 *
 * Generates cryptographically secure random tokens.
 * Used for confirmation, unsubscribe, and tracking links.
 *
 * @package PartoCMS\Newsletter\Support
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Support;

final class TokenGenerator
{
    private const DEFAULT_BYTES = 32;   // 32 bytes = 64 hex chars

    /**
     * Generate a hex token of N bytes.
     * Default = 32 bytes (64 chars) — collision-resistant.
     */
    public static function generate(int $bytes = self::DEFAULT_BYTES): string
    {
        if ($bytes < 16) {
            throw new \InvalidArgumentException('Token must be at least 16 bytes.');
        }
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Generate URL-safe base64 token.
     */
    public static function generateUrlSafe(int $bytes = self::DEFAULT_BYTES): string
    {
        if ($bytes < 16) {
            throw new \InvalidArgumentException('Token must be at least 16 bytes.');
        }
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * Constant-time comparison (prevents timing attacks).
     */
    public static function equals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * Generate a short numeric code (for OTP-like verification).
     */
    public static function generateNumeric(int $length = 6): string
    {
        if ($length < 4 || $length > 10) {
            throw new \InvalidArgumentException('Numeric code length must be 4-10.');
        }
        $max = (int) str_repeat('9', $length);
        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}

<?php
/**
 * PartoCMS - TOTP (Time-based One-Time Password) - RFC 6238
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator
 */

class TOTP {

    const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const PERIOD = 30;
    const DIGITS = 6;

    public static function generateSecret($length = 32) {
        $chars = self::BASE32_CHARS;
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    private static function base32Decode($b32) {
        $b32 = strtoupper($b32);
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        if (empty($b32)) return '';

        $map = array_flip(str_split(self::BASE32_CHARS));
        $binary = '';
        foreach (str_split($b32) as $c) {
            if (!isset($map[$c])) continue;
            $binary .= str_pad(decbin($map[$c]), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        return $bytes;
    }

    private static function hotp($secret, $counter) {
        $key = self::base32Decode($secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function getCode($secret, $timestamp = null) {
        if ($timestamp === null) $timestamp = time();
        $counter = (int) floor($timestamp / self::PERIOD);
        return self::hotp($secret, $counter);
    }

    public static function verify($secret, $code, $discrepancy = 1) {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;

        $timestamp = time();
        $counter = (int) floor($timestamp / self::PERIOD);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if (hash_equals(self::hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getOtpAuthUri($secret, $accountName, $issuer = 'PartoCMS') {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    public static function getQrCodeUrl($otpUri, $size = 200) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . urlencode($otpUri);
    }

    public static function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }
        return $codes;
    }
}

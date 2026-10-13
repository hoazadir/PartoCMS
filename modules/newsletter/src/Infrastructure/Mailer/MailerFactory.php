<?php
/**
 * PartoCMS - Newsletter Mailer Factory
 *
 * Builds a MailerInterface from persisted settings.
 * Falls back to site-wide admin_email when newsletter settings absent.
 *
 * @package PartoCMS\Newsletter\Infrastructure\Mailer
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Mailer;

use PDO;

final class MailerFactory
{
    /** @var array<string, mixed> */
    private array $settings;

    private function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Build from DB settings table (key = newsletter_smtp_*).
     */
    public static function fromDatabase(PDO $pdo): self
    {
        $stmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM settings
            WHERE setting_key LIKE 'newsletter_smtp_%'
               OR setting_key IN ('admin_email', 'site_name')
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return new self($rows);
    }

    /**
     * Build from explicit array (for tests).
     */
    public static function fromArray(array $settings): self
    {
        return new self($settings);
    }

    /**
     * Create a MailerInterface instance.
     */
    public function build(): MailerInterface
    {
        $s = $this->settings;

        $host       = (string) ($s['newsletter_smtp_host']       ?? '');
        $port       = (int)    ($s['newsletter_smtp_port']       ?? 587);
        $username   = (string) ($s['newsletter_smtp_username']   ?? '');
        $password   = (string) ($s['newsletter_smtp_password']   ?? '');
        $encryption = (string) ($s['newsletter_smtp_encryption'] ?? 'tls');
        $fromEmail  = (string) ($s['newsletter_from_email']      ?? ($s['admin_email'] ?? ''));
        $fromName   = (string) ($s['newsletter_from_name']       ?? ($s['site_name']   ?? 'PartoCMS'));
        $replyTo    = (string) ($s['newsletter_reply_to']        ?? '');
        $timeout    = (int)    ($s['newsletter_smtp_timeout']    ?? 30);
        $debug      = !empty($s['newsletter_smtp_debug']);

        return new SmtpMailer(
            host:       $host,
            port:       $port,
            username:   $username,
            password:   $password,
            encryption: $encryption,
            fromEmail:  $fromEmail,
            fromName:   $fromName,
            replyTo:    $replyTo,
            timeout:    $timeout,
            debug:      $debug
        );
    }
}

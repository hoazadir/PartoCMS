<?php
/**
 * PartoCMS - Newsletter Mailer Interface
 *
 * Abstraction over email sending. Implementations can use
 * SMTP, PHPMailer, or the site's existing mail infrastructure.
 *
 * @package PartoCMS\Newsletter\Infrastructure\Mailer
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Mailer;

interface MailerInterface
{
    /**
     * Send an email.
     *
     * @param string      $to
     * @param string|null $toName
     * @param string      $subject
     * @param string      $bodyHtml
     * @param string|null $bodyText
     * @param string|null $preheader
     * @return string Message-ID on success
     * @throws \RuntimeException on failure
     */
    public function send(
        string $to,
        ?string $toName,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $preheader = null
    ): string;
}

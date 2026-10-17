<?php
/**
 * PartoCMS - Newsletter SMTP Mailer
 *
 * Implements MailerInterface using PHPMailer.
 * Configurable via constructor; typically built from settings table.
 *
 * @package PartoCMS\Newsletter\Infrastructure\Mailer
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RuntimeException;

final class SmtpMailer implements MailerInterface
{
    public function __construct(
        private string $host,
        private int    $port         = 587,
        private string $username     = '',
        private string $password     = '',
        private string $encryption   = 'tls',    // 'tls' | 'ssl' | 'none'
        private string $fromEmail    = '',
        private string $fromName     = '',
        private string $replyTo      = '',
        private int    $timeout      = 30,
        private bool   $debug        = false
    ) {}

    private ?PHPMailer $mailer = null;

    private function getMailer(): PHPMailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        $mail = new PHPMailer(true);

        // ─── SMTP config ───
        $mail->isSMTP();
        $mail->Host    = $this->host;
        $mail->Port    = $this->port;
        $mail->Timeout = $this->timeout;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPKeepAlive = true; // reuse single connection across sends

        if ($this->debug) {
            $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'error_log';
        }

        // ─── Encryption ───
        $enc = strtolower($this->encryption);
        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }

        // ─── Auth ───
        if ($this->username !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
        } else {
            $mail->SMTPAuth = false;
        }

        // ─── From / Reply-To ───
        $fromEmail = $this->fromEmail !== '' ? $this->fromEmail : $this->username;
        if ($fromEmail === '') {
            throw new RuntimeException('SmtpMailer: fromEmail is required.');
        }
        $mail->setFrom($fromEmail, $this->fromName);
        if ($this->replyTo !== '') {
            $mail->addReplyTo($this->replyTo);
        }

        $this->mailer = $mail;
        return $this->mailer;
    }

    public function close(): void
    {
        if ($this->mailer !== null) {
            $this->mailer->smtpClose();
            $this->mailer = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function send(
        string $to,
        ?string $toName,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?string $preheader = null
    ): string {
        $mail = $this->getMailer();

        try {
            // ─── Recipient ───
            $mail->clearAddresses();
            $mail->addAddress($to, (string) $toName);

            // ─── Content ───
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $this->injectPreheader($bodyHtml, $preheader);
            $mail->AltBody = $bodyText ?? $this->htmlToText($bodyHtml);

            $mail->send();
            $messageId = $mail->getLastMessageID();

            return $messageId !== '' ? $messageId : ('<' . uniqid('nl-', true) . '@' . $this->host . '>');
        } catch (PHPMailerException $e) {
            $this->close();
            throw new RuntimeException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Inject hidden preheader text (inbox preview) at top of HTML.
     */
    private function injectPreheader(string $html, ?string $preheader): string
    {
        if ($preheader === null || trim($preheader) === '') {
            return $html;
        }
        $safe = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
        $hidden = '<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">'
                . $safe
                . '</div>';
        return $hidden . $html;
    }

    /**
     * Basic HTML → plain text (fallback for AltBody).
     */
    private function htmlToText(string $html): string
    {
        $text = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html);
        $text = preg_replace('#<br\s*/?>#i', "\n", (string) $text);
        $text = preg_replace('#</p>#i', "\n\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }
}

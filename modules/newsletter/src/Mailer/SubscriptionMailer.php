<?php
/**
 * PartoCMS - Newsletter Subscription Mailer
 *
 * ارسال ایمیل‌های lifecycle مربوط به عضویت:
 *   - تأیید عضویت (double opt-in)
 *   - خوش‌آمد پس از تأیید
 *   - تأیید لغو عضویت
 *
 * از MailerInterface استفاده می‌کند (abstraction)
 *
 * @package PartoCMS\Newsletter\Mailer
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Mailer;

use PartoCMS\Newsletter\Domain\Entity\Subscriber;
use PartoCMS\Newsletter\Infrastructure\Mailer\MailerInterface;
use Throwable;

final class SubscriptionMailer
{
    private MailerInterface $mailer;
    private string $templatesPath;
    private string $siteName;
    private string $siteUrl;

    public function __construct(
        MailerInterface $mailer,
        ?string $templatesPath = null,
        ?string $siteName = null,
        ?string $siteUrl = null
    ) {
        $this->mailer        = $mailer;
        $this->templatesPath = $templatesPath
            ?? dirname(__DIR__, 2) . '/templates/email';
        $this->siteName      = $siteName ?? (function_exists('getSetting') ? getSetting('site_name', 'PartoCMS') : 'PartoCMS');
        $this->siteUrl       = $siteUrl  ?? (defined('SITE_URL') ? SITE_URL : '');
    }

    /**
     * ارسال ایمیل تأیید عضویت (double opt-in)
     */
    public function sendConfirmation(Subscriber $subscriber): ?string
    {
        $confirmUrl = $this->siteUrl . '/modules/newsletter/ajax/confirm.php?token=' . urlencode($subscriber->token());
        $unsubUrl   = $this->siteUrl . '/modules/newsletter/ajax/unsubscribe.php?token=' . urlencode($subscriber->token());

        $body = $this->render('confirm.php', [
            'subscriber' => $this->subscriberToArray($subscriber),
            'confirmUrl' => $confirmUrl,
            'unsubUrl'   => $unsubUrl,
        ]);

        $subject = '✅ تأیید عضویت در خبرنامه ' . $this->siteName;

        return $this->send($subscriber, $subject, $body, 'تأیید عضویت در خبرنامه');
    }

    /**
     * ارسال ایمیل خوش‌آمد بعد از تأیید
     */
    public function sendWelcome(Subscriber $subscriber): ?string
    {
        $unsubUrl = $this->siteUrl . '/modules/newsletter/ajax/unsubscribe.php?token=' . urlencode($subscriber->token());

        $body = $this->render('welcome.php', [
            'subscriber' => $this->subscriberToArray($subscriber),
            'unsubUrl'   => $unsubUrl,
        ]);

        $subject = '🎉 به خبرنامه ' . $this->siteName . ' خوش آمدید';

        return $this->send($subscriber, $subject, $body, 'خوش‌آمدگویی');
    }

    /**
     * ارسال تأیید لغو عضویت
     */
    public function sendUnsubscribeConfirm(Subscriber $subscriber): ?string
    {
        $body = $this->render('unsubscribe-confirm.php', [
            'subscriber' => $this->subscriberToArray($subscriber),
        ]);

        $subject = 'عضویت شما در خبرنامه لغو شد';

        return $this->send($subscriber, $subject, $body, 'تأیید لغو عضویت');
    }

    // ─── Internal ───

    /**
     * ارسال با wrap در layout
     */
    private function send(Subscriber $subscriber, string $subject, string $bodyHtml, string $title): ?string
    {
        try {
            $wrapped = $this->render('layout.php', [
                'emailTitle'      => $title,
                'emailPreheader'  => $subject,
                'emailBody'       => $bodyHtml,
                'emailSiteName'   => $this->siteName,
                'emailSiteUrl'    => $this->siteUrl,
            ]);

            return $this->mailer->send(
                to: $subscriber->email()->value(),
                toName: $subscriber->name(),
                subject: $subject,
                bodyHtml: $wrapped,
                bodyText: null,
                preheader: $subject
            );
        } catch (Throwable $e) {
            error_log('SubscriptionMailer::send error for ' . $subscriber->email()->value() . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * رندر یک template با متغیرهای داده‌شده
     */
    private function render(string $template, array $vars): string
    {
        $file = $this->templatesPath . '/' . $template;

        if (!is_file($file)) {
            error_log('SubscriptionMailer: template not found: ' . $file);
            return '';
        }

        // 🆕 متغیرهای مشترک که همه templateها دارند
        $vars = array_merge([
            'emailSiteName' => $this->siteName,
            'emailSiteUrl'  => $this->siteUrl,
        ], $vars);

        // استخراج متغیرها در scope محلی
        extract($vars, EXTR_SKIP);

        ob_start();
        try {
            include $file;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            error_log('SubscriptionMailer::render error in ' . $template . ': ' . $e->getMessage());
            return '';
        }
    }

    /**
     * تبدیل Subscriber به آرایه برای templates
     */
    private function subscriberToArray(Subscriber $subscriber): array
    {
        return [
            'id'    => $subscriber->id(),
            'email' => $subscriber->email()->value(),
            'name'  => $subscriber->name(),
        ];
    }
}

<?php
/**
 * PartoCMS - Newsletter Email: Unsubscribe Confirmation
 *
 * تأیید لغو عضویت
 *
 * @var array  $subscriber
 * @var string $siteUrl
 */

$name = $subscriber['name'] ?? '';
$greeting = $name !== '' ? 'سلام ' . htmlspecialchars($name) . '،' : 'سلام،';
$siteUrl  = htmlspecialchars($emailSiteUrl);
?>
<div style="color: #334155; font-size: 15px; line-height: 1.9;">

    <p style="margin: 0 0 15px; font-size: 16px; color: #1e293b; font-weight: bold;">
        <?= $greeting ?>
    </p>

    <p style="margin: 0 0 20px;">
        عضویت شما در خبرنامه <strong><?= htmlspecialchars($emailSiteName) ?></strong> لغو شد.
    </p>

    <div style="background: #fef3c7; border-right: 4px solid #f59e0b; border-radius: 8px; padding: 15px 20px; margin: 25px 0;">
        <p style="margin: 0; color: #92400e; font-size: 14px;">
            ⚠️ از این پس هیچ ایمیلی از خبرنامه ما دریافت نخواهید کرد.
        </p>
    </div>

    <p style="margin: 0 0 25px;">
        اگر به اشتباه لغو کردید، می‌توانید دوباره در سایت ما ثبت‌نام کنید.
    </p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="<?= $siteUrl ?>"
           style="display: inline-block; padding: 14px 35px; background: #64748b; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px;">
            🌐 بازگشت به سایت
        </a>
    </div>

    <hr style="border: none; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

    <p style="margin: 0; font-size: 12px; color: #cbd5e1; text-align: center;">
        از اینکه همراه ما بودید سپاسگزاریم. 🙏
    </p>

</div>

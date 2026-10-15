<?php
/**
 * PartoCMS - Newsletter Email: Welcome
 *
 * ایمیل خوشآمد بعد از تأیید عضویت
 *
 * @var array  $subscriber
 * @var string $unsubUrl
 */

$name = $subscriber['name'] ?? '';
$greeting = $name !== '' ? 'سلام ' . htmlspecialchars($name) . ' عزیز،' : 'سلام،';
$siteUrl  = htmlspecialchars($emailSiteUrl);
?>
<div style="color: #334155; font-size: 15px; line-height: 1.9;">

    <p style="margin: 0 0 15px; font-size: 16px; color: #1e293b; font-weight: bold;">
        🎉 <?= $greeting ?>
    </p>

    <p style="margin: 0 0 20px;">
        عضویت شما در خبرنامه <strong><?= htmlspecialchars($emailSiteName) ?></strong> با موفقیت تأیید شد.
    </p>

    <p style="margin: 0 0 25px;">
        از این به بعد جدیدترین اخبار، مطالب و پیشنهادهای ما را مستقیم در ایمیل خود دریافت خواهید کرد.
    </p>

    <div style="background: #eef2ff; border-right: 4px solid #6366f1; border-radius: 8px; padding: 15px 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px; color: #4338ca; font-weight: bold; font-size: 14px;">
            💡 چه چیزی دریافت می‌کنید؟
        </p>
        <ul style="margin: 0; padding-right: 20px; color: #475569; font-size: 14px; line-height: 2;">
            <li>📰 خبرهای تازه و مطالب مفید</li>
            <li>🎁 پیشنهادهای ویژه مشترکین</li>
            <li>📅 رویدادها و اطلاع‌رسانی‌ها</li>
        </ul>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="<?= $siteUrl ?>"
           style="display: inline-block; padding: 14px 35px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px;">
            🌐 مشاهده سایت
        </a>
    </div>

    <hr style="border: none; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

    <p style="margin: 0; font-size: 12px; color: #cbd5e1; text-align: center;">
        هر زمان بخواهید می‌توانید <a href="<?= htmlspecialchars($unsubUrl) ?>" style="color: #94a3b8; text-decoration: underline;">عضویت خود را لغو کنید</a>.
    </p>

</div>

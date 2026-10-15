<?php
/**
 * PartoCMS - Newsletter Email: Confirmation
 *
 * محتوای تأیید عضویت (double opt-in)
 * درون layout.php رندر می‌شود
 *
 * @var array  $subscriber   اطلاعات مشترک
 * @var string $confirmUrl   لینک تأیید
 * @var string $unsubUrl     لینک لغو
 */

$name = $subscriber['name'] ?? '';
$greeting = $name !== '' ? 'سلام ' . htmlspecialchars($name) . ' عزیز،' : 'سلام،';
?>
<div style="color: #334155; font-size: 15px; line-height: 1.9;">

    <p style="margin: 0 0 15px; font-size: 16px; color: #1e293b; font-weight: bold;">
        <?= $greeting ?>
    </p>

    <p style="margin: 0 0 20px;">
        از ثبت‌نام شما در خبرنامه <strong><?= htmlspecialchars($emailSiteName) ?></strong> سپاسگزاریم.
    </p>

    <p style="margin: 0 0 25px;">
        برای تکمیل عضویت و دریافت خبرنامه‌های ما، لطفاً روی دکمه زیر کلیک کنید:
    </p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="<?= htmlspecialchars($confirmUrl) ?>"
           style="display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 15px; box-shadow: 0 4px 15px rgba(99,102,241,0.4);">
            ✅ تأیید عضویت
        </a>
    </div>

    <p style="margin: 25px 0 10px; font-size: 13px; color: #64748b;">
        اگر دکمه بالا کار نمی‌کند، این لینک را در مرورگر خود کپی کنید:
    </p>

    <p style="margin: 0 0 25px; padding: 12px 15px; background: #f1f5f9; border-radius: 6px; word-break: break-all; direction: ltr; text-align: left; font-family: monospace; font-size: 12px; color: #6366f1;">
        <?= htmlspecialchars($confirmUrl) ?>
    </p>

    <hr style="border: none; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

    <p style="margin: 0 0 10px; font-size: 13px; color: #94a3b8;">
        💡 اگر شما این درخواست را نداده‌اید، این ایمیل را نادیده بگیرید. عضویت شما فعال نخواهد شد.
    </p>

    <p style="margin: 0; font-size: 12px; color: #cbd5e1;">
        لینک <a href="<?= htmlspecialchars($unsubUrl) ?>" style="color: #94a3b8; text-decoration: underline;">لغو عضویت</a>
    </p>

</div>

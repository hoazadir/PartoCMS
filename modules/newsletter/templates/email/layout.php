<?php
/**
 * PartoCMS - Newsletter Email Layout
 *
 * قالب HTML مشترک برای همه ایمیل‌های خبرنامه
 *
 * @var string $emailTitle        عنوان بالای ایمیل
 * @var string $emailPreheader    متن کوتاه inbox preview
 * @var string $emailBody         محتوای اصلی (HTML)
 * @var string $emailSiteName     نام سایت
 * @var string $emailSiteUrl      آدرس سایت
 * @var string $emailFooterText   متن فوتر (اختیاری)
 */

$emailFooterText = $emailFooterText ?? 'این ایمیل به دلیل عضویت شما در خبرنامه ارسال شده است.';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($emailTitle) ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: Tahoma, 'Vazirmatn', sans-serif; direction: rtl;">

<div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    <?= htmlspecialchars($emailPreheader) ?>
</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f1f5f9; padding: 30px 15px;">
    <tr>
        <td align="center">

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

                <tr>
                    <td style="background: linear-gradient(135deg, #6366f1, #4f46e5); padding: 35px 30px; text-align: center;">
                        <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: bold;">
                            <?= htmlspecialchars($emailSiteName) ?>
                        </h1>
                        <p style="margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 13px;">
                            <?= htmlspecialchars($emailTitle) ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 35px 30px;">
                        <?= $emailBody ?>
                    </td>
                </tr>

                <tr>
                    <td style="background: #f8fafc; padding: 25px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                        <p style="margin: 0 0 10px; color: #64748b; font-size: 12px; line-height: 1.7;">
                            <?= htmlspecialchars($emailFooterText) ?>
                        </p>
                        <p style="margin: 0; color: #94a3b8; font-size: 11px;">
                            © <?= date('Y') ?> <?= htmlspecialchars($emailSiteName) ?>
                        </p>
                        <p style="margin: 8px 0 0;">
                            <a href="<?= htmlspecialchars($emailSiteUrl) ?>" style="color: #6366f1; text-decoration: none; font-size: 12px;">
                                <?= htmlspecialchars($emailSiteUrl) ?>
                            </a>
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>

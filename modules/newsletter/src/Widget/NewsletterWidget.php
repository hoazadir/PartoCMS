<?php
/**
 * PartoCMS - Newsletter Widget
 *
 * API ساده برای نمایش فرم عضویت در فرانت‌اند
 *
 * @package PartoCMS\Newsletter
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Widget;

final class NewsletterWidget
{
    private static bool $assetsRendered = false;

    /**
     * رندر فرم عضویت
     */
    public static function renderForm(array $options = []): string
    {
        $opts = array_merge([
            'title'        => '📮 عضویت در خبرنامه',
            'subtitle'     => 'جدیدترین خبرها را مستقیم در ایمیل خود دریافت کنید',
            'show_name'    => true,
            'button_text'  => '🚀 عضویت',
            'placeholder'  => 'ایمیل شما',
            'source'       => 'widget',
            'class'        => 'nl-widget',
        ], $options);

        $id  = 'nl-form-' . bin2hex(random_bytes(4));
        $assets = self::$assetsRendered ? '' : self::renderAssets();
        self::$assetsRendered = true;

        $html  = $assets;
        $html .= '<div class="' . htmlspecialchars($opts['class']) . '" id="' . $id . '">';
        $html .= '  <div class="nl-header">';
        $html .= '    <h3 class="nl-title">' . htmlspecialchars($opts['title']) . '</h3>';
        if ($opts['subtitle'] !== '') {
            $html .= '    <p class="nl-subtitle">' . htmlspecialchars($opts['subtitle']) . '</p>';
        }
        $html .= '  </div>';

        $html .= '  <form class="nl-form" onsubmit="return nlSubmit(event, \'' . $id . '\')">';
        $html .= '    <input type="hidden" name="source" value="' . htmlspecialchars($opts['source']) . '">';

        if ($opts['show_name']) {
            $html .= '    <div class="nl-field">';
            $html .= '      <input type="text" name="name" placeholder="نام شما (اختیاری)" autocomplete="name">';
            $html .= '    </div>';
        }

        $html .= '    <div class="nl-field">';
        $html .= '      <input type="email" name="email" required placeholder="' . htmlspecialchars($opts['placeholder']) . '" autocomplete="email">';
        $html .= '    </div>';

        $html .= '    <button type="submit" class="nl-btn">' . htmlspecialchars($opts['button_text']) . '</button>';
        $html .= '    <div class="nl-message" style="display:none;"></div>';
        $html .= '  </form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * رندر پیام موفقیت
     */
    public static function renderSuccess(string $message = '✅ با موفقیت عضو شدید!'): string
    {
        return '<div class="nl-success">' . htmlspecialchars($message) . '</div>';
    }

    /**
     * رندر assets (CSS + JS) — فقط یک بار
     */
    private static function renderAssets(): string
    {
        $ajaxUrl = SITE_URL . '/modules/newsletter/ajax/subscribe.php';
        $ajaxUrl = htmlspecialchars($ajaxUrl, ENT_QUOTES);

        return <<<HTML
<style>
.nl-widget {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-radius: 16px;
    padding: 35px 30px;
    color: #fff;
    max-width: 500px;
    margin: 20px auto;
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
    font-family: Tahoma, Vazirmatn, sans-serif;
}
.nl-widget .nl-header { text-align: center; margin-bottom: 25px; }
.nl-widget .nl-title { margin: 0 0 8px; font-size: 20px; }
.nl-widget .nl-subtitle { margin: 0; font-size: 13px; opacity: 0.9; }
.nl-widget .nl-field { margin-bottom: 12px; }
.nl-widget .nl-field input {
    width: 100%; padding: 12px 16px; border: none; border-radius: 8px;
    font-family: inherit; font-size: 14px; box-sizing: border-box;
    background: rgba(255,255,255,0.95); color: #1e293b;
}
.nl-widget .nl-field input:focus { outline: 2px solid #fff; }
.nl-widget .nl-btn {
    width: 100%; padding: 13px; border: none; border-radius: 8px;
    background: #fff; color: #4f46e5; font-weight: bold; font-size: 15px;
    cursor: pointer; font-family: inherit; transition: 0.2s;
}
.nl-widget .nl-btn:hover { background: #f8fafc; transform: translateY(-2px); }
.nl-widget .nl-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.nl-widget .nl-message {
    margin-top: 15px; padding: 12px 16px; border-radius: 8px;
    font-size: 14px; text-align: center;
}
.nl-widget .nl-message.nl-success { background: rgba(255,255,255,0.2); }
.nl-widget .nl-message.nl-error { background: rgba(220, 38, 38, 0.3); }
.nl-success {
    background: #dcfce7; color: #166534; padding: 20px; border-radius: 12px;
    text-align: center; font-size: 15px; max-width: 500px; margin: 20px auto;
    font-family: Tahoma, sans-serif;
}
</style>
<script>
async function nlSubmit(e, formId) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('.nl-btn');
    const msg = form.querySelector('.nl-message');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '⏳ در حال ثبت...';
    msg.style.display = 'none';

    try {
        const formData = new FormData(form);
        const resp = await fetch('{$ajaxUrl}', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            msg.className = 'nl-message nl-success';
            msg.innerHTML = data.message || '✅ ثبت شد';
            form.reset();
        } else {
            msg.className = 'nl-message nl-error';
            msg.innerHTML = '❌ ' + (data.error || 'خطای نامشخص');
        }
        msg.style.display = 'block';
    } catch (err) {
        msg.className = 'nl-message nl-error';
        msg.innerHTML = '❌ خطای شبکه';
        msg.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }

    return false;
}
</script>
HTML;
    }
}

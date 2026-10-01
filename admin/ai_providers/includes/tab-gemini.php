<?php
/**
 * 🆕 PartoCMS - Tab Gemini
 * تب تنظیمات Google Gemini API
 *
 * دسترسی: از طریق admin/ai_providers.php?tab=gemini
 */

if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ خواندن وضعیت فعلی ═══
$geminiKey      = $settings['ai_gemini_api_key'] ?? '';
$geminiModel    = $settings['ai_gemini_model'] ?? 'gemini-2.5-flash';
$geminiTimeout  = $settings['ai_gemini_timeout'] ?? '60';
$isConfigured   = !empty($geminiKey);
?>

<!-- Header -->
<div class="provider-header">
    <div class="provider-icon">🔵</div>
    <div class="provider-title">
        <h2>Google Gemini API</h2>
        <p>مدل چندزبانه گوگل با کیفیت بالا و context بسیار بزرگ</p>
    </div>
    <div>
        <?php if ($isConfigured): ?>
            <span class="status-badge status-active">
                <i class="bi bi-check-circle-fill"></i>
                تنظیم شده
            </span>
        <?php else: ?>
            <span class="status-badge status-warning">
                <i class="bi bi-exclamation-circle"></i>
                تنظیم نشده
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- توضیحات -->
<div class="info-box">
    <h4>💡 Gemini چیست؟</h4>
    <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
        Gemini مدل هوش مصنوعی چندزبانه گوگل است. مزایای اصلی:
    </p>
    <ul style="margin:10px 0 0;padding-right:20px;font-size:13.5px;line-height:2;color:#334155;">
        <li>✅ <strong>Context بسیار بزرگ</strong> — تا ۱ میلیون توکن (مناسب متن‌های طولانی)</li>
        <li>✅ <strong>چندزبانه عالی</strong> — پشتیبانی خوب از فارسی</li>
        <li>✅ <strong>رایگان برای استفاده شخصی</strong></li>
        <li>⚠️ نیاز به VPN (تحریم ایران)</li>
    </ul>
</div>

<!-- راهنمای گام‌به‌گام -->
<div class="info-box" style="background:#fef3c7;border-right-color:#f59e0b;">
    <h4 style="color:#92400e;">📋 راهنمای دریافت API Key (گام‌به‌گام)</h4>
    <ol>
        <li>
            <strong>VPN خود را روشن کنید</strong>
            <span style="color:#dc2626;font-weight:bold;">(Google برای ایران تحریم است)</span>
        </li>
        <li>
            به سایت
            <a href="https://aistudio.google.com/" target="_blank" rel="noopener">
                aistudio.google.com <i class="bi bi-box-arrow-up-right" style="font-size:11px;"></i>
            </a>
            بروید
        </li>
        <li>
            با حساب <strong>Google</strong> خود وارد شوید
        </li>
        <li>
            قوانین استفاده را <strong>قبول کنید</strong>
        </li>
        <li>
            از منوی سمت چپ روی
            <strong>Get API Key</strong> کلیک کنید
        </li>
        <li>
            روی دکمه <strong>Create API Key</strong> کلیک کنید
        </li>
        <li>
            یکی از پروژه‌ها را انتخاب کنید یا یک پروژه جدید بسازید
        </li>
        <li>
            کلید ساخته می‌شود (شبیه: <code>AIzaSy...</code>) — آن را <strong>کپی</strong> کنید
        </li>
        <li>
            کلید را در فیلد زیر <strong>بچسبانید</strong> و روی دکمه
            <strong>💾 ذخیره</strong> کلیک کنید
        </li>
    </ol>
</div>

<!-- محدودیت‌ها -->
<div class="limits-grid">
    <div class="limit-item">
        <div class="label">💰 هزینه</div>
        <div class="value" style="color:#16a34a;">رایگان</div>
    </div>
    <div class="limit-item">
        <div class="label">⚡ سرعت</div>
        <div class="value" style="color:#2563eb;">سریع</div>
    </div>
    <div class="limit-item">
        <div class="label">📊 محدودیت روزانه</div>
        <div class="value">1,500 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">📈 محدودیت دقیقه</div>
        <div class="value">15 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">📏 Context</div>
        <div class="value">1M توکن</div>
    </div>
    <div class="limit-item">
        <div class="label">🌐 نیاز به VPN</div>
        <div class="value" style="color:#dc2626;">بله</div>
    </div>
</div>

<!-- فرم تنظیمات -->
<div style="margin-top:30px;padding-top:25px;border-top:2px solid #e2e8f0;">

    <h3 style="margin:0 0 20px;font-size:16px;color:#0f172a;">
        🔧 تنظیمات Gemini
    </h3>

    <!-- API Key -->
    <div class="form-group">
        <label for="geminiKeyInput">
            🔑 API Key
            <?php if ($isConfigured): ?>
                <span style="color:#16a34a;font-size:12px;">(ذخیره شده)</span>
            <?php endif; ?>
        </label>

        <div class="input-group">
            <input type="password"
                   id="geminiKeyInput"
                   class="form-control"
                   dir="ltr"
                   placeholder="AIzaSy..."
                   value="<?= htmlspecialchars($geminiKey) ?>">

            <button type="button"
                    class="btn btn-secondary"
                    onclick="togglePasswordVisibility('geminiKeyInput', this)"
                    title="نمایش/مخفی">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <small>این Key فقط روی سرور شما ذخیره می‌شود.</small>
    </div>

    <!-- Model -->
    <div class="form-group">
        <label for="geminiModelInput">🤖 مدل پیش‌فرض</label>

        <select id="geminiModelInput" class="form-control">
            <option value="gemini-2.5-flash" <?= $geminiModel === 'gemini-2.5-flash' ? 'selected' : '' ?>>
                🏆 Gemini 2.5 Flash (توصیه شده — سریع و باکیفیت)
            </option>
            <option value="gemini-2.5-flash-lite" <?= $geminiModel === 'gemini-2.5-flash-lite' ? 'selected' : '' ?>>
                ⚡ Gemini 2.5 Flash Lite (سریع‌تر)
            </option>
            <option value="gemini-2.5-pro" <?= $geminiModel === 'gemini-2.5-pro' ? 'selected' : '' ?>>
                💎 Gemini 2.5 Pro (دقیق‌تر)
            </option>
            <option value="gemini-1.5-flash" <?= $geminiModel === 'gemini-1.5-flash' ? 'selected' : '' ?>>
                Gemini 1.5 Flash
            </option>
            <option value="gemini-1.5-pro" <?= $geminiModel === 'gemini-1.5-pro' ? 'selected' : '' ?>>
                Gemini 1.5 Pro
            </option>
        </select>

        <small>مدل پیش‌فرض برای تولید متن و ترجمه</small>
    </div>

    <!-- Timeout -->
    <div class="form-group">
        <label for="geminiTimeoutInput">⏱ Timeout (ثانیه)</label>

        <input type="number"
               id="geminiTimeoutInput"
               class="form-control"
               dir="ltr"
               min="10"
               max="300"
               value="<?= htmlspecialchars($geminiTimeout) ?>">

        <small>حداکثر زمان انتظار برای پاسخ (پیش‌فرض: ۶۰ ثانیه)</small>
    </div>

    <!-- Buttons -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:25px;">
        <button type="button"
                class="btn btn-primary"
                onclick="saveGeminiSettings()">
            <i class="bi bi-save"></i>
            💾 ذخیره تغییرات
        </button>

        <button type="button"
                class="btn btn-success"
                onclick="testProvider('gemini')">
            <i class="bi bi-lightning"></i>
            🔍 تست اتصال
        </button>

        <?php if ($isConfigured): ?>
            <button type="button"
                    class="btn btn-danger"
                    onclick="clearGeminiKey()"
                    style="margin-right:auto;">
                <i class="bi bi-trash"></i>
                🗑 حذف Key
            </button>
        <?php endif; ?>
    </div>

</div>

<script>
function saveGeminiSettings() {
    const key     = document.getElementById('geminiKeyInput').value.trim();
    const model   = document.getElementById('geminiModelInput').value;
    const timeout = document.getElementById('geminiTimeoutInput').value;

    if (key === '') {
        showAlert('error', 'لطفاً API Key را وارد کنید');
        return;
    }

    const formData = new FormData();
    formData.append('provider', 'gemini');
    formData.append('api_key', key);
    formData.append('model', model);
    formData.append('timeout', timeout);

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'تنظیمات Gemini ذخیره شد'));
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا در ذخیره'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}

function clearGeminiKey() {
    if (!confirm('آیا مطمئن هستید که می‌خواهید API Key Gemini را حذف کنید؟')) return;

    const formData = new FormData();
    formData.append('provider', 'gemini');
    formData.append('clear', '1');

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ API Key حذف شد');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    });
}
</script>

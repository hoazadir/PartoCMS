<?php
/**
 * 🆕 PartoCMS - Tab Groq
 * تب تنظیمات Groq Cloud API
 *
 * دسترسی: از طریق admin/ai_providers.php?tab=groq
 */

// جلوگیری از دسترسی مستقیم
if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ خواندن وضعیت فعلی ═══
$groqKey      = $settings['ai_groq_api_key'] ?? '';
$groqModel    = $settings['ai_groq_model'] ?? 'llama-3.3-70b-versatile';
$groqTimeout  = $settings['ai_groq_timeout'] ?? '60';
$isConfigured = !empty($groqKey);
?>

<!-- Header -->
<div class="provider-header">
    <div class="provider-icon">🟢</div>
    <div class="provider-title">
        <h2>Groq Cloud API</h2>
        <p>فوق‌سریع‌ترین سرویس هوش مصنوعی ابری با LPU اختصاصی</p>
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
    <h4>💡 Groq چیست؟</h4>
    <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
        Groq یک سرویس ابری است که از تراشه‌های <strong>LPU</strong> (Language Processing Unit) استفاده می‌کند.
        این یعنی پاسخ‌ها <strong>۱۰ تا ۵۰ برابر سریع‌تر</strong> از سرویس‌های معمولی هستند.
        استفاده شخصی <strong>کاملاً رایگان</strong> است.
    </p>
</div>

<!-- راهنمای گام‌به‌گام -->
<div class="info-box" style="background:#fef3c7;border-right-color:#f59e0b;">
    <h4 style="color:#92400e;">📋 راهنمای دریافت API Key (گام‌به‌گام)</h4>
    <ol>
        <li>
            <strong>VPN خود را روشن کنید</strong>
            <span style="color:#dc2626;font-weight:bold;">(Groq برای ایران تحریم است)</span>
        </li>
        <li>
            به سایت
            <a href="https://console.groq.com/" target="_blank" rel="noopener">
                console.groq.com <i class="bi bi-box-arrow-up-right" style="font-size:11px;"></i>
            </a>
            بروید
        </li>
        <li>
            روی دکمه <strong>Sign Up</strong> کلیک کنید و با
            <strong>Google</strong> یا <strong>GitHub</strong> ثبت‌نام کنید
        </li>
        <li>
            بعد از ورود، از منوی سمت راست روی
            <strong>API Keys</strong> کلیک کنید
        </li>
        <li>
            روی دکمه <strong>Create API Key</strong> کلیک کنید
        </li>
        <li>
            یک نام برای Key انتخاب کنید (مثلاً: <code>PartoCMS</code>)
        </li>
        <li>
            کلید ساخته می‌شود — آن را <strong>کپی</strong> کنید
            <br>
            <small style="color:#dc2626;">⚠️ توجه: بعد از بستن پنجره، دیگر نمایش داده نمی‌شود!</small>
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
        <div class="value" style="color:#2563eb;">فوق‌سریع</div>
    </div>
    <div class="limit-item">
        <div class="label">📊 محدودیت روزانه</div>
        <div class="value">14,400 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">📈 محدودیت دقیقه</div>
        <div class="value">30 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">🌐 نیاز به VPN</div>
        <div class="value" style="color:#dc2626;">بله</div>
    </div>
</div>

<!-- فرم تنظیمات -->
<div style="margin-top:30px;padding-top:25px;border-top:2px solid #e2e8f0;">

    <h3 style="margin:0 0 20px;font-size:16px;color:#0f172a;">
        🔧 تنظیمات Groq
    </h3>

    <!-- API Key -->
    <div class="form-group">
        <label for="groqKeyInput">
            🔑 API Key
            <?php if ($isConfigured): ?>
                <span style="color:#16a34a;font-size:12px;">(ذخیره شده)</span>
            <?php endif; ?>
        </label>

        <div class="input-group">
            <input type="password"
                   id="groqKeyInput"
                   class="form-control"
                   dir="ltr"
                   placeholder="gsk_..."
                   value="<?= htmlspecialchars($groqKey) ?>">

            <button type="button"
                    class="btn btn-secondary"
                    onclick="togglePasswordVisibility('groqKeyInput', this)"
                    title="نمایش/مخفی">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <small>
            این Key فقط روی سرور شما ذخیره می‌شود و در هیچ جای دیگری ارسال نمی‌شود.
        </small>
    </div>

    <!-- Model -->
    <div class="form-group">
        <label for="groqModelInput">🤖 مدل پیش‌فرض</label>

        <select id="groqModelInput" class="form-control">
            <option value="llama-3.3-70b-versatile" <?= $groqModel === 'llama-3.3-70b-versatile' ? 'selected' : '' ?>>
                🏆 Llama 3.3 70B (توصیه شده — باکیفیت)
            </option>
            <option value="llama-3.1-8b-instant" <?= $groqModel === 'llama-3.1-8b-instant' ? 'selected' : '' ?>>
                ⚡ Llama 3.1 8B (سریع‌تر)
            </option>
            <option value="llama3-70b-8192" <?= $groqModel === 'llama3-70b-8192' ? 'selected' : '' ?>>
                Llama 3 70B
            </option>
            <option value="mixtral-8x7b-32768" <?= $groqModel === 'mixtral-8x7b-32768' ? 'selected' : '' ?>>
                Mixtral 8x7B (Context بزرگ)
            </option>
            <option value="gemma2-9b-it" <?= $groqModel === 'gemma2-9b-it' ? 'selected' : '' ?>>
                Gemma 2 9B
            </option>
        </select>

        <small>مدل پیش‌فرض برای تولید متن و ترجمه</small>
    </div>

    <!-- Timeout -->
    <div class="form-group">
        <label for="groqTimeoutInput">⏱ Timeout (ثانیه)</label>

        <input type="number"
               id="groqTimeoutInput"
               class="form-control"
               dir="ltr"
               min="10"
               max="300"
               value="<?= htmlspecialchars($groqTimeout) ?>">

        <small>حداکثر زمان انتظار برای پاسخ (پیش‌فرض: ۶۰ ثانیه)</small>
    </div>

    <!-- Buttons -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:25px;">
        <button type="button"
                class="btn btn-primary"
                onclick="saveGroqSettings()">
            <i class="bi bi-save"></i>
            💾 ذخیره تغییرات
        </button>

        <button type="button"
                class="btn btn-success"
                onclick="testProvider('groq')">
            <i class="bi bi-lightning"></i>
            🔍 تست اتصال
        </button>

        <?php if ($isConfigured): ?>
            <button type="button"
                    class="btn btn-danger"
                    onclick="clearGroqKey()"
                    style="margin-right:auto;">
                <i class="bi bi-trash"></i>
                🗑 حذف Key
            </button>
        <?php endif; ?>
    </div>

</div>

<script>
function saveGroqSettings() {
    const key     = document.getElementById('groqKeyInput').value.trim();
    const model   = document.getElementById('groqModelInput').value;
    const timeout = document.getElementById('groqTimeoutInput').value;

    if (key === '') {
        showAlert('error', 'لطفاً API Key را وارد کنید');
        return;
    }

    const formData = new FormData();
    formData.append('provider', 'groq');
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
            showAlert('success', '✅ ' + (data.message || 'تنظیمات Groq ذخیره شد'));
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا در ذخیره'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}

function clearGroqKey() {
    if (!confirm('آیا مطمئن هستید که می‌خواهید API Key Groq را حذف کنید؟')) return;

    const formData = new FormData();
    formData.append('provider', 'groq');
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

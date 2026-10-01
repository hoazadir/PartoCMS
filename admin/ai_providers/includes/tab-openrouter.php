<?php
/**
 * 🆕 PartoCMS - Tab OpenRouter
 * تب تنظیمات OpenRouter API
 *
 * دسترسی: از طریق admin/ai_providers.php?tab=openrouter
 */

if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ خواندن وضعیت فعلی ═══
$orKey      = $settings['ai_openrouter_api_key'] ?? '';
$orModel    = $settings['ai_openrouter_model'] ?? 'meta-llama/llama-3.3-70b-instruct:free';
$orTimeout  = $settings['ai_openrouter_timeout'] ?? '60';
$isConfigured = !empty($orKey);
?>

<!-- Header -->
<div class="provider-header">
    <div class="provider-icon">🟣</div>
    <div class="provider-title">
        <h2>OpenRouter API</h2>
        <p>دسترسی به ۱۰۰+ مدل از providers مختلف در یک API</p>
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
    <h4>💡 OpenRouter چیست؟</h4>
    <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
        OpenRouter یک دروازه واحد برای دسترسی به مدل‌های مختلف است.
        به جای ثبت‌نام در هر provider جداگانه، از یک API استفاده می‌کنید.
    </p>
    <ul style="margin:10px 0 0;padding-right:20px;font-size:13.5px;line-height:2;color:#334155;">
        <li>✅ <strong>تنوع بالا</strong> — Llama, Mistral, Gemma, Qwen و ۱۰۰+ مدل دیگر</li>
        <li>✅ <strong>مدل‌های رایگان</strong> با پسوند <code>:free</code></li>
        <li>✅ <strong>بدون تحریم در اکثر مواقع</strong> (بستگی به provider پشت آن)</li>
        <li>⚠️ Rate limit پایین‌تر در نسخه رایگان</li>
    </ul>
</div>

<!-- راهنمای گام‌به‌گام -->
<div class="info-box" style="background:#fef3c7;border-right-color:#f59e0b;">
    <h4 style="color:#92400e;">📋 راهنمای دریافت API Key (گام‌به‌گام)</h4>
    <ol>
        <li>
            به سایت
            <a href="https://openrouter.ai/" target="_blank" rel="noopener">
                openrouter.ai <i class="bi bi-box-arrow-up-right" style="font-size:11px;"></i>
            </a>
            بروید
        </li>
        <li>
            روی دکمه <strong>Sign In</strong> کلیک کنید
        </li>
        <li>
            با <strong>Google</strong>، <strong>GitHub</strong> یا ایمیل ثبت‌نام کنید
        </li>
        <li>
            بعد از ورود، روی آیکن پروفایل خود کلیک کنید
        </li>
        <li>
            از منو روی <strong>Keys</strong> کلیک کنید
            یا مستقیم به
            <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>
            بروید
        </li>
        <li>
            روی دکمه <strong>Create Key</strong> کلیک کنید
        </li>
        <li>
            یک نام برای Key بگذارید (مثلاً: <code>PartoCMS</code>)
        </li>
        <li>
            روی <strong>Create</strong> کلیک کنید
        </li>
        <li>
            کلید ساخته می‌شود (شبیه: <code>sk-or-v1-...</code>) — آن را <strong>کپی</strong> کنید
        </li>
        <li>
            کلید را در فیلد زیر <strong>بچسبانید</strong> و روی
            <strong>💾 ذخیره</strong> کلیک کنید
        </li>
    </ol>
    <p style="margin:15px 0 0;font-size:13px;color:#92400e;">
        <strong>💡 نکته:</strong> در OpenRouter می‌توانید مستقیم وارد شوید و از مدل‌های
        رایگان (با <code>:free</code>) استفاده کنید.
    </p>
</div>

<!-- محدودیت‌ها -->
<div class="limits-grid">
    <div class="limit-item">
        <div class="label">💰 هزینه</div>
        <div class="value" style="color:#16a34a;">رایگان*</div>
    </div>
    <div class="limit-item">
        <div class="label">⚡ سرعت</div>
        <div class="value" style="color:#2563eb;">متوسط تا سریع</div>
    </div>
    <div class="limit-item">
        <div class="label">📊 محدودیت روزانه</div>
        <div class="value">200 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">📈 محدودیت دقیقه</div>
        <div class="value">20 درخواست</div>
    </div>
    <div class="limit-item">
        <div class="label">🌐 نیاز به VPN</div>
        <div class="value" style="color:#f59e0b;">نیمه (بستگی دارد)</div>
    </div>
</div>

<p style="font-size:11.5px;color:#64748b;margin-top:10px;">
    * مدل‌های رایگان با <code>:free</code> — برخی مدل‌ها پول می‌خواهند.
</p>

<!-- فرم تنظیمات -->
<div style="margin-top:30px;padding-top:25px;border-top:2px solid #e2e8f0;">

    <h3 style="margin:0 0 20px;font-size:16px;color:#0f172a;">
        🔧 تنظیمات OpenRouter
    </h3>

    <!-- API Key -->
    <div class="form-group">
        <label for="orKeyInput">
            🔑 API Key
            <?php if ($isConfigured): ?>
                <span style="color:#16a34a;font-size:12px;">(ذخیره شده)</span>
            <?php endif; ?>
        </label>

        <div class="input-group">
            <input type="password"
                   id="orKeyInput"
                   class="form-control"
                   dir="ltr"
                   placeholder="sk-or-v1-..."
                   value="<?= htmlspecialchars($orKey) ?>">

            <button type="button"
                    class="btn btn-secondary"
                    onclick="togglePasswordVisibility('orKeyInput', this)"
                    title="نمایش/مخفی">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <small>این Key فقط روی سرور شما ذخیره می‌شود.</small>
    </div>

    <!-- Model -->
    <div class="form-group">
        <label for="orModelInput">🤖 مدل پیش‌فرض</label>

        <select id="orModelInput" class="form-control">
            <option value="meta-llama/llama-3.3-70b-instruct:free" <?= $orModel === 'meta-llama/llama-3.3-70b-instruct:free' ? 'selected' : '' ?>>
                🏆 Llama 3.3 70B (رایگان — توصیه شده)
            </option>
            <option value="meta-llama/llama-3.1-8b-instruct:free" <?= $orModel === 'meta-llama/llama-3.1-8b-instruct:free' ? 'selected' : '' ?>>
                ⚡ Llama 3.1 8B (رایگان — سریع)
            </option>
            <option value="google/gemma-2-9b-it:free" <?= $orModel === 'google/gemma-2-9b-it:free' ? 'selected' : '' ?>>
                Gemma 2 9B (رایگان)
            </option>
            <option value="qwen/qwen-2.5-72b-instruct:free" <?= $orModel === 'qwen/qwen-2.5-72b-instruct:free' ? 'selected' : '' ?>>
                Qwen 2.5 72B (رایگان)
            </option>
            <option value="mistralai/mistral-7b-instruct:free" <?= $orModel === 'mistralai/mistral-7b-instruct:free' ? 'selected' : '' ?>>
                Mistral 7B (رایگان)
            </option>
            <option value="microsoft/phi-3-mini-128k-instruct:free" <?= $orModel === 'microsoft/phi-3-mini-128k-instruct:free' ? 'selected' : '' ?>>
                Phi-3 Mini (رایگان — Context بزرگ)
            </option>
            <option value="openchat/openchat-7b:free" <?= $orModel === 'openchat/openchat-7b:free' ? 'selected' : '' ?>>
                OpenChat 7B (رایگان)
            </option>
        </select>

        <small>
            همه مدل‌های بالا <strong>رایگان</strong> هستند.
            برای لیست کامل:
            <a href="https://openrouter.ai/models?max_price=0" target="_blank">
                OpenRouter Free Models
            </a>
        </small>
    </div>

    <!-- Timeout -->
    <div class="form-group">
        <label for="orTimeoutInput">⏱ Timeout (ثانیه)</label>

        <input type="number"
               id="orTimeoutInput"
               class="form-control"
               dir="ltr"
               min="10"
               max="300"
               value="<?= htmlspecialchars($orTimeout) ?>">

        <small>حداکثر زمان انتظار برای پاسخ (پیش‌فرض: ۶۰ ثانیه)</small>
    </div>

    <!-- Buttons -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:25px;">
        <button type="button"
                class="btn btn-primary"
                onclick="saveOpenRouterSettings()">
            <i class="bi bi-save"></i>
            💾 ذخیره تغییرات
        </button>

        <button type="button"
                class="btn btn-success"
                onclick="testProvider('openrouter')">
            <i class="bi bi-lightning"></i>
            🔍 تست اتصال
        </button>

        <?php if ($isConfigured): ?>
            <button type="button"
                    class="btn btn-danger"
                    onclick="clearOpenRouterKey()"
                    style="margin-right:auto;">
                <i class="bi bi-trash"></i>
                🗑 حذف Key
            </button>
        <?php endif; ?>
    </div>

</div>

<script>
function saveOpenRouterSettings() {
    const key     = document.getElementById('orKeyInput').value.trim();
    const model   = document.getElementById('orModelInput').value;
    const timeout = document.getElementById('orTimeoutInput').value;

    if (key === '') {
        showAlert('error', 'لطفاً API Key را وارد کنید');
        return;
    }

    const formData = new FormData();
    formData.append('provider', 'openrouter');
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
            showAlert('success', '✅ ' + (data.message || 'تنظیمات OpenRouter ذخیره شد'));
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا در ذخیره'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}

function clearOpenRouterKey() {
    if (!confirm('آیا مطمئن هستید که می‌خواهید API Key OpenRouter را حذف کنید؟')) return;

    const formData = new FormData();
    formData.append('provider', 'openrouter');
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

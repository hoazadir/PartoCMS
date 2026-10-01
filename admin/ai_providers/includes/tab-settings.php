<?php
/**
 * 🆕 PartoCMS - Tab Settings
 * تب تنظیمات کلی: ترتیب Fallback + Proxy
 *
 * دسترسی: از طریق admin/ai_providers.php?tab=settings
 */

if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ خواندن وضعیت فعلی ═══
$proxyUrl   = $settings['ai_proxy_url'] ?? '';
$proxyType  = $settings['ai_proxy_type'] ?? 'socks5';
$proxyUser  = $settings['ai_proxy_user'] ?? '';
$proxyPass  = $settings['ai_proxy_pass'] ?? '';

$debugMode  = ($settings['ai_gateway_debug'] ?? '0') === '1';

// ═══ Provider Chain ═══
$chainRaw = $settings['ai_provider_chain'] ?? '';
$chain = [];
if (!empty($chainRaw)) {
    $decoded = json_decode($chainRaw, true);
    if (is_array($decoded)) $chain = $decoded;
}
if (empty($chain)) {
    $chain = ['ollama', 'groq', 'gemini', 'openrouter'];
}

// اطمینان از وجود همه
foreach (['ollama', 'groq', 'gemini', 'openrouter'] as $p) {
    if (!in_array($p, $chain, true)) {
        $chain[] = $p;
    }
}

// ═══ اطلاعات محیط ═══
$envInfo = null;
if (class_exists('AIEnvironment')) {
    $envInfo = AIEnvironment::capabilities();
}

// ═══ وضعیت Proxy ═══
$proxyHealth = null;
if (class_exists('AIProxyManager') && !empty($proxyUrl)) {
    $proxyHealth = AIProxyManager::testHealth(true);
}

// نام و آیکون providerها
$providerMeta = [
    'ollama'     => ['icon' => '🖥', 'label' => 'Ollama (محلی)'],
    'groq'       => ['icon' => '🟢', 'label' => 'Groq'],
    'gemini'     => ['icon' => '🔵', 'label' => 'Gemini'],
    'openrouter' => ['icon' => '🟣', 'label' => 'OpenRouter'],
];
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- بخش ۱: ترتیب Fallback                                      -->
<!-- ═══════════════════════════════════════════════════════════ -->

<div class="provider-header">
    <div class="provider-icon">🔗</div>
    <div class="provider-title">
        <h2>ترتیب Fallback</h2>
        <p>اگر provider اول خطا داد، به provider بعدی سوئیچ می‌شود</p>
    </div>
</div>

<div class="info-box">
    <h4>💡 ترتیب Fallback چطور کار می‌کند؟</h4>
    <p style="margin:0;font-size:13.5px;line-height:1.8;color:#334155;">
        وقتی درخواست AI ارسال می‌شود، ابتدا provider اول امتحان می‌شود.
        اگر خطا داد (Timeout، Rate Limit، ...) به provider بعدی می‌رود.
        این کار ادامه می‌یابد تا یکی از آن‌ها پاسخ دهد.
    </p>
</div>

<!-- لیست Drag & Drop -->
<div style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:12px;padding:20px;margin-bottom:20px;">
    <p style="margin:0 0 15px;font-size:13px;color:#64748b;text-align:center;">
        ☰ برای تغییر ترتیب، هر ردیف را بکشید
    </p>

    <ul id="chainList" style="list-style:none;padding:0;margin:0;">
        <?php foreach ($chain as $i => $slug):
            $meta = $providerMeta[$slug] ?? ['icon' => '❓', 'label' => $slug];
        ?>
            <li data-slug="<?= htmlspecialchars($slug) ?>"
                style="background:#fff;border:2px solid #e2e8f0;border-radius:8px;padding:15px 20px;margin-bottom:10px;display:flex;align-items:center;gap:15px;cursor:grab;transition:all 0.2s;">
                <span style="font-size:18px;color:#94a3b8;">☰</span>
                <span style="font-weight:bold;color:#64748b;min-width:25px;"><?= $i + 1 ?>.</span>
                <span style="font-size:20px;"><?= $meta['icon'] ?></span>
                <span style="flex:1;font-weight:600;color:#0f172a;"><?= htmlspecialchars($meta['label']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<button type="button"
        class="btn btn-primary"
        onclick="saveProviderChain()">
    <i class="bi bi-save"></i>
    💾 ذخیره ترتیب
</button>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- بخش ۲: Proxy                                                -->
<!-- ═══════════════════════════════════════════════════════════ -->

<div style="margin-top:40px;padding-top:30px;border-top:2px solid #e2e8f0;">

    <div class="provider-header">
        <div class="provider-icon">🌐</div>
        <div class="provider-title">
            <h2>تنظیمات Proxy</h2>
            <p>برای دسترسی به providerهای تحریم‌شده (Groq, Gemini, OpenAI)</p>
        </div>
        <?php if (!empty($proxyUrl)): ?>
            <div>
                <?php if ($proxyHealth && !empty($proxyHealth['ok'])): ?>
                    <span class="status-badge status-active">
                        <i class="bi bi-check-circle-fill"></i>
                        سالم
                    </span>
                <?php else: ?>
                    <span class="status-badge status-inactive">
                        <i class="bi bi-x-circle"></i>
                        خطا
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- اطلاعات محیط -->
    <?php if ($envInfo): ?>
        <div class="info-box">
            <h4><?= $envInfo['env_icon'] ?> محیط فعلی: <?= htmlspecialchars($envInfo['env_label']) ?></h4>
            <p style="margin:0;font-size:13.5px;line-height:1.8;color:#334155;">
                <?= htmlspecialchars($envInfo['proxy_hint']) ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- راهنما -->
    <div class="info-box" style="background:#fef3c7;border-right-color:#f59e0b;">
        <h4 style="color:#92400e;">📋 چطور Proxy تنظیم کنیم؟</h4>
        <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
            <strong>روی Termux/موبایل:</strong>
        </p>
        <ol style="margin:0 0 15px;padding-right:20px;line-height:2;font-size:13.5px;color:#334155;">
            <li>یک اپ VPN نصب کنید (مثل <strong>Hiddify</strong>، <strong>V2RayNG</strong>، <strong>NekoBox</strong>)</li>
            <li>در اپ، گزینه <strong>SOCKS5 Proxy</strong> را فعال کنید</li>
            <li>معمولاً روی <code>127.0.0.1:1080</code> یا <code>127.0.0.1:10808</code> اجرا می‌شود</li>
            <li>آدرس زیر را پر کنید: <code>socks5://127.0.0.1:1080</code></li>
        </ol>

        <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
            <strong>روی VPS/سرور:</strong>
        </p>
        <ol style="margin:0;padding-right:20px;line-height:2;font-size:13.5px;color:#334155;">
            <li>Xray یا WireGuard را روی سرور نصب کنید</li>
            <li>یک SOCKS5 محلی راه بیندازید</li>
            <li>آدرس را در فیلد زیر وارد کنید</li>
        </ol>
    </div>

    <!-- فرم Proxy -->
    <div class="form-group">
        <label for="proxyTypeInput">🔧 نوع Proxy</label>
        <select id="proxyTypeInput" class="form-control">
            <option value="socks5" <?= $proxyType === 'socks5' ? 'selected' : '' ?>>
                SOCKS5 (توصیه شده)
            </option>
            <option value="socks5h" <?= $proxyType === 'socks5h' ? 'selected' : '' ?>>
                SOCKS5h (با DNS از طریق proxy)
            </option>
            <option value="http" <?= $proxyType === 'http' ? 'selected' : '' ?>>
                HTTP
            </option>
            <option value="https" <?= $proxyType === 'https' ? 'selected' : '' ?>>
                HTTPS
            </option>
        </select>
    </div>

    <div class="form-group">
        <label for="proxyUrlInput">🌐 آدرس Proxy</label>
        <input type="text"
               id="proxyUrlInput"
               class="form-control"
               dir="ltr"
               placeholder="socks5://127.0.0.1:1080"
               value="<?= htmlspecialchars($proxyUrl) ?>">
        <small>
            مثال‌ها:
            <code>socks5://127.0.0.1:1080</code> —
            <code>socks5://user:pass@host:1080</code> —
            <code>http://proxy.example.com:8080</code>
        </small>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
        <div class="form-group">
            <label for="proxyUserInput">👤 نام کاربری (اختیاری)</label>
            <input type="text"
                   id="proxyUserInput"
                   class="form-control"
                   dir="ltr"
                   value="<?= htmlspecialchars($proxyUser) ?>">
        </div>

        <div class="form-group">
            <label for="proxyPassInput">🔒 رمز عبور (اختیاری)</label>
            <input type="password"
                   id="proxyPassInput"
                   class="form-control"
                   dir="ltr"
                   value="<?= htmlspecialchars($proxyPass) ?>">
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
        <button type="button"
                class="btn btn-primary"
                onclick="saveProxySettings()">
            <i class="bi bi-save"></i>
            💾 ذخیره Proxy
        </button>

        <button type="button"
                class="btn btn-success"
                onclick="testProxy()">
            <i class="bi bi-lightning"></i>
            🔍 تست Proxy
        </button>

        <?php if (!empty($proxyUrl)): ?>
            <button type="button"
                    class="btn btn-danger"
                    onclick="clearProxy()"
                    style="margin-right:auto;">
                <i class="bi bi-trash"></i>
                🗑 حذف Proxy
            </button>
        <?php endif; ?>
    </div>

    <!-- نتیجه تست Proxy -->
    <?php if ($proxyHealth): ?>
        <div class="alert alert-<?= !empty($proxyHealth['ok']) ? 'success' : 'error' ?>" style="margin-top:20px;">
            <i class="bi bi-<?= !empty($proxyHealth['ok']) ? 'check-circle' : 'x-circle' ?>"></i>
            <div>
                <?php if (!empty($proxyHealth['ok'])): ?>
                    <strong>✅ Proxy سالم است</strong>
                    <br>
                    <small>
                        HTTP: <?= $proxyHealth['http'] ?? '-' ?> |
                        زمان: <?= $proxyHealth['elapsed'] ?? '-' ?>s
                    </small>
                <?php else: ?>
                    <strong>❌ Proxy خطا دارد</strong>
                    <br>
                    <small><?= htmlspecialchars($proxyHealth['error'] ?? 'خطای نامشخص') ?></small>
                    <?php if (!empty($proxyHealth['hint'])): ?>
                        <br><small style="color:#92400e;">💡 <?= htmlspecialchars($proxyHealth['hint']) ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- بخش ۳: تنظیمات پیشرفته                                      -->
<!-- ═══════════════════════════════════════════════════════════ -->

<div style="margin-top:40px;padding-top:30px;border-top:2px solid #e2e8f0;">

    <h3 style="margin:0 0 20px;font-size:16px;color:#0f172a;">
        ⚙️ تنظیمات پیشرفته
    </h3>

    <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
            <input type="checkbox"
                   id="debugModeInput"
                   <?= $debugMode ? 'checked' : '' ?>
                   style="width:20px;height:20px;">
            <span>🐛 حالت Debug (ثبت لاگ‌های تفصیلی)</span>
        </label>
        <small>
            با فعال کردن این گزینه، تمام تلاش‌های Fallback در فایل
            <code>cache/ai-gateway.log</code> ثبت می‌شوند.
        </small>
    </div>

    <button type="button"
            class="btn btn-primary"
            onclick="saveDebugMode()">
        <i class="bi bi-save"></i>
        💾 ذخیره
    </button>

</div>

<!-- ═══ Sortable.js برای Drag & Drop ═══ -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
// ═══ Drag & Drop ═══
const chainList = document.getElementById('chainList');
if (chainList && typeof Sortable !== 'undefined') {
    new Sortable(chainList, {
        animation: 200,
        handle: 'li',
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            // به‌روزرسانی شماره‌ها
            chainList.querySelectorAll('li').forEach((li, i) => {
                const numEl = li.querySelector('span:nth-child(2)');
                if (numEl) numEl.textContent = (i + 1) + '.';
            });
        }
    });
}

// ═══ ذخیره Chain ═══
function saveProviderChain() {
    const chain = [];
    chainList.querySelectorAll('li').forEach(li => {
        chain.push(li.dataset.slug);
    });

    const formData = new FormData();
    formData.append('chain', JSON.stringify(chain));

    fetch('<?= $baseAdmin ?>/../ajax/ai_chain_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'ترتیب ذخیره شد'));
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}

// ═══ ذخیره Proxy ═══
function saveProxySettings() {
    const type = document.getElementById('proxyTypeInput').value;
    const url  = document.getElementById('proxyUrlInput').value.trim();
    const user = document.getElementById('proxyUserInput').value;
    const pass = document.getElementById('proxyPassInput').value;

    const formData = new FormData();
    formData.append('proxy_type', type);
    formData.append('proxy_url', url);
    formData.append('proxy_user', user);
    formData.append('proxy_pass', pass);

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'تنظیمات Proxy ذخیره شد'));
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}

// ═══ تست Proxy ═══
function testProxy() {
    const url = document.getElementById('proxyUrlInput').value.trim();
    if (url === '') {
        showAlert('error', 'لطفاً ابتدا آدرس Proxy را وارد و ذخیره کنید');
        return;
    }

    const btn = event.target.closest('button');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> تست...';

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_test.php?provider=proxy')
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'Proxy سالم است'));
        } else {
            showAlert('error', '❌ ' + (data.error || 'Proxy خطا دارد'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = original;
    });
}

// ═══ حذف Proxy ═══
function clearProxy() {
    if (!confirm('آیا مطمئن هستید؟')) return;

    const formData = new FormData();
    formData.append('clear_proxy', '1');

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ Proxy حذف شد');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    });
}

// ═══ ذخیره Debug Mode ═══
function saveDebugMode() {
    const enabled = document.getElementById('debugModeInput').checked ? '1' : '0';

    const formData = new FormData();
    formData.append('debug_mode', enabled);

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_save.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + (data.message || 'ذخیره شد'));
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message));
}
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
    background: #dbeafe !important;
}
</style>

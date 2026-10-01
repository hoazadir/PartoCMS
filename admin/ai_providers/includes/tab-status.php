<?php
/**
 * 🆕 PartoCMS - Tab Status
 * تب نمایش وضعیت کلی همه providerها
 *
 * دسترسی: از طریق admin/ai_providers.php?tab=status
 */

if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ وضعیت providerها ═══
$providerMeta = [
    'ollama'     => ['icon' => '🖥', 'label' => 'Ollama (محلی)', 'key_field' => null],
    'groq'       => ['icon' => '🟢', 'label' => 'Groq', 'key_field' => 'ai_groq_api_key'],
    'gemini'     => ['icon' => '🔵', 'label' => 'Gemini', 'key_field' => 'ai_gemini_api_key'],
    'openrouter' => ['icon' => '🟣', 'label' => 'OpenRouter', 'key_field' => 'ai_openrouter_api_key'],
];

// ═══ وضعیت Circuit Breaker ═══
$breakerStatus = [];
if (class_exists('AICircuitBreaker')) {
    try {
        $breaker = new AICircuitBreaker();
        $breakerStatus = $breaker->getAllStatuses();
    } catch (Throwable $e) {
        $breakerStatus = [];
    }
}

// ═══ وضعیت Proxy ═══
$proxyUrl = $settings['ai_proxy_url'] ?? '';
$proxyHealth = null;
if (class_exists('AIProxyManager') && !empty($proxyUrl)) {
    $proxyHealth = AIProxyManager::testHealth();
}

// ═══ اطلاعات محیط ═══
$envInfo = class_exists('AIEnvironment') ? AIEnvironment::capabilities() : null;

// ═══ Chain ═══
$chainRaw = $settings['ai_provider_chain'] ?? '';
$chain = [];
if (!empty($chainRaw)) {
    $decoded = json_decode($chainRaw, true);
    if (is_array($decoded)) $chain = $decoded;
}
if (empty($chain)) $chain = ['ollama', 'groq', 'gemini', 'openrouter'];
?>

<!-- Header -->
<div class="provider-header">
    <div class="provider-icon">📊</div>
    <div class="provider-title">
        <h2>وضعیت کلی سیستم هوش مصنوعی</h2>
        <p>مرور سریع وضعیت همه providerها، Proxy و Circuit Breaker</p>
    </div>
    <div>
        <button type="button"
                class="btn btn-primary"
                onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i>
            🔄 بروزرسانی
        </button>
    </div>
</div>

<!-- ═══ ۱. کارت‌های Provider ═══ -->
<h3 style="margin:25px 0 15px;font-size:16px;color:#0f172a;">
    🚀 ارائه‌دهندگان
</h3>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;">

    <?php foreach ($providerMeta as $slug => $meta):
        $keyField = $meta['key_field'];
        $hasKey = ($keyField === null) || !empty($settings[$keyField] ?? '');

        $breaker = $breakerStatus[$slug] ?? null;
        $isCooling = !empty($breaker['is_cooling']);

        // وضعیت
        if (!$hasKey && $keyField !== null) {
            $statusText = 'تنظیم نشده';
            $statusClass = 'status-warning';
            $statusIcon = '⚠️';
        } elseif ($isCooling) {
            $statusText = 'Cooldown';
            $statusClass = 'status-inactive';
            $statusIcon = '🧊';
        } else {
            $statusText = 'آماده';
            $statusClass = 'status-active';
            $statusIcon = '✅';
        }

        $isInChain = in_array($slug, $chain, true);
        $chainPosition = $isInChain ? (array_search($slug, $chain, true) + 1) : null;
    ?>
        <div style="background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:20px;position:relative;transition:all 0.2s;">
            <?php if ($chainPosition): ?>
                <div style="position:absolute;top:-10px;right:15px;background:#2563eb;color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;">
                    <?= $chainPosition ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:15px;">
                <div style="font-size:32px;"><?= $meta['icon'] ?></div>
                <div style="flex:1;">
                    <div style="font-weight:bold;font-size:15px;color:#0f172a;">
                        <?= htmlspecialchars($meta['label']) ?>
                    </div>
                    <div style="font-size:11px;color:#64748b;font-family:monospace;direction:ltr;text-align:left;">
                        <?= htmlspecialchars($slug) ?>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <span class="status-badge <?= $statusClass ?>">
                    <?= $statusIcon ?> <?= $statusText ?>
                </span>
            </div>

            <?php if ($isCooling && $breaker): ?>
                <div style="background:#fef2f2;border-radius:6px;padding:8px 12px;font-size:12px;color:#991b1b;margin-bottom:10px;">
                    ⏱ Cooldown: <?= htmlspecialchars($breaker['cooldown_human'] ?? '-') ?>
                    <?php if ($breaker['last_error']): ?>
                        <br>
                        <span style="font-size:11px;color:#7f1d1d;">
                            خطا: <?= htmlspecialchars($breaker['last_error']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($hasKey && !$isCooling): ?>
                <button type="button"
                        class="btn btn-sm btn-success"
                        onclick="testProvider('<?= $slug ?>')"
                        style="width:100%;">
                    <i class="bi bi-lightning"></i>
                    تست اتصال
                </button>
            <?php elseif (!$hasKey && $keyField): ?>
                <a href="?tab=<?= $slug ?>"
                   class="btn btn-sm btn-warning"
                   style="width:100%;text-align:center;justify-content:center;">
                    <i class="bi bi-gear"></i>
                    تنظیم کنید
                </a>
            <?php else: ?>
                <button type="button"
                        class="btn btn-sm btn-secondary"
                        onclick="resetBreaker('<?= $slug ?>')"
                        style="width:100%;">
                    <i class="bi bi-arrow-clockwise"></i>
                    ریست
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

</div>

<!-- ═══ ۲. کارت Proxy ═══ -->
<h3 style="margin:35px 0 15px;font-size:16px;color:#0f172a;">
    🌐 Proxy
</h3>

<?php if (empty($proxyUrl)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <div>
            <strong>Proxy تنظیم نشده است</strong>
            <br>
            <small>
                برای استفاده از Groq، Gemini و OpenAI حتماً نیاز به Proxy دارید.
                <a href="?tab=settings" style="color:#92400e;font-weight:bold;">تنظیم کنید →</a>
            </small>
        </div>
    </div>
<?php else: ?>
    <div style="background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:20px;">
        <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;flex-wrap:wrap;">
            <span style="font-size:24px;">🌐</span>
            <div style="flex:1;font-family:monospace;direction:ltr;font-size:13px;color:#334155;word-break:break-all;">
                <?= htmlspecialchars($proxyUrl) ?>
            </div>

            <?php if ($proxyHealth): ?>
                <?php if (!empty($proxyHealth['ok'])): ?>
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
            <?php endif; ?>
        </div>

        <?php if ($proxyHealth): ?>
            <div class="limits-grid">
                <div class="limit-item">
                    <div class="label">نوع</div>
                    <div class="value"><?= htmlspecialchars($settings['ai_proxy_type'] ?? 'socks5') ?></div>
                </div>
                <div class="limit-item">
                    <div class="label">HTTP Status</div>
                    <div class="value"><?= $proxyHealth['http'] ?? '-' ?></div>
                </div>
                <div class="limit-item">
                    <div class="label">زمان پاسخ</div>
                    <div class="value"><?= $proxyHealth['elapsed'] ?? '-' ?>s</div>
                </div>
            </div>
        <?php endif; ?>

        <div style="display:flex;gap:10px;margin-top:15px;">
            <button type="button"
                    class="btn btn-success"
                    onclick="testProvider('proxy')">
                <i class="bi bi-lightning"></i>
                تست مجدد
            </button>
            <a href="?tab=settings"
               class="btn btn-secondary">
                <i class="bi bi-gear"></i>
                تنظیمات
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- ═══ ۳. اطلاعات محیط ═══ -->
<?php if ($envInfo): ?>
    <h3 style="margin:35px 0 15px;font-size:16px;color:#0f172a;">
        💻 محیط اجرا
    </h3>

    <div style="background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:20px;">
        <div class="limits-grid">
            <div class="limit-item">
                <div class="label">محیط</div>
                <div class="value"><?= $envInfo['env_icon'] ?> <?= htmlspecialchars($envInfo['env_label']) ?></div>
            </div>
            <div class="limit-item">
                <div class="label">سیستم‌عامل</div>
                <div class="value"><?= htmlspecialchars($envInfo['os_family']) ?></div>
            </div>
            <div class="limit-item">
                <div class="label">نسخه PHP</div>
                <div class="value"><?= htmlspecialchars($envInfo['php_version']) ?></div>
            </div>
            <div class="limit-item">
                <div class="label">نصب VPN خودکار</div>
                <div class="value" style="color:<?= $envInfo['can_install_vpn'] ? '#16a34a' : '#dc2626' ?>;">
                    <?= $envInfo['can_install_vpn'] ? '✅ امکان‌پذیر' : '❌ ناممکن' ?>
                </div>
            </div>
            <div class="limit-item">
                <div class="label">Shell Exec</div>
                <div class="value" style="color:<?= $envInfo['has_shell_exec'] ? '#16a34a' : '#dc2626' ?>;">
                    <?= $envInfo['has_shell_exec'] ? '✅ فعال' : '❌ غیرفعال' ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ═══ ۴. دکمه‌های عملیاتی ═══ -->
<h3 style="margin:35px 0 15px;font-size:16px;color:#0f172a;">
    🛠 ابزارها
</h3>

<div style="display:flex;gap:10px;flex-wrap:wrap;">
    <button type="button"
            class="btn btn-warning"
            onclick="resetAllBreakers()">
        <i class="bi bi-arrow-clockwise"></i>
        🔄 ریست همه Circuit Breakerها
    </button>

    <button type="button"
            class="btn btn-secondary"
            onclick="runFullTest()">
        <i class="bi bi-lightning-fill"></i>
        🧪 تست همه Providers
    </button>
</div>

<script>
function testProvider(slug) {
    const btn = event.target.closest('button');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال تست...';

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_test.php?provider=' + slug)
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ ' + slug + ': ' + (data.message || 'اتصال موفق'));
        } else {
            showAlert('error', '❌ ' + slug + ': ' + (data.error || 'خطا'));
        }
    })
    .catch(err => showAlert('error', 'خطای شبکه: ' + err.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = original;
    });
}

function resetBreaker(slug) {
    if (!confirm('Circuit Breaker این provider ریست شود؟')) return;

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_test.php?provider=' + slug + '&action=reset')
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ Circuit Breaker ریست شد');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    });
}

function resetAllBreakers() {
    if (!confirm('همه Circuit Breakerها ریست شوند؟')) return;

    fetch('<?= $baseAdmin ?>/../ajax/ai_provider_test.php?provider=all&action=reset_all')
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showAlert('success', '✅ همه Circuit Breakerها ریست شدند');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', '❌ ' + (data.error || 'خطا'));
        }
    });
}

function runFullTest() {
    const providers = ['ollama', 'groq', 'gemini', 'openrouter'];
    showAlert('info', '🧪 در حال تست همه providers... (ممکن است چند ثانیه طول بکشد)');

    let results = [];

    Promise.all(providers.map(p =>
        fetch('<?= $baseAdmin ?>/../ajax/ai_provider_test.php?provider=' + p)
            .then(r => r.json())
            .then(data => ({ provider: p, ...data }))
            .catch(err => ({ provider: p, ok: false, error: err.message }))
    )).then(all => {
        let msg = '';
        all.forEach(r => {
            const emoji = r.ok ? '✅' : '❌';
            msg += emoji + ' ' + r.provider + ': ' + (r.ok ? (r.message || 'OK') : (r.error || 'FAIL')) + '<br>';
        });

        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-info';
        alertDiv.innerHTML = '<div><strong>نتایج تست:</strong><br>' + msg + '</div>';

        const container = document.querySelector('.tab-content');
        container.insertBefore(alertDiv, container.firstChild);
    });
}
</script>

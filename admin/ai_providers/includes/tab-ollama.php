<?php
/**
 * 🆕 PartoCMS - Tab Ollama
 * تب نمایش اطلاعات Ollama (محلی)
 *
 * ⚠️ این تب فقط نمایش اطلاعات است — هیچ تغییری در تنظیمات Ollama فعلی
 * نمی‌دهد. تنظیمات واقعی همچنان از ai_settings.php مدیریت می‌شود.
 */

if (!defined('PARTO_CMS') && !isset($activeTab)) {
    die('دسترسی غیرمجاز');
}

// ═══ خواندن وضعیت فعلی (فقط خواندن) ═══
$ollamaEndpoint = $settings['ai_endpoint'] ?? 'http://localhost:11434';
$ollamaModel    = $settings['ai_model'] ?? 'qwen2.5:1.5b';
$ollamaTimeout  = $settings['ai_timeout'] ?? '300';
$ollamaEnabled  = ($settings['ai_enabled'] ?? '0') === '1';

// ═══ بررسی اتصال به Ollama (فقط برای نمایش) ═══
$ollamaOnline = false;
$ollamaModels = [];
$ollamaError  = '';

try {
    $ch = curl_init($ollamaEndpoint . '/api/tags');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($code === 200) {
        $data = json_decode($resp, true);
        $ollamaOnline = true;
        $ollamaModels = $data['models'] ?? [];
    } else {
        $ollamaError = "HTTP {$code}";
    }
} catch (Throwable $e) {
    $ollamaError = $e->getMessage();
}
?>

<!-- Header -->
<div class="provider-header">
    <div class="provider-icon">🖥</div>
    <div class="provider-title">
        <h2>Ollama (محلی)</h2>
        <p>مدل‌های هوش مصنوعی که روی همین سرور اجرا می‌شوند</p>
    </div>
    <div>
        <?php if ($ollamaOnline): ?>
            <span class="status-badge status-active">
                <i class="bi bi-check-circle-fill"></i>
                فعال
            </span>
        <?php else: ?>
            <span class="status-badge status-inactive">
                <i class="bi bi-x-circle"></i>
                آفلاین
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- هشدار مهم -->
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>تنظیمات Ollama در جای دیگری است</strong>
        <br>
        <small>
            این تب فقط اطلاعات نمایش می‌دهد. برای تغییر تنظیمات Ollama
            به
            <a href="<?= $baseAdmin ?>/ai_settings.php" style="color:#1e40af;font-weight:bold;">
                صفحه دستیار هوشمند 🤖
            </a>
            بروید.
        </small>
    </div>
</div>

<!-- توضیحات -->
<div class="info-box">
    <h4>💡 Ollama چیست؟</h4>
    <p style="margin:0 0 10px;font-size:13.5px;line-height:1.8;color:#334155;">
        Ollama یک سرویس <strong>کاملاً محلی</strong> است که مدل‌های هوش مصنوعی را
        روی همان سرور شما اجرا می‌کند. این یعنی:
    </p>
    <ul style="margin:10px 0 0;padding-right:20px;font-size:13.5px;line-height:2;color:#334155;">
        <li>✅ <strong>رایگان</strong> — هیچ هزینه‌ای ندارد</li>
        <li>✅ <strong>حریم خصوصی</strong> — داده‌ها از سرور خارج نمی‌شوند</li>
        <li>✅ <strong>بدون نیاز به VPN</strong></li>
        <li>⚠️ <strong>کندتر</strong> — بستگی به منابع سرور دارد</li>
    </ul>
</div>

<!-- وضعیت فعلی -->
<div style="margin-top:25px;padding-top:25px;border-top:2px solid #e2e8f0;">

    <h3 style="margin:0 0 20px;font-size:16px;color:#0f172a;">
        📊 وضعیت فعلی
    </h3>

    <?php if ($ollamaOnline): ?>

        <!-- آمار -->
        <div class="limits-grid" style="margin-bottom:25px;">
            <div class="limit-item">
                <div class="label">🖥 Endpoint</div>
                <div class="value" style="font-size:12px;font-family:monospace;direction:ltr;">
                    <?= htmlspecialchars($ollamaEndpoint) ?>
                </div>
            </div>
            <div class="limit-item">
                <div class="label">🤖 مدل پیش‌فرض</div>
                <div class="value" style="font-size:12px;font-family:monospace;direction:ltr;">
                    <?= htmlspecialchars($ollamaModel) ?>
                </div>
            </div>
            <div class="limit-item">
                <div class="label">⏱ Timeout</div>
                <div class="value"><?= (int)$ollamaTimeout ?>s</div>
            </div>
            <div class="limit-item">
                <div class="label">📦 تعداد مدل</div>
                <div class="value"><?= count($ollamaModels) ?></div>
            </div>
            <div class="limit-item">
                <div class="label">🔌 وضعیت سرویس</div>
                <div class="value" style="color:#16a34a;">
                    <?= $ollamaEnabled ? '✅ فعال' : '❌ غیرفعال' ?>
                </div>
            </div>
        </div>

        <!-- لیست مدل‌ها -->
        <h4 style="margin:20px 0 15px;font-size:14px;color:#334155;">
            📦 مدل‌های نصب‌شده روی سرور
        </h4>

        <?php if (empty($ollamaModels)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                هیچ مدلی نصب نشده. به
                <a href="<?= $baseAdmin ?>/ai_settings.php" style="color:#92400e;">تنظیمات Ollama</a>
                بروید و یک مدل نصب کنید.
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th style="padding:10px;text-align:right;border-bottom:1px solid #e2e8f0;">مدل</th>
                            <th style="padding:10px;text-align:right;border-bottom:1px solid #e2e8f0;">حجم</th>
                            <th style="padding:10px;text-align:right;border-bottom:1px solid #e2e8f0;">خانواده</th>
                            <th style="padding:10px;text-align:right;border-bottom:1px solid #e2e8f0;">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ollamaModels as $m):
                            $name = $m['name'] ?? '';
                            $size = $m['size'] ?? 0;
                            $sizeHuman = $size > 0 ? round($size / 1024 / 1024 / 1024, 2) . ' GB' : '-';
                            $family = $m['details']['family'] ?? '-';
                            $isCurrent = ($name === $ollamaModel || strpos($name, $ollamaModel) !== false);
                        ?>
                            <tr style="<?= $isCurrent ? 'background:#dcfce7;' : '' ?>">
                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-family:monospace;direction:ltr;font-size:12px;">
                                    <?= htmlspecialchars($name) ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                    <?= $sizeHuman ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                    <?= htmlspecialchars($family) ?>
                                </td>
                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                    <?php if ($isCurrent): ?>
                                        <span style="color:#16a34a;font-weight:bold;">✅ فعال</span>
                                    <?php else: ?>
                                        <span style="color:#64748b;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <!-- خطا -->
        <div class="alert alert-error">
            <i class="bi bi-x-circle"></i>
            <div>
                <strong>Ollama آفلاین است</strong>
                <br>
                <small>خطا: <?= htmlspecialchars($ollamaError ?: 'اتصال برقرار نشد') ?></small>
            </div>
        </div>

        <div class="info-box" style="background:#fef2f2;border-right-color:#dc2626;">
            <h4 style="color:#991b1b;">🔧 عیب‌یابی</h4>
            <ol style="margin:0;padding-right:20px;line-height:2;font-size:13.5px;color:#334155;">
                <li>مطمئن شوید Ollama نصب است: <code>ollama --version</code></li>
                <li>مطمئن شوید سرویس در حال اجرا است: <code>ollama serve</code></li>
                <li>
                    Endpoint را چک کنید:
                    <code><?= htmlspecialchars($ollamaEndpoint) ?></code>
                </li>
                <li>
                    برای تغییر تنظیمات به
                    <a href="<?= $baseAdmin ?>/ai_settings.php">تنظیمات Ollama</a>
                    بروید
                </li>
            </ol>
        </div>

    <?php endif; ?>

    <!-- دکمه‌های عملیاتی -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:25px;">
        <button type="button"
                class="btn btn-primary"
                onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i>
            🔄 بررسی مجدد
        </button>

        <button type="button"
                class="btn btn-success"
                onclick="testProvider(event, 'ollama')">
            <i class="bi bi-lightning"></i>
            🔍 تست اتصال
        </button>

        <a href="<?= $baseAdmin ?>/ai_settings.php"
           class="btn btn-secondary">
            <i class="bi bi-gear"></i>
            ⚙️ تنظیمات Ollama
        </a>
    </div>

</div>

<?php
/**
 * 🆕 PartoCMS - AJAX: Save AI Provider Settings
 *
 * ذخیره API Keyها و تنظیمات providerها
 * - API Key هر provider (groq, gemini, openrouter)
 * - مدل و timeout
 * - Proxy settings
 * - Debug mode
 *
 * ⚠️ فقط ذخیره می‌کند — هیچ چیز دیگری را تغییر نمی‌دهد.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

// ═══ بررسی دسترسی ═══
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

// ═══ بررسی متد ═══
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'فقط POST مجاز است']);
    exit;
}

try {
    $pdo = getDB();

    // ═══════════════════════════════════════════════════════════
    // حالت ۱: حذف Proxy
    // ═══════════════════════════════════════════════════════════
    if (!empty($_POST['clear_proxy'])) {
        $keys = ['ai_proxy_url', 'ai_proxy_type', 'ai_proxy_user', 'ai_proxy_pass'];
        foreach ($keys as $k) {
            $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$k]);
        }
        echo json_encode(['ok' => true, 'message' => 'Proxy حذف شد']);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت ۲: ذخیره Debug Mode
    // ═══════════════════════════════════════════════════════════
    if (isset($_POST['debug_mode'])) {
        $value = $_POST['debug_mode'] === '1' ? '1' : '0';
        saveSetting($pdo, 'ai_gateway_debug', $value);
        echo json_encode([
            'ok' => true,
            'message' => $value === '1' ? 'حالت Debug فعال شد' : 'حالت Debug غیرفعال شد',
        ]);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت ۳: ذخیره Proxy
    // ═══════════════════════════════════════════════════════════
    if (isset($_POST['proxy_url'])) {
        $url  = trim($_POST['proxy_url'] ?? '');
        $type = $_POST['proxy_type'] ?? 'socks5';
        $user = trim($_POST['proxy_user'] ?? '');
        $pass = trim($_POST['proxy_pass'] ?? '');

        // اعتبارسنجی نوع
        $allowedTypes = ['socks5', 'socks5h', 'socks4', 'http', 'https'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'socks5';
        }

        // اگر URL خالی است → حذف
        if (empty($url)) {
            foreach (['ai_proxy_url', 'ai_proxy_type', 'ai_proxy_user', 'ai_proxy_pass'] as $k) {
                $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$k]);
            }
            echo json_encode(['ok' => true, 'message' => 'Proxy حذف شد']);
            exit;
        }

        // اعتبارسنجی URL
        if (!preg_match('/^(socks5h?|socks4|https?):\/\/.+/i', $url)) {
            echo json_encode([
                'ok'    => false,
                'error' => 'آدرس Proxy باید با socks5:// یا http:// شروع شود',
            ]);
            exit;
        }

        saveSetting($pdo, 'ai_proxy_url', $url);
        saveSetting($pdo, 'ai_proxy_type', $type);
        saveSetting($pdo, 'ai_proxy_user', $user);
        saveSetting($pdo, 'ai_proxy_pass', $pass);

        // پاک کردن کش health check
        if (class_exists('AIProxyManager')) {
            require_once __DIR__ . '/../includes/AI/AIProxyManager.php';
            AIProxyManager::clearCache();
        }

        echo json_encode([
            'ok' => true,
            'message' => 'تنظیمات Proxy ذخیره شد',
        ]);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت ۴: ذخیره API Key یک provider
    // ═══════════════════════════════════════════════════════════
    $provider = $_POST['provider'] ?? '';

    $allowedProviders = ['groq', 'gemini', 'openrouter', 'openai'];
    if (!in_array($provider, $allowedProviders, true)) {
        echo json_encode(['ok' => false, 'error' => 'Provider نامعتبر']);
        exit;
    }

    // ─── حذف Key ───
    if (!empty($_POST['clear'])) {
        $keyField = 'ai_' . $provider . '_api_key';
        $modelField = 'ai_' . $provider . '_model';
        $timeoutField = 'ai_' . $provider . '_timeout';

        foreach ([$keyField, $modelField, $timeoutField] as $k) {
            $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$k]);
        }

        echo json_encode([
            'ok' => true,
            'message' => 'API Key حذف شد',
        ]);
        exit;
    }

    // ─── ذخیره Key ───
    $apiKey  = trim($_POST['api_key'] ?? '');
    $model   = trim($_POST['model'] ?? '');
    $timeout = (int) ($_POST['timeout'] ?? 60);

    if (empty($apiKey)) {
        echo json_encode(['ok' => false, 'error' => 'API Key نمی‌تواند خالی باشد']);
        exit;
    }

    // اعتبارسنجی اولیه (شکل Key)
    $keyValidation = validateApiKey($provider, $apiKey);
    if (!$keyValidation['ok']) {
        echo json_encode([
            'ok' => false,
            'error' => $keyValidation['error'],
        ]);
        exit;
    }

    // محدود کردن timeout
    if ($timeout < 10) $timeout = 10;
    if ($timeout > 300) $timeout = 300;

    // ذخیره در دیتابیس
    saveSetting($pdo, 'ai_' . $provider . '_api_key', $apiKey);
    saveSetting($pdo, 'ai_' . $provider . '_model', $model);
    saveSetting($pdo, 'ai_' . $provider . '_timeout', (string) $timeout);

    // ─── بروزرسانی AIProviderFactory (اگر لود بود) ───
    // هیچ تغییری نمی‌دهیم — فقط تنظیمات را ذخیره می‌کنیم

    echo json_encode([
        'ok' => true,
        'message' => 'تنظیمات ' . ucfirst($provider) . ' ذخیره شد',
        'data' => [
            'provider' => $provider,
            'model' => $model,
            'timeout' => $timeout,
        ],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ]);
}

// ═══════════════════════════════════════════════════════════
// Helper Functions
// ═══════════════════════════════════════════════════════════

/**
 * ذخیره یا به‌روزرسانی یک setting
 */
function saveSetting(PDO $pdo, string $key, string $value): void
{
    $sql = "INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $pdo->prepare($sql)->execute([$key, $value]);
}

/**
 * اعتبارسنجی شکل API Key هر provider
 */
function validateApiKey(string $provider, string $key): array
{
    switch ($provider) {
        case 'groq':
            if (!preg_match('/^gsk_[a-zA-Z0-9]{20,}$/', $key)) {
                return [
                    'ok' => false,
                    'error' => 'شکل Groq API Key نامعتبر است. باید با gsk_ شروع شود.',
                ];
            }
            break;

        case 'gemini':
            if (!preg_match('/^AIza[a-zA-Z0-9_-]{30,}$/', $key)) {
                return [
                    'ok' => false,
                    'error' => 'شکل Gemini API Key نامعتبر است. باید با AIza شروع شود.',
                ];
            }
            break;

        case 'openrouter':
            if (!preg_match('/^sk-or-v1-[a-zA-Z0-9]{40,}$/', $key)) {
                return [
                    'ok' => false,
                    'error' => 'شکل OpenRouter API Key نامعتبر است. باید با sk-or-v1- شروع شود.',
                ];
            }
            break;

        case 'openai':
            if (!preg_match('/^sk-[a-zA-Z0-9_-]{20,}$/', $key)) {
                return [
                    'ok' => false,
                    'error' => 'شکل OpenAI API Key نامعتبر است. باید با sk- شروع شود.',
                ];
            }
            break;
    }

    return ['ok' => true];
}

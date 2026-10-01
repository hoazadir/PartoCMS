<?php
/**
 * 🆕 PartoCMS - AJAX: Test AI Provider Connection
 *
 * تست اتصال به providerها و Proxy
 * - تست provider (groq, gemini, openrouter, ollama)
 * - تست proxy
 * - ریست Circuit Breaker
 *
 * ⚠️ فقط تست می‌کند — هیچ چیزی را تغییر نمی‌دهد.
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

// ═══ پارامترها ═══
$provider = $_GET['provider'] ?? '';
$action   = $_GET['action'] ?? 'test';

// ═══ لود فایل‌های AI (فقط خواندن) ═══
$aiFiles = [
    'AIEnvironment' => __DIR__ . '/../includes/AI/AIEnvironment.php',
    'AIProxyManager' => __DIR__ . '/../includes/AI/AIProxyManager.php',
    'AICircuitBreaker' => __DIR__ . '/../includes/AI/AICircuitBreaker.php',
    'AIProviderFactory' => __DIR__ . '/../includes/AI/AIProviderFactory.php',
];

foreach ($aiFiles as $name => $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// ═══ بارگذاری Providerها ═══
$providerFiles = [
    'ollama'     => __DIR__ . '/../includes/AI/Providers/LLM/OllamaProvider.php',
    'groq'       => __DIR__ . '/../includes/AI/Providers/LLM/GroqProvider.php',
    'gemini'     => __DIR__ . '/../includes/AI/Providers/LLM/GeminiProvider.php',
    'openrouter' => __DIR__ . '/../includes/AI/Providers/LLM/OpenRouterProvider.php',
];

foreach ($providerFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

try {
    // ═══════════════════════════════════════════════════════════
    // حالت ریست Circuit Breaker
    // ═══════════════════════════════════════════════════════════
    if ($action === 'reset' && !empty($provider) && $provider !== 'all') {
        if (!class_exists('AICircuitBreaker')) {
            echo json_encode(['ok' => false, 'error' => 'AICircuitBreaker در دسترس نیست']);
            exit;
        }

        $breaker = new AICircuitBreaker();
        $breaker->reset($provider);

        echo json_encode([
            'ok' => true,
            'message' => "Circuit Breaker برای {$provider} ریست شد",
        ]);
        exit;
    }

    if ($action === 'reset_all') {
        if (!class_exists('AICircuitBreaker')) {
            echo json_encode(['ok' => false, 'error' => 'AICircuitBreaker در دسترس نیست']);
            exit;
        }

        $breaker = new AICircuitBreaker();
        $breaker->resetAll();

        echo json_encode([
            'ok' => true,
            'message' => 'همه Circuit Breakerها ریست شدند',
        ]);
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت تست Proxy
    // ═══════════════════════════════════════════════════════════
    if ($provider === 'proxy') {
        if (!class_exists('AIProxyManager')) {
            echo json_encode(['ok' => false, 'error' => 'AIProxyManager در دسترس نیست']);
            exit;
        }

        $result = AIProxyManager::testHealth(true);

        if (!empty($result['ok'])) {
            echo json_encode([
                'ok' => true,
                'message' => 'Proxy سالم است (HTTP ' . ($result['http'] ?? '-') . ' در ' . ($result['elapsed'] ?? '-') . 's)',
                'data' => $result,
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'error' => $result['error'] ?? 'Proxy خطا دارد',
                'data' => $result,
            ]);
        }
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // حالت تست Provider
    // ═══════════════════════════════════════════════════════════
    if (empty($provider)) {
        echo json_encode(['ok' => false, 'error' => 'پارامتر provider الزامی است']);
        exit;
    }

    $allowed = ['ollama', 'groq', 'gemini', 'openrouter', 'openai'];
    if (!in_array($provider, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Provider نامعتبر']);
        exit;
    }

    // ساخت provider
    $providerInstance = buildProvider($provider);

    if ($providerInstance === null) {
        echo json_encode([
            'ok' => false,
            'error' => "Provider '{$provider}' قابل ساخت نیست — فایل یا کلاس پیدا نشد",
        ]);
        exit;
    }

    // بررسی proxy برای providerهای تحریم‌شده
    if (class_exists('AIProxyManager')) {
        $needsProxy = AIProxyManager::needsProxy($provider);
        $hasProxy   = AIProxyManager::hasProxy();

        if ($needsProxy && !$hasProxy) {
            echo json_encode([
                'ok' => false,
                'error' => "provider '{$provider}' نیاز به proxy دارد ولی تنظیم نشده",
                'hint' => 'به تب تنظیمات بروید و یک SOCKS5 proxy تنظیم کنید',
            ]);
            exit;
        }
    }

    // تست اتصال
    $start = microtime(true);
    $result = $providerInstance->testConnection();
    $elapsed = round(microtime(true) - $start, 2);

    if (!empty($result['ok'])) {
        $message = "اتصال به {$provider} موفق بود ({$elapsed}s)";

        if (isset($result['models'])) {
            if (is_array($result['models'])) {
                $count = count($result['models']);
                $message .= " — {$count} مدل در دسترس";
            } elseif (is_numeric($result['models'])) {
                $message .= " — {$result['models']} مدل در دسترس";
            }
        }

        echo json_encode([
            'ok' => true,
            'message' => $message,
            'data' => [
                'provider' => $provider,
                'elapsed' => $elapsed,
                'result' => $result,
            ],
        ]);
    } else {
        $error = $result['error'] ?? 'خطای نامشخص';

        // تشخیص نوع خطا
        $hint = '';
        if (stripos($error, 'api key') !== false || stripos($error, '401') !== false) {
            $hint = 'API Key نامعتبر یا منقضی شده است';
        } elseif (stripos($error, '429') !== false) {
            $hint = 'محدودیت Rate Limit — بعداً امتحان کنید';
        } elseif (stripos($error, 'timed out') !== false || stripos($error, 'timeout') !== false) {
            $hint = 'زمان انتظار تمام شد — اتصال شبکه را بررسی کنید';
        } elseif (stripos($error, 'connection') !== false) {
            $hint = 'اتصال برقرار نشد — Proxy یا شبکه را بررسی کنید';
        }

        echo json_encode([
            'ok' => false,
            'error' => $error,
            'hint' => $hint,
            'data' => [
                'provider' => $provider,
                'elapsed' => $elapsed,
            ],
        ]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ]);
}

// ═══════════════════════════════════════════════════════════
// Helper: ساخت Provider با تنظیمات فعلی
// ═══════════════════════════════════════════════════════════

function buildProvider(string $provider): ?object
{
    $config = getProviderConfig($provider);
    $classMap = [
        'ollama'     => 'OllamaProvider',
        'groq'       => 'GroqProvider',
        'gemini'     => 'GeminiProvider',
        'openrouter' => 'OpenRouterProvider',
        'openai'     => 'OpenAIProvider',
    ];

    if (!isset($classMap[$provider])) return null;

    $class = $classMap[$provider];
    if (!class_exists($class)) return null;

    try {
        return new $class($config);
    } catch (Throwable $e) {
        error_log("buildProvider({$provider}) error: " . $e->getMessage());
        return null;
    }
}

function getProviderConfig(string $provider): array
{
    switch ($provider) {
        case 'ollama':
            return [
                'endpoint' => getSetting('ai_endpoint', 'http://localhost:11434'),
                'timeout'  => (int) getSetting('ai_timeout', '300'),
                'model'    => getSetting('ai_model', 'qwen2.5:1.5b'),
            ];

        case 'groq':
            return [
                'api_key' => getSetting('ai_groq_api_key', ''),
                'model'   => getSetting('ai_groq_model', 'llama-3.3-70b-versatile'),
                'timeout' => (int) getSetting('ai_groq_timeout', '60'),
            ];

        case 'gemini':
            return [
                'api_key' => getSetting('ai_gemini_api_key', ''),
                'model'   => getSetting('ai_gemini_model', 'gemini-2.5-flash'),
                'timeout' => (int) getSetting('ai_gemini_timeout', '60'),
            ];

        case 'openrouter':
            return [
                'api_key' => getSetting('ai_openrouter_api_key', ''),
                'model'   => getSetting('ai_openrouter_model', 'meta-llama/llama-3.3-70b-instruct:free'),
                'timeout' => (int) getSetting('ai_openrouter_timeout', '60'),
            ];

        case 'openai':
            return [
                'api_key' => getSetting('ai_openai_key', ''),
                'model'   => getSetting('ai_openai_model', 'gpt-4o-mini'),
                'timeout' => (int) getSetting('ai_openai_timeout', '60'),
            ];

        default:
            return [];
    }
}

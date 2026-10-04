<?php
/**
 * 🆕 PartoCMS - AI Gateway
 *
 * دروازه یکپارچه هوش مصنوعی با Fallback خودکار
 *
 * معماری: Local-First + Cloud Fallback
 *   ۱. Ollama (محلی) — اولویت اول
 *   ۲. Groq (ابری)   — سریع، رایگان
 *   ۳. Gemini (ابری) — باکیفیت، رایگان
 *   ۴. OpenRouter    — تنوع بالا
 *
 * ویژگی‌ها:
 *   - Fallback خودکار روی خطا
 *   - Circuit Breaker (Cooldown)
 *   - Proxy خودکار برای providerهای تحریم‌شده
 *   - سازگار با همه محیط‌ها (Termux, VPS, Cloud)
 *
 * ⚠️ این فایل جدید است — Ollama و AiAssistant دست‌نخورده.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/AIEnvironment.php';
require_once __DIR__ . '/AIProxyManager.php';
require_once __DIR__ . '/AICircuitBreaker.php';
require_once __DIR__ . '/Providers/LLM/LLMProviderInterface.php';

class AIGateway
{
    /** @var array لیست providerهای فعال (به ترتیب اولویت) */
    private array $providers = [];

    /** @var AICircuitBreaker */
    private AICircuitBreaker $breaker;

    /** @var array آمار آخرین اجرا */
    private array $lastRunStats = [];

    /** @var bool حالت دیباگ */
    private bool $debug = false;

    /**
     * Provider Chain پیش‌فرض (اگر در تنظیمات نبود)
     */
    private const DEFAULT_CHAIN = ['openrouter', 'groq', 'gemini', 'ollama'];

    public function __construct(?array $customChain = null)
    {
        $this->breaker = new AICircuitBreaker();
        $this->debug = (bool) (getSetting('ai_gateway_debug', '0') === '1');

        $chain = $customChain ?? $this->loadChain();
        $this->loadProviders($chain);
    }

    // ═══════════════════════════════════════════════════════════
    // API اصلی
    // ═══════════════════════════════════════════════════════════

    /**
     * ارسال پیام با Fallback خودکار
     */
    public function chat(array $messages, array $options = []): array
    {
        if (empty($this->providers)) {
            return ['ok' => false, 'error' => 'هیچ provider فعالی وجود ندارد'];
        }

        $stats = [
            'started_at'    => microtime(true),
            'attempts'      => [],
            'provider_used' => null,
            'final_ok'      => false,
        ];

        $lastError = null;

        foreach ($this->providers as $name => $provider) {
            // چک Circuit Breaker
            if ($this->breaker->isCoolingDown($name)) {
                $status = $this->breaker->getStatus($name);
                $stats['attempts'][] = [
                    'provider' => $name,
                    'skipped'  => true,
                    'reason'   => 'circuit_breaker',
                    'cooldown' => $status['cooldown_human'],
                ];
                $this->log("Skip {$name}: Circuit Breaker ({$status['cooldown_human']})");
                continue;
            }

            // تلاش
            $attempt = [
                'provider' => $name,
                'started'  => microtime(true),
            ];

            try {
                $result = $this->callProvider($provider, $name, $messages, $options);
                $attempt['elapsed'] = round(microtime(true) - $attempt['started'], 2);

                if (!empty($result['ok'])) {
                    // موفق ✅
                    $this->breaker->recordSuccess($name);

                    $attempt['ok'] = true;
                    $stats['attempts'][] = $attempt;
                    $stats['provider_used'] = $name;
                    $stats['final_ok'] = true;

                    $result['provider_used'] = $name;
                    $result['stats'] = $stats;

                    $this->lastRunStats = $stats;
                    $this->log("Success with {$name} in {$attempt['elapsed']}s");

                    return $result;
                }

                // خطا ❌
                $errorType = AICircuitBreaker::classifyError($result);
                $this->breaker->recordFailure($name, $errorType);

                $attempt['ok'] = false;
                $attempt['error'] = $result['error'] ?? 'نامشخص';
                $attempt['error_type'] = $errorType;
                $stats['attempts'][] = $attempt;

                $this->log("Fail {$name} ({$errorType}): " . ($result['error'] ?? ''));

                $lastError = $result['error'] ?? 'خطای نامشخص';

                // اگر خطا از نوع Auth بود، این provider را برای مدت طولانی Cooldown
                if ($errorType === 'auth_error') {
                    $this->log("Auth error on {$name} — cooldown 1 hour");
                }

            } catch (Throwable $e) {
                $errorType = 'unknown';
                $this->breaker->recordFailure($name, $errorType);

                $attempt['ok'] = false;
                $attempt['error'] = $e->getMessage();
                $attempt['error_type'] = $errorType;
                $attempt['elapsed'] = round(microtime(true) - $attempt['started'], 2);
                $stats['attempts'][] = $attempt;

                $lastError = $e->getMessage();
                $this->log("Exception {$name}: " . $e->getMessage());
            }
        }

        // همه‌ی providers خطا دادند
        $stats['elapsed'] = round(microtime(true) - $stats['started_at'], 2);
        $this->lastRunStats = $stats;

        return [
            'ok'    => false,
            'error' => 'همه providers خطا دادند: ' . ($lastError ?: 'نامشخص'),
            'stats' => $stats,
        ];
    }

    /**
     * چت با خروجی JSON
     */
    public function chatJson(array $messages, array $options = []): array
    {
        $options['format'] = 'json';
        $result = $this->chat($messages, $options);

        // اگر پاسخ موفق بود، JSON را پارس کن
        if (!empty($result['ok']) && !empty($result['response'])) {
            $responseText = trim($result['response']);

            // حذف markdown fences اگر بود
            $responseText = preg_replace('/^```(?:json)?\s*/i', '', $responseText);
            $responseText = preg_replace('/\s*```$/i', '', $responseText);
            $responseText = trim($responseText);

            // پارس
            $parsed = json_decode($responseText, true);

            if (is_array($parsed)) {
                $result['data'] = $parsed;
                $result['raw']  = $responseText;
            } else {
                return [
                    'ok'    => false,
                    'error' => 'پاسخ JSON قابل پارس نبود',
                    'raw'   => $responseText,
                    'provider_used' => $result['provider_used'] ?? '?',
                ];
            }
        }

        return $result;
    }

    /**
     * ترجمه سریع
     */
    public function translate(string $text, string $from, string $to): array
    {
        $messages = [
            ['role' => 'system', 'content' => "You are a professional translator. Reply only with the translation, nothing else."],
            ['role' => 'user',   'content' => "Translate from {$from} to {$to}:\n\n{$text}"],
        ];

        return $this->chat($messages, [
            'temperature' => 0.3,
            'num_predict' => 2000,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // فراخوانی یک provider با proxy خودکار
    // ═══════════════════════════════════════════════════════════

    /**
     * @param object $provider
     * @param string $name نام provider (برای proxy check)
     */
    private function callProvider($provider, string $name, array $messages, array $options): array
    {
        // آیا این provider نیاز به proxy دارد؟
        $needsProxy = AIProxyManager::needsProxy($name);
        $hasProxy   = AIProxyManager::hasProxy();

        // اگر نیاز دارد و کاربر تنظیم نکرده → خطا
        if ($needsProxy && !$hasProxy) {
            return [
                'ok'    => false,
                'error' => "provider '{$name}' نیاز به proxy دارد ولی تنظیم نشده. " .
                           "یک SOCKS5 proxy در تنظیمات AI وارد کنید.",
            ];
        }

        // فراخوانی provider (proxy از داخل خود provider یا از Gateway)
        return $provider->chat($messages, $options);
    }

    // ═══════════════════════════════════════════════════════════
    // مدیریت providerها
    // ═══════════════════════════════════════════════════════════

    private function loadChain(): array
    {
        $raw = getSetting('ai_provider_chain', '');

        if (empty($raw)) {
            return self::DEFAULT_CHAIN;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return self::DEFAULT_CHAIN;
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    private function loadProviders(array $chain): void
    {
        foreach ($chain as $name) {
            $provider = $this->makeProvider($name);
            if ($provider !== null) {
                $this->providers[$name] = $provider;
            }
        }
    }

    private function makeProvider(string $name): ?object
    {
        // نگاشت نام → کلاس
        $map = [
            'ollama'     => 'OllamaProvider',
            'groq'       => 'GroqProvider',
            'gemini'     => 'GeminiProvider',
            'openrouter' => 'OpenRouterProvider',
            'openai'     => 'OpenAIProvider',
        ];

        if (!isset($map[$name])) {
            $this->log("Unknown provider: {$name}");
            return null;
        }

        $class = $map[$name];
        $file  = __DIR__ . '/Providers/LLM/' . $class . '.php';

        if (!file_exists($file)) {
            $this->log("Provider file not found: {$file}");
            return null;
        }

        require_once $file;

        if (!class_exists($class)) {
            $this->log("Provider class not found: {$class}");
            return null;
        }

        try {
            // گرفتن config از Factory موجود (بدون تغییر آن)
            $config = $this->getProviderConfig($name);

            return new $class($config);

        } catch (Throwable $e) {
            $this->log("Failed to instantiate {$class}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * گرفتن config یک provider از settings
     * (بدون تغییر AIProviderFactory)
     */
    private function getProviderConfig(string $name): array
    {
        switch ($name) {
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
                    'model'   => getSetting('ai_openrouter_model', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
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

    // ═══════════════════════════════════════════════════════════
    // وضعیت و آمار
    // ═══════════════════════════════════════════════════════════

    /**
     * لیست providerهای فعال
     */
    public function getActiveProviders(): array
    {
        return array_keys($this->providers);
    }

    /**
     * وضعیت کامل همه providerها
     */
    public function getStatus(): array
    {
        $status = [
            'environment' => AIEnvironment::detect(),
            'chain'       => $this->getActiveProviders(),
            'has_proxy'   => AIProxyManager::hasProxy(),
            'providers'   => [],
        ];

        foreach ($this->providers as $name => $provider) {
            $breakerStatus = $this->breaker->getStatus($name);
            $needsProxy = AIProxyManager::needsProxy($name);

            $status['providers'][$name] = [
                'name'        => $name,
                'available'   => true,
                'needs_proxy' => $needsProxy,
                'is_cooling'  => $breakerStatus['is_cooling'],
                'cooldown'    => $breakerStatus['cooldown_human'],
                'failures'    => $breakerStatus['failures'],
                'last_error'  => $breakerStatus['last_error'],
            ];
        }

        return $status;
    }

    /**
     * آمار آخرین اجرا
     */
    public function getLastRunStats(): array
    {
        return $this->lastRunStats;
    }

    /**
     * تست یک provider خاص
     */
    public function testProvider(string $name): array
    {
        if (!isset($this->providers[$name])) {
            return ['ok' => false, 'error' => "Provider '{$name}' در دسترس نیست"];
        }

        $provider = $this->providers[$name];

        if (!method_exists($provider, 'testConnection')) {
            return ['ok' => false, 'error' => 'این provider از تست پشتیبانی نمی‌کند'];
        }

        return $provider->testConnection();
    }

    /**
     * ریست Circuit Breaker
     */
    public function resetBreaker(?string $provider = null): void
    {
        if ($provider === null) {
            $this->breaker->resetAll();
        } else {
            $this->breaker->reset($provider);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════

    private function log(string $message): void
    {
        if (!$this->debug) return;

        $logFile = __DIR__ . '/../../cache/ai-gateway.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

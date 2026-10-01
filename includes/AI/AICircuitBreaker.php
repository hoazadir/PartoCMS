<?php
/**
 * 🆕 PartoCMS - AI Circuit Breaker
 *
 * مدیریت خطاهای providerها و Cooldown خودکار
 * وقتی یک provider خطا می‌دهد، موقتاً غیرفعال می‌شود تا زمانی که recovery کند.
 *
 * الهام از الگوی Circuit Breaker:
 *   - Closed    → عادی
 *   - Open      → خطا خورده، Cooldown فعال
 *   - Half-Open → بعد از Cooldown، تست مجدد
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

class AICircuitBreaker
{
    /** @var string پوشه ذخیره وضعیت */
    private string $storageDir;

    /** @var int مدت Cooldown پیش‌فرض (ثانیه) */
    private const DEFAULT_COOLDOWN = 300; // ۵ دقیقه

    /** @var int حداکثر خطا قبل از باز شدن circuit */
    private const MAX_FAILURES = 3;

    /** @var array کش در حافظه */
    private array $cache = [];

    /**
     * Cooldown برای هر نوع خطا (ثانیه)
     */
    private const COOLDOWN_BY_ERROR = [
        'timeout'      => 300,   // ۵ دقیقه
        'rate_limit'   => 600,   // ۱۰ دقیقه (429)
        'http_5xx'     => 180,   // ۳ دقیقه
        'connection'   => 120,   // ۲ دقیقه
        'auth_error'   => 3600,  // ۱ ساعت (401/403)
        'unknown'      => 300,   // ۵ دقیقه
    ];

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? __DIR__ . '/../../cache/ai-circuit';
        $this->ensureStorageDir();
    }

    // ═══════════════════════════════════════════════════════════
    // API اصلی
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا این provider در Cooldown است؟
     */
    public function isCoolingDown(string $provider): bool
    {
        $state = $this->getState($provider);

        if (empty($state['cooldown_until'])) {
            return false;
        }

        // اگر زمان Cooldown تمام شده → پاک کن
        if (time() >= $state['cooldown_until']) {
            $this->reset($provider);
            return false;
        }

        return true;
    }

    /**
     * ثبت خطا برای یک provider
     */
    public function recordFailure(string $provider, string $errorType = 'unknown'): void
    {
        $state = $this->getState($provider);

        // افزایش شمارنده خطا
        $state['failures'] = ($state['failures'] ?? 0) + 1;
        $state['last_error'] = $errorType;
        $state['last_error_at'] = time();

        // اگر تعداد خطاها به حد نصاب رسید → Cooldown
        if ($state['failures'] >= self::MAX_FAILURES || $this->isHardError($errorType)) {
            $cooldown = self::COOLDOWN_BY_ERROR[$errorType] ?? self::DEFAULT_COOLDOWN;
            $state['cooldown_until'] = time() + $cooldown;
            $state['cooldown_duration'] = $cooldown;
            $state['reason'] = $errorType;
        }

        $this->saveState($provider, $state);
    }

    /**
     * ثبت موفقیت — reset خطاها
     */
    public function recordSuccess(string $provider): void
    {
        $this->reset($provider);
    }

    /**
     * Reset کامل یک provider
     */
    public function reset(string $provider): void
    {
        $this->saveState($provider, [
            'failures'       => 0,
            'cooldown_until' => 0,
            'last_error'     => null,
            'last_error_at'  => null,
            'reason'         => null,
        ]);
    }

    /**
     * Set cooldown مستقیم (برای Fallback)
     */
    public function setCooldown(string $provider, ?int $seconds = null): void
    {
        $state = $this->getState($provider);
        $cooldown = $seconds ?? self::DEFAULT_COOLDOWN;

        $state['cooldown_until'] = time() + $cooldown;
        $state['cooldown_duration'] = $cooldown;

        $this->saveState($provider, $state);
    }

    /**
     * چه زمانی Cooldown تمام می‌شود؟
     */
    public function getCooldownUntil(string $provider): ?int
    {
        $state = $this->getState($provider);

        if (empty($state['cooldown_until'])) {
            return null;
        }

        if (time() >= $state['cooldown_until']) {
            return null;
        }

        return $state['cooldown_until'];
    }

    /**
     * چند ثانیه تا پایان Cooldown باقی مانده؟
     */
    public function getCooldownRemaining(string $provider): int
    {
        $until = $this->getCooldownUntil($provider);
        if ($until === null) return 0;

        return max(0, $until - time());
    }

    /**
     * وضعیت کامل یک provider
     */
    public function getStatus(string $provider): array
    {
        $state = $this->getState($provider);
        $remaining = $this->getCooldownRemaining($provider);

        return [
            'provider'          => $provider,
            'failures'          => $state['failures'] ?? 0,
            'is_cooling'        => $remaining > 0,
            'cooldown_until'    => $state['cooldown_until'] ?? null,
            'cooldown_remaining'=> $remaining,
            'cooldown_human'    => $remaining > 0 ? $this->formatDuration($remaining) : null,
            'last_error'        => $state['last_error'] ?? null,
            'reason'            => $state['reason'] ?? null,
        ];
    }

    /**
     * وضعیت همه providerها
     */
    public function getAllStatuses(): array
    {
        $providers = ['ollama', 'groq', 'gemini', 'openrouter'];
        $statuses = [];

        foreach ($providers as $p) {
            $statuses[$p] = $this->getStatus($p);
        }

        return $statuses;
    }

    /**
     * پاک کردن کل کش و وضعیت
     */
    public function resetAll(): void
    {
        $this->cache = [];
        $files = glob($this->storageDir . '/*.json') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص نوع خطا
    // ═══════════════════════════════════════════════════════════

    /**
     * تشخیص نوع خطا از پیام خطا
     *
     * @param array $result نتیجه‌ی provider که خطا داده
     * @return string نوع خطا (timeout, rate_limit, http_5xx, ...)
     */
    public static function classifyError(array $result): string
    {
        $error = strtolower($result['error'] ?? '');

        // Timeout
        if (strpos($error, 'timed out') !== false ||
            strpos($error, 'timeout') !== false) {
            return 'timeout';
        }

        // Rate Limit
        if (strpos($error, '429') !== false ||
            strpos($error, 'rate limit') !== false ||
            strpos($error, 'too many requests') !== false) {
            return 'rate_limit';
        }

        // Auth Error
        if (strpos($error, '401') !== false ||
            strpos($error, '403') !== false ||
            strpos($error, 'unauthorized') !== false ||
            strpos($error, 'forbidden') !== false ||
            strpos($error, 'api key') !== false) {
            return 'auth_error';
        }

        // HTTP 5xx
        if (preg_match('/http 5\d{2}/', $error)) {
            return 'http_5xx';
        }

        // Connection Error
        if (strpos($error, 'connection refused') !== false ||
            strpos($error, 'could not resolve') !== false ||
            strpos($error, 'connection reset') !== false ||
            strpos($error, 'no route to host') !== false ||
            strpos($error, 'ssl') !== false) {
            return 'connection';
        }

        return 'unknown';
    }

    /**
     * آیا این خطا "سخت" است؟ (بدون نیاز به ۳ خطا → فوراً Cooldown)
     */
    private function isHardError(string $errorType): bool
    {
        return in_array($errorType, ['auth_error', 'rate_limit'], true);
    }

    // ═══════════════════════════════════════════════════════════
    // ذخیره‌سازی
    // ═══════════════════════════════════════════════════════════

    private function getState(string $provider): array
    {
        // چک کش
        if (isset($this->cache[$provider])) {
            return $this->cache[$provider];
        }

        $file = $this->getStateFile($provider);

        if (!file_exists($file)) {
            return $this->cache[$provider] = $this->emptyState();
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return $this->cache[$provider] = $this->emptyState();
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $this->cache[$provider] = $this->emptyState();
        }

        return $this->cache[$provider] = array_merge($this->emptyState(), $data);
    }

    private function saveState(string $provider, array $state): void
    {
        $this->cache[$provider] = $state;
        $file = $this->getStateFile($provider);

        @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function emptyState(): array
    {
        return [
            'failures'          => 0,
            'cooldown_until'    => 0,
            'cooldown_duration' => 0,
            'last_error'        => null,
            'last_error_at'     => null,
            'reason'            => null,
        ];
    }

    private function getStateFile(string $provider): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $provider);
        return $this->storageDir . '/' . $safe . '.json';
    }

    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Helper
    // ═══════════════════════════════════════════════════════════

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return $seconds . ' ثانیه';
        if ($seconds < 3600) return round($seconds / 60) . ' دقیقه';
        return round($seconds / 3600, 1) . ' ساعت';
    }
}

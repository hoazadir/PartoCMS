<?php
/**
 * 🆕 PartoCMS - AI Proxy Manager
 *
 * مدیریت proxy برای دسترسی به providerهای تحریم‌شده
 * روی همه محیط‌ها کار می‌کند: Termux, VPS, Cloud, Shared Host
 *
 * ⚠️ این فایل جدید است — هیچ چیزی را تغییر نمی‌دهد.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 *
 * امکانات:
 *   - تشخیص خودکار نیاز به proxy برای هر provider
 *   - پشتیبانی از SOCKS5, SOCKS4, HTTP, HTTPS proxy
 *   - چک سلامت proxy
 *   - کش نتایج تست
 */

require_once __DIR__ . '/AIEnvironment.php';

class AIProxyManager
{
    /** @var array کش نتایج health check */
    private static array $healthCache = [];

    /** @var int مدت اعتبار کش health check (ثانیه) */
    private const HEALTH_CACHE_TTL = 300;

    // ═══════════════════════════════════════════════════════════
    // لیست providerهای تحریم‌شده
    // ═══════════════════════════════════════════════════════════

    /**
     * Providerهایی که نیاز به proxy دارند (برای کاربران ایرانی)
     */
    private const PROVIDERS_NEEDING_PROXY = [
        'groq'       => true,
        'gemini'     => true,
        'openai'     => true,
        'cerebras'   => true,
        'github'     => true,
        'anthropic'  => true,
        'cohere'     => true,
    ];

    /**
     * Providerهایی که نیازی به proxy ندارند
     */
    private const PROVIDERS_DIRECT = [
        'ollama'     => true,   // محلی
        'openrouter' => false,  // بستگی به provider پشت آن دارد
        'mistral'    => false,  // نیمه
        'sambanova'  => false,  // نامشخص
    ];

    // ═══════════════════════════════════════════════════════════
    // API اصلی
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا این provider نیاز به proxy دارد؟
     */
    public static function needsProxy(string $providerSlug): bool
    {
        // اگر مستقیم است → هرگز
        if (isset(self::PROVIDERS_DIRECT[$providerSlug]) && self::PROVIDERS_DIRECT[$providerSlug] === true) {
            return false;
        }

        // اگر تحریم‌شده است → بله
        return self::PROVIDERS_NEEDING_PROXY[$providerSlug] ?? false;
    }

    /**
     * آیا کاربر proxy تنظیم کرده؟
     */
    public static function hasProxy(): bool
    {
        return !empty(self::getProxyUrl());
    }

    /**
     * گرفتن URL proxy از تنظیمات
     */
    public static function getProxyUrl(): string
    {
        return trim(getSetting('ai_proxy_url', '') ?? '');
    }

    /**
     * گرفتن نوع proxy
     */
    public static function getProxyType(): int
    {
        $type = getSetting('ai_proxy_type', 'socks5');

        switch (strtolower($type)) {
            case 'socks5':
            case 'socks5h':
                return CURLPROXY_SOCKS5_HOSTNAME;
            case 'socks4':
                return CURLPROXY_SOCKS4;
            case 'http':
                return CURLPROXY_HTTP;
            case 'https':
                return CURLPROXY_HTTPS;
            default:
                return CURLPROXY_SOCKS5_HOSTNAME;
        }
    }

    /**
     * گرفتن proxy برای یک provider
     *
     * @return string|null  URL proxy یا null (بدون proxy)
     */
    public static function getProxyFor(string $providerSlug): ?string
    {
        // اگر provider نیاز ندارد → null
        if (!self::needsProxy($providerSlug)) {
            return null;
        }

        // اگر proxy تنظیم نشده → null (و هشدار لاگ)
        $url = self::getProxyUrl();
        if (empty($url)) {
            if (function_exists('error_log')) {
                error_log("AIProxyManager: provider '{$providerSlug}' نیاز به proxy دارد ولی تنظیم نشده");
            }
            return null;
        }

        return $url;
    }

    /**
     * اعمال proxy روی یک cURL handle
     */
    public static function applyToCurl($ch, string $providerSlug): bool
    {
        $proxy = self::getProxyFor($providerSlug);

        if ($proxy === null) {
            return false; // بدون proxy
        }

        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, self::getProxyType());

        // اگر proxy نیاز به auth دارد
        $user = getSetting('ai_proxy_user', '');
        $pass = getSetting('ai_proxy_pass', '');
        if (!empty($user)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' . $pass);
        }

        return true;
    }

    // ═══════════════════════════════════════════════════════════
    // تست سلامت
    // ═══════════════════════════════════════════════════════════

    /**
     * تست سلامت proxy (با کش)
     */
    public static function testHealth(bool $skipCache = false): array
    {
        $cacheKey = 'health_' . md5(self::getProxyUrl());

        // چک کش
        if (!$skipCache && isset(self::$healthCache[$cacheKey])) {
            $cached = self::$healthCache[$cacheKey];
            if (time() - $cached['time'] < self::HEALTH_CACHE_TTL) {
                return $cached['result'];
            }
        }

        $result = self::doHealthCheck();

        self::$healthCache[$cacheKey] = [
            'time'   => time(),
            'result' => $result,
        ];

        return $result;
    }

    /**
     * تست واقعی proxy
     */
    private static function doHealthCheck(): array
    {
        $proxy = self::getProxyUrl();

        if (empty($proxy)) {
            return [
                'ok'    => false,
                'error' => 'Proxy تنظیم نشده است',
                'hint'  => 'برای دسترسی به providerهای تحریم‌شده، یک SOCKS5 proxy تنظیم کنید',
            ];
        }

        // تست با یک endpoint عمومی که در ایران تحریم است
        $testUrl = 'https://api.groq.com/openai/v1/models';
        $start   = microtime(true);

        $ch = curl_init($testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_PROXY          => $proxy,
            CURLOPT_PROXYTYPE      => self::getProxyType(),
        ]);

        // proxy auth
        $user = getSetting('ai_proxy_user', '');
        $pass = getSetting('ai_proxy_pass', '');
        if (!empty($user)) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' . $pass);
        }

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $elapsed = round(microtime(true) - $start, 2);

        // نتیجه: 200 (کار می‌کند) یا 401 (proxy کار می‌کند ولی API key نداریم)
        if ($code === 200 || $code === 401) {
            return [
                'ok'       => true,
                'http'     => $code,
                'elapsed'  => $elapsed,
                'provider' => 'groq',
                'message'  => 'Proxy سالم است و به Groq دسترسی دارد',
            ];
        }

        // خطاها
        if ($code === 0) {
            return [
                'ok'      => false,
                'error'   => 'اتصال برقرار نشد',
                'details' => $err,
                'elapsed' => $elapsed,
                'hint'    => 'آیا proxy فعال است؟ آدرس و پورت را چک کنید',
            ];
        }

        if ($code === 403) {
            return [
                'ok'      => false,
                'error'   => 'دسترسی رد شد (403)',
                'http'    => $code,
                'elapsed' => $elapsed,
                'hint'    => 'Proxy به سایت مقصد دسترسی ندارد — احتمالاً IP proxy هم تحریم است',
            ];
        }

        return [
            'ok'      => false,
            'error'   => "HTTP {$code}",
            'http'    => $code,
            'details' => $err,
            'elapsed' => $elapsed,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helper برای دیباگ
    // ═══════════════════════════════════════════════════════════

    /**
     * وضعیت کامل proxy
     */
    public static function status(): array
    {
        $env = AIEnvironment::detect();
        $strategy = AIEnvironment::getProxyStrategy();

        return [
            'environment'     => $env,
            'environment_label' => $strategy['label'],
            'has_proxy'       => self::hasProxy(),
            'proxy_url'       => self::getProxyUrl() ?: '(تنظیم نشده)',
            'proxy_type'      => getSetting('ai_proxy_type', 'socks5'),
            'has_auth'        => !empty(getSetting('ai_proxy_user', '')),
            'recommended_method' => $strategy['method'],
            'hint'            => $strategy['hint'],
            'health'          => self::testHealth(),
        ];
    }

    /**
     * پاک کردن کش
     */
    public static function clearCache(): void
    {
        self::$healthCache = [];
    }
}

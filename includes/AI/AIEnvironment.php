<?php
/**
 * 🆕 PartoCMS - AI Environment Detector
 *
 * تشخیص محیط اجرا (Termux, VPS, Cloud, ...) و قابلیت‌های آن
 * این فایل هیچ چیزی را تغییر نمی‌دهد — فقط اطلاعات جمع‌آوری می‌کند.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 *
 * محیط‌های پشتیبانی‌شده:
 *   - termux        : Android / Termux (توسعه)
 *   - shared_host   : هاست اشتراکی
 *   - vps           : VPS با sudo
 *   - dedicated     : سرور اختصاصی (root)
 *   - cloud         : AWS/GCP/Azure
 *   - localhost     : لوکال (توسعه)
 *   - unknown       : نامشخص
 */

class AIEnvironment
{
    // ═══════════════════════════════════════════════════════════
    // ثابت‌های محیط
    // ═══════════════════════════════════════════════════════════
    const ENV_TERMUX      = 'termux';
    const ENV_SHARED_HOST = 'shared_host';
    const ENV_VPS         = 'vps';
    const ENV_DEDICATED   = 'dedicated';
    const ENV_CLOUD       = 'cloud';
    const ENV_LOCALHOST   = 'localhost';
    const ENV_UNKNOWN     = 'unknown';

    /** @var string|null کش نتیجه تشخیص */
    private static ?string $cachedEnv = null;

    // ═══════════════════════════════════════════════════════════
    // تشخیص محیط
    // ═══════════════════════════════════════════════════════════

    /**
     * تشخیص محیط فعلی
     */
    public static function detect(): string
    {
        if (self::$cachedEnv !== null) {
            return self::$cachedEnv;
        }

        // ۱. Termux (Android)
        if (self::isTermux()) {
            return self::$cachedEnv = self::ENV_TERMUX;
        }

        // ۲. Cloud providers (AWS, GCP, Azure, ...)
        if (self::isCloud()) {
            return self::$cachedEnv = self::ENV_CLOUD;
        }

        // ۳. سرور اختصاصی (root)
        if (self::isRoot()) {
            return self::$cachedEnv = self::ENV_DEDICATED;
        }

        // ۴. VPS (sudo در دسترس)
        if (self::hasSudo()) {
            return self::$cachedEnv = self::ENV_VPS;
        }

        // ۵. هاست اشتراکی (مشخصه‌های هاستینگ)
        if (self::isSharedHost()) {
            return self::$cachedEnv = self::ENV_SHARED_HOST;
        }

        // ۶. لوکال (development)
        if (self::isLocalhost()) {
            return self::$cachedEnv = self::ENV_LOCALHOST;
        }

        return self::$cachedEnv = self::ENV_UNKNOWN;
    }

    // ═══════════════════════════════════════════════════════════
    // تست‌های تشخیص
    // ═══════════════════════════════════════════════════════════

    private static function isTermux(): bool
    {
        // ۱. متغیر محیطی TERMUX_VERSION
        if (getenv('TERMUX_VERSION')) {
            return true;
        }

        // ۲. وجود پوشه مخصوص Termux
        if (is_dir('/data/data/com.termux')) {
            return true;
        }

        // ۳. مسیر PREFIX
        $prefix = getenv('PREFIX');
        if ($prefix && strpos($prefix, 'com.termux') !== false) {
            return true;
        }

        // ۴. پوشه home در Termux
        $home = getenv('HOME') ?: '';
        if (strpos($home, 'com.termux') !== false) {
            return true;
        }

        return false;
    }

    private static function isCloud(): bool
    {
        // AWS
        if (getenv('AWS_REGION') || getenv('AWS_EXECUTION_ENV')) {
            return true;
        }

        // Google Cloud
        if (getenv('GOOGLE_CLOUD_PROJECT') || getenv('GCLOUD_PROJECT')) {
            return true;
        }

        // Azure
        if (getenv('WEBSITE_SITE_NAME') || getenv('AZURE_FUNCTIONS_ENVIRONMENT')) {
            return true;
        }

        // DigitalOcean
        if (getenv('DIGITALOCEAN_REGION')) {
            return true;
        }

        return false;
    }

    private static function isRoot(): bool
    {
        if (!function_exists('posix_getuid')) {
            return false;
        }
        return posix_getuid() === 0;
    }

    private static function hasSudo(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        // چک disable_functions
        $disabled = ini_get('disable_functions') ?: '';
        if (stripos($disabled, 'shell_exec') !== false) {
            return false;
        }

        // فقط روی Linux/Mac
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            return false;
        }

        $result = @shell_exec('sudo -n true 2>/dev/null');
        return $result === '' || $result === null;
    }

    private static function isSharedHost(): bool
    {
        $hostname = gethostname() ?: '';
        $hostLower = strtolower($hostname);

        $sharedHostHints = [
            'hostinger', 'cpanel', 'directadmin', 'plesk',
            'godaddy', 'bluehost', 'siteground', 'hostgator',
            'dreamhost', 'namecheap', 'arvancloud', 'irandns',
            'parspack', 'mihanwebhost', 'liara',
        ];

        foreach ($sharedHostHints as $hint) {
            if (strpos($hostLower, $hint) !== false) {
                return true;
            }
        }

        // اگر root/sudo نداشت و Termux/Cloud نبود
        return !self::isRoot() && !self::hasSudo() && !self::isTermux();
    }

    private static function isLocalhost(): bool
    {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';

        $localHints = ['localhost', '127.0.0.1', '::1', '.local', '.test'];
        foreach ($localHints as $hint) {
            if (stripos($serverName, $hint) !== false) return true;
            if (stripos($serverAddr, $hint) !== false) return true;
        }

        // IP خصوصی
        if ($serverAddr && filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════════
    // قابلیت‌ها
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا این محیط می‌تواند VPN خودکار نصب کند؟
     */
    public static function canInstallVPN(): bool
    {
        $env = self::detect();
        return in_array($env, [
            self::ENV_VPS,
            self::ENV_DEDICATED,
            self::ENV_CLOUD,
        ], true);
    }

    /**
     * آیا این محیط می‌تواند Ollama را اجرا کند؟
     */
    public static function canRunOllama(): bool
    {
        // Ollama روی همه جا قابل اجرا است (اگر منابع کافی)
        // فقط Cloud ممکن است منابع کافی نداشته باشد
        return true;
    }

    /**
     * آیا shell_exec در دسترس است؟
     */
    public static function hasShellExec(): bool
    {
        if (!function_exists('shell_exec')) return false;

        $disabled = ini_get('disable_functions') ?: '';
        return stripos($disabled, 'shell_exec') === false;
    }

    /**
     * استراتژی proxy پیشنهادی برای این محیط
     *
     * @return array{env: string, method: string, auto_vpn: bool, can_install: bool, hint: string, recommend: array}
     */
    public static function getProxyStrategy(): array
    {
        $env = self::detect();

        switch ($env) {
            case self::ENV_TERMUX:
                return [
                    'env'         => $env,
                    'label'       => 'Android / Termux',
                    'icon'        => '📱',
                    'method'      => 'socks5_manual',
                    'auto_vpn'    => false,
                    'can_install' => false,
                    'hint'        => 'روی Termux، از یک اپ VPN (Hiddify, V2RayNG, ...) + SOCKS5 محلی استفاده کنید',
                    'recommend'   => ['socks5', 'http_proxy'],
                ];

            case self::ENV_SHARED_HOST:
                return [
                    'env'         => $env,
                    'label'       => 'هاست اشتراکی',
                    'icon'        => '🌐',
                    'method'      => 'socks5_external',
                    'auto_vpn'    => false,
                    'can_install' => false,
                    'hint'        => 'روی هاست اشتراکی، از یک SOCKS5 proxy خارجی استفاده کنید',
                    'recommend'   => ['socks5'],
                ];

            case self::ENV_VPS:
            case self::ENV_DEDICATED:
            case self::ENV_CLOUD:
                return [
                    'env'         => $env,
                    'label'       => 'VPS / سرور',
                    'icon'        => '🖥',
                    'method'      => 'wireguard_auto',
                    'auto_vpn'    => true,
                    'can_install' => true,
                    'hint'        => 'روی سرور، WireGuard یا Xray نصب و مدیریت کنید',
                    'recommend'   => ['wireguard', 'xray', 'socks5'],
                ];

            case self::ENV_LOCALHOST:
                return [
                    'env'         => $env,
                    'label'       => 'لوکال (توسعه)',
                    'icon'        => '💻',
                    'method'      => 'direct',
                    'auto_vpn'    => false,
                    'can_install' => false,
                    'hint'        => 'محیط توسعه — معمولاً proxy لازم نیست',
                    'recommend'   => ['direct', 'socks5'],
                ];

            default:
                return [
                    'env'         => self::ENV_UNKNOWN,
                    'label'       => 'نامشخص',
                    'icon'        => '❓',
                    'method'      => 'socks5_manual',
                    'auto_vpn'    => false,
                    'can_install' => false,
                    'hint'        => 'محیط نامشخص — از SOCKS5 دستی استفاده کنید',
                    'recommend'   => ['socks5'],
                ];
        }
    }

    /**
     * لیست کامل قابلیت‌های محیط
     */
    public static function capabilities(): array
    {
        $strategy = self::getProxyStrategy();

        return [
            'env'                 => self::detect(),
            'env_label'           => $strategy['label'],
            'env_icon'            => $strategy['icon'],
            'can_run_ollama'      => self::canRunOllama(),
            'can_install_vpn'     => self::canInstallVPN(),
            'can_use_socks5'      => true,
            'can_use_http_proxy'  => true,
            'has_shell_exec'      => self::hasShellExec(),
            'has_sudo'            => self::hasSudo(),
            'is_root'             => self::isRoot(),
            'os_family'           => PHP_OS_FAMILY,
            'php_version'         => PHP_VERSION,
            'hostname'            => gethostname() ?: '',
            'proxy_method'        => $strategy['method'],
            'proxy_hint'          => $strategy['hint'],
            'recommend'           => $strategy['recommend'],
        ];
    }
}

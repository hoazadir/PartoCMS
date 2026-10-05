<?php
/**
 * 🆕 PartoCMS Smart Installer - Requirement Checker
 *
 * بررسی پیش‌نیازهای نصب:
 *   - نسخه PHP
 *   - افزونه‌های PHP
 *   - دسترسی‌ها (permissions)
 *   - توابع PHP
 *   - فضا و منابع
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

class RequirementChecker
{
    /** @var array نتایج بررسی */
    private array $results = [];

    /**
     * اجرای همه بررسی‌ها
     */
    public function checkAll(): array
    {
        $this->results = [
            'environment'    => $this->checkEnvironment(),
            'php_version'    => $this->checkPhpVersion(),
            'extensions'     => $this->checkExtensions(),
            'permissions'    => $this->checkPermissions(),
            'functions'      => $this->checkFunctions(),
            'database'       => $this->checkDatabase(),
            'disk_space'     => $this->checkDiskSpace(),
        ];

        return $this->results;
    }

    /**
     * آیا همه چیز OK است؟
     */
    public function allPassed(): bool
    {
        foreach ($this->results as $category => $items) {
            foreach ($items as $item) {
                if (isset($item['status']) && $item['status'] === 'fail') {
                    return false;
                }
                if (isset($item['required']) && $item['required'] === true
                    && isset($item['status']) && $item['status'] === 'warn') {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * نتیجه یک بخش
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * خلاصه آماری
     */
    public function getSummary(): array
    {
        $ok = 0; $warn = 0; $fail = 0;

        foreach ($this->results as $category => $items) {
            foreach ($items as $item) {
                $status = $item['status'] ?? 'info';
                if ($status === 'ok') $ok++;
                elseif ($status === 'warn') $warn++;
                elseif ($status === 'fail') $fail++;
            }
        }

        return [
            'ok'   => $ok,
            'warn' => $warn,
            'fail' => $fail,
            'total' => $ok + $warn + $fail,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // بررسی‌ها
    // ═══════════════════════════════════════════════════════════

    /**
     * 🆕 تشخیص محیط اجرا (Termux/VPS/Cloud/Shared/Personal)
     */
    private function checkEnvironment(): array
    {
        $results = [];

        // تلاش برای بارگذاری AIEnvironment
        $aiEnvFile = dirname(__DIR__, 2) . '/includes/AI/AIEnvironment.php';
        $aiEnvLoaded = false;

        if (file_exists($aiEnvFile)) {
            require_once $aiEnvFile;
            $aiEnvLoaded = class_exists('AIEnvironment');
        }

        if (!$aiEnvLoaded) {
            return [[
                'name'     => 'محیط اجرا',
                'current'  => 'نامشخص',
                'required' => '—',
                'status'   => 'warn',
                'message'  => "⚠️ AIEnvironment موجود نیست — تشخیص محیط غیرفعال",
            ]];
        }

        try {
            $env = AIEnvironment::detect();
            $strategy = AIEnvironment::getProxyStrategy();
            $caps = AIEnvironment::capabilities();

            // ─── ۱. محیط ───
            $results[] = [
                'name'     => 'محیط اجرا',
                'current'  => $strategy['label'],
                'required' => '—',
                'status'   => 'ok',
                'message'  => "✅ محیط: {$strategy['icon']} {$strategy['label']} ({$env})",
            ];

            // ─── ۲. سیستم‌عامل ───
            $os  = $caps['os_family'] ?? 'Unknown';
            $php = $caps['php_version'] ?? PHP_VERSION;
            $results[] = [
                'name'     => 'سیستم‌عامل',
                'current'  => $os,
                'required' => '—',
                'status'   => 'ok',
                'message'  => "✅ سیستم‌عامل: {$os} | PHP: {$php}",
            ];

            // ─── ۳. shell_exec ───
            $hasShell = $caps['has_shell_exec'] ?? false;
            $results[] = [
                'name'     => 'shell_exec()',
                'current'  => $hasShell ? 'فعال' : 'غیرفعال',
                'required' => 'اختیاری',
                'status'   => $hasShell ? 'ok' : 'warn',
                'message'  => $hasShell
                    ? "✅ shell_exec فعال — Ollama و VPN خودکار"
                    : "⚠️ shell_exec غیرفعال — Ollama و WireGuard/Xray ممکن نیست",
            ];

            // ─── ۴. root/sudo ───
            $isRoot  = $caps['is_root']  ?? false;
            $hasSudo = $caps['has_sudo'] ?? false;

            if ($isRoot) {
                $accessLabel  = 'root (کامل)';
                $accessStatus = 'ok';
                $accessMsg    = "✅ دسترسی root — نصب VPN خودکار ممکن است";
            } elseif ($hasSudo) {
                $accessLabel  = 'sudo';
                $accessStatus = 'ok';
                $accessMsg    = "✅ دسترسی sudo — نصب VPN خودکار ممکن است";
            } else {
                $accessLabel  = 'کاربر عادی';
                $accessStatus = 'warn';
                $accessMsg    = "⚠️ بدون root/sudo — فقط SOCKS5 proxy دستی";
            }

            $results[] = [
                'name'     => 'دسترسی',
                'current'  => $accessLabel,
                'required' => 'اختیاری',
                'status'   => $accessStatus,
                'message'  => $accessMsg,
            ];

            // ─── ۵. نصب VPN خودکار ───
            $canVPN = $caps['can_install_vpn'] ?? false;
            $results[] = [
                'name'     => 'نصب VPN خودکار',
                'current'  => $canVPN ? 'امکان‌پذیر' : 'ناممکن',
                'required' => 'اختیاری',
                'status'   => $canVPN ? 'ok' : 'warn',
                'message'  => $canVPN
                    ? "✅ امکان نصب WireGuard/Xray"
                    : "ℹ️ روی این محیط VPN خودکار ممکن نیست (نیاز به root)",
            ];

            // ─── ۶. Ollama ───
            $ollamaAvailable = $this->checkOllama();
            $results[] = [
                'name'     => 'Ollama',
                'current'  => $ollamaAvailable ? 'نصب شده' : 'نصب نشده',
                'required' => 'اختیاری',
                'status'   => $ollamaAvailable ? 'ok' : 'warn',
                'message'  => $ollamaAvailable
                    ? "✅ Ollama در حال اجرا است"
                    : "ℹ️ Ollama نصب نیست — می‌توانید از AI ابری استفاده کنید",
            ];

            // ─── ۷. استراتژی Proxy ───
            $proxyHint = $strategy['hint'] ?? '';
            if ($proxyHint) {
                $results[] = [
                    'name'     => 'استراتژی Proxy',
                    'current'  => $strategy['method'] ?? 'direct',
                    'required' => '—',
                    'status'   => 'info',
                    'message'  => "ℹ️ {$proxyHint}",
                ];
            }

        } catch (Throwable $e) {
            $results[] = [
                'name'     => 'محیط اجرا',
                'current'  => 'خطا',
                'required' => '—',
                'status'   => 'warn',
                'message'  => "⚠️ خطا در تشخیص محیط: " . $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * 🆕 بررسی سریع Ollama
     */
    private function checkOllama(): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        try {
            $ch = curl_init('http://localhost:11434/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_CONNECTTIMEOUT => 1,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close() در PHP 8+ deprecated است و لازم نیست

            return $code === 200;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * نسخه PHP
     */
    private function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $required = '8.0.0';

        if (version_compare($current, $required, '>=')) {
            return [[
                'name' => 'نسخه PHP',
                'current' => $current,
                'required' => ">= {$required}",
                'status' => 'ok',
                'message' => "✅ نسخه {$current} پشتیبانی می‌شود",
            ]];
        }

        return [[
            'name' => 'نسخه PHP',
            'current' => $current,
            'required' => ">= {$required}",
            'status' => 'fail',
            'message' => "❌ نسخه PHP قدیمی است. حداقل {$required} لازم است",
        ]];
    }

    /**
     * افزونه‌های PHP
     */
    private function checkExtensions(): array
    {
        $required = [
            'pdo'         => 'اتصال به دیتابیس',
            'pdo_mysql'   => 'اتصال به MySQL/MariaDB',
            'mbstring'    => 'پشتیبانی UTF-8',
            'json'        => 'پارس JSON',
            'curl'        => 'درخواست‌های HTTP',
            'openssl'     => 'رمزنگاری',
            'gd'          => 'پردازش تصویر',
            'zip'         => 'فایل‌های Zip',
            'fileinfo'    => 'تشخیص نوع فایل',
        ];

        $optional = [
            'intl'        => 'پشتیبانی بین‌المللی',
            'imagick'     => 'پردازش تصویر پیشرفته',
            'exif'        => 'متادیتای تصویر',
        ];

        $results = [];

        foreach ($required as $ext => $desc) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => $ext,
                'current' => $loaded ? 'نصب' : 'نصب نشده',
                'required' => 'لازم',
                'status' => $loaded ? 'ok' : 'fail',
                'required_flag' => true,
                'message' => $loaded
                    ? "✅ {$ext} ({$desc})"
                    : "❌ {$ext} نصب نیست — {$desc}",
            ];
        }

        foreach ($optional as $ext => $desc) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => $ext,
                'current' => $loaded ? 'نصب' : 'نصب نشده',
                'required' => 'اختیاری',
                'status' => $loaded ? 'ok' : 'warn',
                'required_flag' => false,
                'message' => $loaded
                    ? "✅ {$ext} ({$desc})"
                    : "⚠️ {$ext} نصب نیست — {$desc} (اختیاری)",
            ];
        }

        return $results;
    }

    /**
     * دسترسی‌ها (permissions)
     */
    private function checkPermissions(): array
    {
        $root = dirname(__DIR__, 2);

        $paths = [
            $root => 'پوشه اصلی (config.php)',
            $root . '/logs' => 'پوشه logs',
            $root . '/logs/sessions' => 'پوشه sessions',
            $root . '/assets/uploads' => 'پوشه آپلود',
            $root . '/assets/uploads/ads' => 'پوشه تبلیغات',
            $root . '/backups' => 'پوشه بکاپ',
        ];

        $results = [];

        foreach ($paths as $path => $desc) {
            if (!file_exists($path)) {
                // تلاش برای ساخت
                $created = @mkdir($path, 0755, true);
                if ($created) {
                    $results[] = [
                        'name' => basename($path),
                        'current' => 'ساخته شد',
                        'required' => 'قابل نوشتن',
                        'status' => 'ok',
                        'message' => "✅ {$desc} — ساخته شد",
                    ];
                } else {
                    $results[] = [
                        'name' => basename($path),
                        'current' => 'وجود ندارد',
                        'required' => 'قابل نوشتن',
                        'status' => 'fail',
                        'message' => "❌ {$desc} وجود ندارد و قابل ساخت نیست",
                    ];
                }
                continue;
            }

            $writable = is_writable($path);
            $results[] = [
                'name' => basename($path),
                'current' => $writable ? 'قابل نوشتن' : 'فقط خواندنی',
                'required' => 'قابل نوشتن',
                'status' => $writable ? 'ok' : 'fail',
                'message' => $writable
                    ? "✅ {$desc} — قابل نوشتن"
                    : "❌ {$desc} — قابل نوشتن نیست (chmod 755)",
            ];
        }

        // config.php
        $configPath = $root . '/config.php';
        if (file_exists($configPath)) {
            $results[] = [
                'name' => 'config.php',
                'current' => 'وجود دارد',
                'required' => 'قابل نوشتن',
                'status' => is_writable($configPath) ? 'ok' : 'warn',
                'message' => is_writable($configPath)
                    ? "✅ config.php قابل نوشتن است"
                    : "⚠️ config.php قابل نوشتن نیست — Installer نمی‌تواند آن را بروز کند",
            ];
        } else {
            $results[] = [
                'name' => 'config.php',
                'current' => 'وجود ندارد',
                'required' => 'لازم',
                'status' => 'warn',
                'message' => "⚠️ config.php وجود ندارد — از example ساخته می‌شود",
            ];
        }

        return $results;
    }

    /**
     * توابع PHP
     */
    private function checkFunctions(): array
    {
        $required = [
            'file_put_contents',
            'file_get_contents',
            'json_encode',
            'json_decode',
            'curl_init',
            'mb_strlen',
            'preg_match',
            'password_hash',
            'hash',
            'random_bytes',
        ];

        $results = [];

        foreach ($required as $func) {
            $exists = function_exists($func);
            $results[] = [
                'name' => $func,
                'current' => $exists ? 'موجود' : 'غیرفعال',
                'required' => 'لازم',
                'status' => $exists ? 'ok' : 'fail',
                'message' => $exists
                    ? "✅ {$func}()"
                    : "❌ {$func}() غیرفعال است",
            ];
        }

        // shell_exec (اختیاری برای Ollama)
        $shellExec = function_exists('shell_exec')
            && !in_array('shell_exec', explode(',', ini_get('disable_functions') ?: ''));

        $results[] = [
            'name' => 'shell_exec',
            'current' => $shellExec ? 'موجود' : 'غیرفعال',
            'required' => 'اختیاری (Ollama)',
            'status' => $shellExec ? 'ok' : 'warn',
            'message' => $shellExec
                ? "✅ shell_exec() — برای Ollama"
                : "⚠️ shell_exec() غیرفعال — Ollama ممکن است کار نکند",
        ];

        return $results;
    }

    /**
     * دیتابیس
     */
    private function checkDatabase(): array
    {
        $results = [];

        // بررسی اتصال
        try {
            $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
            $user = defined('DB_USER') ? DB_USER : '';
            $pass = defined('DB_PASS') ? DB_PASS : '';
            $name = defined('DB_NAME') ? DB_NAME : '';

            $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $results[] = [
                'name' => 'اتصال MySQL',
                'current' => 'موفق',
                'required' => 'لازم',
                'status' => 'ok',
                'message' => "✅ اتصال به MySQL موفق",
            ];

            // بررسی دیتابیس
            if ($name) {
                $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name));
                $exists = $stmt->fetchColumn();

                $results[] = [
                    'name' => 'دیتابیس',
                    'current' => $exists ? 'وجود دارد' : 'وجود ندارد',
                    'required' => $name,
                    'status' => 'ok',
                    'message' => $exists
                        ? "✅ دیتابیس «{$name}» موجود است"
                        : "ℹ️ دیتابیس «{$name}» ساخته می‌شود",
                ];
            }

        } catch (PDOException $e) {
            $results[] = [
                'name' => 'اتصال MySQL',
                'current' => 'خطا',
                'required' => 'لازم',
                'status' => 'fail',
                'message' => "❌ اتصال ناموفق: " . $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * فضای دیسک
     */
    private function checkDiskSpace(): array
    {
        $root = dirname(__DIR__, 2);
        $free = @disk_free_space($root);
        $total = @disk_total_space($root);

        if ($free === false) {
            return [[
                'name' => 'فضای دیسک',
                'current' => 'نامشخص',
                'required' => '>= 100 MB',
                'status' => 'warn',
                'message' => "⚠️ فضای دیسک قابل خواندن نیست",
            ]];
        }

        $freeMB = round($free / 1024 / 1024, 1);
        $minMB = 100;

        return [[
            'name' => 'فضای دیسک',
            'current' => "{$freeMB} MB آزاد",
            'required' => ">= {$minMB} MB",
            'status' => $freeMB >= $minMB ? 'ok' : 'warn',
            'message' => $freeMB >= $minMB
                ? "✅ {$freeMB} MB آزاد"
                : "⚠️ فقط {$freeMB} MB آزاد — حداقل {$minMB} MB توصیه می‌شود",
        ]];
    }
}

<?php
/**
 * PartoCMS - Environment Detection
 * تشخیص خودکار محیط اجرا برای انتخاب روش مناسب
 *
 * @version 1.0
 * @date 2026-09-16
 */
class Environment {

    /**
     * آیا shell_exec کار می‌کنه؟
     */
    public static function hasShellExec(): bool {
        if (!function_exists('shell_exec')) return false;
        if (self::isDisabled('shell_exec')) return false;

        try {
            $out = @shell_exec('echo partocms_test');
            return trim((string)$out) === 'partocms_test';
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * آیا exec کار می‌کنه؟
     */
    public static function hasExec(): bool {
        if (!function_exists('exec')) return false;
        if (self::isDisabled('exec')) return false;

        try {
            @exec('echo partocms_test', $out, $code);
            return $code === 0 && !empty($out) && trim(implode('', $out)) === 'partocms_test';
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * آیا proc_open کار می‌کنه؟
     */
    public static function hasProcOpen(): bool {
        return function_exists('proc_open') && !self::isDisabled('proc_open');
    }

    /**
     * آیا setsid نصبه؟ (برای اجرای مستقل)
     */
    public static function hasSetsid(): bool {
        if (!self::hasShellExec()) return false;
        try {
            $path = trim((string)@shell_exec('which setsid 2>/dev/null'));
            return !empty($path);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * آیا python3 نصبه؟
     */
    public static function hasPython(): bool {
        if (!self::hasShellExec()) return false;
        try {
            $out = @shell_exec('python3 --version 2>&1');
            return stripos((string)$out, 'python 3') !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * آیا argos-translate نصبه؟
     */
    public static function hasArgos(): bool {
        if (!self::hasShellExec()) return false;
        try {
            $out = @shell_exec('argos-translate --version 2>&1');
            return !empty($out) && stripos((string)$out, 'not found') === false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * آیا curl فعاله؟
     */
    public static function hasCurl(): bool {
        return function_exists('curl_init');
    }

    /**
     * تشخیص سیستم عامل
     */
    public static function getOS(): string {
        if (stripos(PHP_OS, 'linux') !== false) {
            if (self::hasShellExec() && strpos((string)@shell_exec('echo $PREFIX'), 'com.termux') !== false) {
                return 'termux';
            }
            return 'linux';
        }
        if (stripos(PHP_OS, 'win') !== false) return 'windows';
        if (stripos(PHP_OS, 'darwin') !== false) return 'macos';
        return 'unknown';
    }

    /**
     * بررسی کن که یه function غیرفعاله؟
     */
    private static function isDisabled(string $func): bool {
        $disabled = explode(',', (string)ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        return in_array(strtolower($func), array_map('strtolower', $disabled), true);
    }

    /**
     * بهترین روش اجرای پس‌زمینه رو برمی‌گردونه
     */
    public static function getBackgroundMethod(): string {
        if (self::hasSetsid()) return 'setsid';
        if (self::hasExec()) return 'exec';
        if (self::hasProcOpen()) return 'proc_open';
        return 'queue_only';  // فقط صف
    }

    /**
     * خلاصه وضعیت محیط
     */
    public static function getInfo(): array {
        return [
            'os' => self::getOS(),
            'php_version' => PHP_VERSION,
            'has_shell_exec' => self::hasShellExec(),
            'has_exec' => self::hasExec(),
            'has_proc_open' => self::hasProcOpen(),
            'has_setsid' => self::hasSetsid(),
            'has_python' => self::hasPython(),
            'has_argos' => self::hasArgos(),
            'has_curl' => self::hasCurl(),
            'background_method' => self::getBackgroundMethod(),
        ];
    }
}

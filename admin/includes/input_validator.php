<?php
/**
 * PartoCMS - لایه امنیتی سطح ۴: اعتبارسنجی ورودی و محدودیت نرخ
 */

class SecurityValidator {

    // ==================== پاک‌سازی ورودی ====================
    public static function sanitize($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $input);
        }

        switch ($type) {
            case 'int':
                return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'html':
                // برای محتوایی که باید HTML داشته باشد (مثل ادیتور)
                return self::purifyHtml($input);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // ==================== پاک‌سازی HTML (ضد XSS) ====================
    public static function purifyHtml($html) {
        // حذف تگ‌های خطرناک
        $dangerousTags = [
            'script', 'iframe', 'object', 'embed', 'applet',
            'form', 'input', 'button', 'textarea', 'select',
        ];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', '', $html);
            $html = preg_replace('/<' . $tag . '\b[^>]*\/?>/is', '', $html);
        }

        // حذف رویدادهای inline (onclick, onload, ...)
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // حذف javascript: URLs
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html);

        return $html;
    }

    // ==================== Rate Limiting (ضد Brute Force) ====================
    public static function checkRateLimit($identifier, $maxAttempts = 5, $windowSeconds = 900) {
        $key = 'rate_limit_' . md5($identifier);
        $file = sys_get_temp_dir() . '/' . $key . '.json';

        $data = ['count' => 0, 'start' => time()];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            // اگر پنجره زمانی تمام شده، ریست کن
            if (time() - $data['start'] > $windowSeconds) {
                $data = ['count' => 0, 'start' => time()];
            }
        }

        $data['count']++;

        if ($data['count'] > $maxAttempts) {
            $remaining = $windowSeconds - (time() - $data['start']);
            return [
                'allowed' => false,
                'remaining_seconds' => $remaining,
                'message' => 'تعداد تلاش‌های شما بیش از حد مجاز است. لطفاً ' . ceil($remaining / 60) . ' دقیقه دیگر تلاش کنید.',
            ];
        }

        file_put_contents($file, json_encode($data));
        return ['allowed' => true, 'attempts' => $data['count']];
    }

    // ==================== تولید و بررسی CSRF Token ====================
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // ==================== هش کردن رمز عبور (Argon2id) ====================
    public static function hashPassword($password) {
        // Argon2id قوی‌ترین الگوریتم هش در PHP 8+ است
        // مقاوم در برابر GPU-based cracking
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,
            'threads' => 2,
        ]);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>

<?php
/**
 * Security - توابع امنیتی
 */
class Security {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ==================== CSRF Protection ====================
    
    /**
     * تولید توکن CSRF
     */
    public function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * نمایش فیلد hidden CSRF
     */
    public function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . $this->csrfToken() . '">';
    }

    /**
     * بررسی توکن CSRF
     */
    public function verifyCsrf($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        // انقضا: ۲ ساعت
        if (isset($_SESSION['csrf_time']) && (time() - $_SESSION['csrf_time']) > 7200) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * اجبار CSRF
     */
    public function requireCsrf() {
        if (!$this->verifyCsrf()) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'توکن CSRF نامعتبر']);
            } else {
                die('❌ درخواست نامعتبر (CSRF)');
            }
            exit;
        }
    }

    // ==================== Rate Limiting ====================
    
    /**
     * بررسی محدودیت درخواست
     */
    public function checkRateLimit($action, $maxAttempts = 10, $timeWindow = 60) {
        $ip = $this->getClientIp();

        // پاک کردن رکوردهای قدیمی
        $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ")->execute([$timeWindow * 2]);

        $stmt = $this->pdo->prepare("
            SELECT id, count, first_attempt 
            FROM rate_limits 
            WHERE ip_address = ? AND action = ?
        ");
        $stmt->execute([$ip, $action]);
        $record = $stmt->fetch();

        if ($record) {
            $elapsed = time() - strtotime($record['first_attempt']);
            
            if ($elapsed < $timeWindow) {
                if ($record['count'] >= $maxAttempts) {
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'retry_after' => $timeWindow - $elapsed,
                    ];
                }
                
                $this->pdo->prepare("UPDATE rate_limits SET count = count + 1 WHERE id = ?")
                    ->execute([$record['id']]);
                
                return [
                    'allowed' => true,
                    'remaining' => $maxAttempts - $record['count'] - 1,
                ];
            } else {
                // ریست
                $this->pdo->prepare("UPDATE rate_limits SET count = 1, first_attempt = NOW() WHERE id = ?")
                    ->execute([$record['id']]);
                return ['allowed' => true, 'remaining' => $maxAttempts - 1];
            }
        }

        $this->pdo->prepare("INSERT INTO rate_limits (ip_address, action, count) VALUES (?, ?, 1)")
            ->execute([$ip, $action]);
        
        return ['allowed' => true, 'remaining' => $maxAttempts - 1];
    }

    /**
     * اجبار Rate Limit
     */
    public function requireRateLimit($action, $maxAttempts = 10, $timeWindow = 60) {
        $result = $this->checkRateLimit($action, $maxAttempts, $timeWindow);
        
        if (!$result['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $result['retry_after']);
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'تعداد درخواست‌ها زیاد است',
                    'retry_after' => $result['retry_after']
                ]);
            } else {
                die('⏱ لطفاً ' . $result['retry_after'] . ' ثانیه صبر کنید و دوباره تلاش کنید.');
            }
            exit;
        }

        return $result;
    }

    // ==================== IP و اطلاعات کاربر ====================
    
    public function getClientIp() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    // ==================== Sanitization ====================
    
    public function sanitize($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(fn($i) => $this->sanitize($i, $type), $input);
        }

        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return (int) $input;
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'html':
                return $this->purifyHtml($input);
            default:
                return trim(strip_tags($input));
        }
    }

    public function purifyHtml($html) {
        // حذف تگ‌های خطرناک
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $html);
        $html = preg_replace('#on\w+\s*=\s*["\'][^"\']*["\']#i', '', $html);
        $html = preg_replace('#on\w+\s*=\s*[^\s>]+#i', '', $html);
        $html = preg_replace('#javascript\s*:#i', '', $html);
        return $html;
    }

    // ==================== Security Headers ====================
    
    public function setSecurityHeaders() {
        if (headers_sent()) return;

        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Type Sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    // ==================== Activity Log ====================
    
    public function log($action, $entityType = null, $entityId = null, $description = null) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $this->getClientIp();

            $this->pdo->prepare("
                INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $action, $entityType, $entityId, $description, $ip]);
        } catch (Exception $e) {
            // silent
        }
    }
}

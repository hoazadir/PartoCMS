<?php
/**
 * PartoCMS - Rate Limiter (Anti Brute Force)
 */

class RateLimiter {
    private $pdo;
    private $maxAttempts;
    private $windowMinutes;
    private $lockoutMinutes;
    private $telegram;

    public function __construct($pdo, $maxAttempts = 5, $windowMinutes = 15, $telegram = null) {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->windowMinutes = $windowMinutes;
        $this->lockoutMinutes = [5, 15, 60, 1440];
        $this->telegram = $telegram;
    }

    public static function getClientIp() {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = $_SERVER[$k];
                if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public function check($ip = null) {
        if ($ip === null) $ip = self::getClientIp();
        try {
            $stmt = $this->pdo->prepare("SELECT attempts, blocked_until, block_level FROM login_blocks WHERE ip = :ip AND blocked_until > NOW() LIMIT 1");
            $stmt->execute([':ip' => $ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $until = strtotime($row['blocked_until']);
                $remaining = max(0, (int) ceil(($until - time()) / 60));
                return ['blocked' => true, 'until' => $row['blocked_until'], 'remaining_min' => $remaining, 'level' => (int) $row['block_level']];
            }
        } catch (Throwable $e) {}
        return ['blocked' => false, 'remaining_min' => 0, 'level' => 0];
    }

    public function record($username, $success = false, $ip = null) {
        if ($ip === null) $ip = self::getClientIp();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO login_attempts (ip, username, success, user_agent, attempted_at) VALUES (:ip, :user, :ok, :ua, NOW())");
            $stmt->execute([
                ':ip' => $ip,
                ':user' => mb_substr($username, 0, 100),
                ':ok' => $success ? 1 : 0,
                ':ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
            if ($success) { $this->clear($ip); return; }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL :mins MINUTE)");
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':mins', $this->windowMinutes, PDO::PARAM_INT);
            $stmt->execute();
            $failCount = (int) $stmt->fetchColumn();

            if ($failCount >= $this->maxAttempts) $this->block($ip, $failCount, $username);
        } catch (Throwable $e) {}
    }

    private function block($ip, $attempts, $username = '') {
        try {
            $stmt = $this->pdo->prepare("SELECT block_level FROM login_blocks WHERE ip = :ip");
            $stmt->execute([':ip' => $ip]);
            $existing = $stmt->fetchColumn();

            $level = $existing !== false ? min((int) $existing + 1, count($this->lockoutMinutes)) : 1;
            $minutes = $this->lockoutMinutes[$level - 1];

            $stmt = $this->pdo->prepare("INSERT INTO login_blocks (ip, attempts, blocked_until, block_level, last_attempt) VALUES (:ip, :att, DATE_ADD(NOW(), INTERVAL :mins MINUTE), :lvl, NOW()) ON DUPLICATE KEY UPDATE attempts = :att2, blocked_until = DATE_ADD(NOW(), INTERVAL :mins2 MINUTE), block_level = :lvl2, last_attempt = NOW()");
            $stmt->execute([':ip' => $ip, ':att' => $attempts, ':mins' => $minutes, ':lvl' => $level, ':att2' => $attempts, ':mins2' => $minutes, ':lvl2' => $level]);

            if ($this->telegram) $this->alert($ip, $username, $attempts, $minutes, $level);
        } catch (Throwable $e) {}
    }

    private function alert($ip, $username, $attempts, $minutes, $level) {
        try {
            $msg  = "\xF0\x9F\x9A\xA8 <b>Brute Force Attack Detected</b>\n";
            $msg .= "\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\xE2\x94\x81\n\n";
            $msg .= "\xF0\x9F\x8C\x90 <b>IP:</b> <code>" . htmlspecialchars($ip) . "</code>\n";
            $msg .= "\xF0\x9F\x91\xA4 <b>Username:</b> " . htmlspecialchars($username) . "\n";
            $msg .= "\xF0\x9F\x94\xA2 <b>Failed attempts:</b> " . $attempts . "\n";
            $msg .= "\xF0\x9F\x9A\xAB <b>Lockout level:</b> " . $level . "\n";
            $msg .= "\xE2\x8F\xB1 <b>Blocked for:</b> " . $minutes . " minutes\n";
            $msg .= "\xF0\x9F\x95\x90 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
            $msg .= "\xF0\x9F\x8C\x90 <b>Site:</b> " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n\n";
            $msg .= "\xF0\x9F\x94\x97 <a href=\"" . (defined('SITE_URL') ? SITE_URL : '') . "/admin/security_dashboard.php\">Dashboard</a>";
            $this->telegram->sendMessage($msg);
        } catch (Throwable $e) {}
    }

    public function clear($ip = null) {
        if ($ip === null) $ip = self::getClientIp();
        try {
            $this->pdo->prepare("DELETE FROM login_blocks WHERE ip = :ip")->execute([':ip' => $ip]);
            $this->pdo->prepare("DELETE FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)")->execute([':ip' => $ip]);
        } catch (Throwable $e) {}
    }

    public function cleanup() {
        try {
            $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $this->pdo->exec("DELETE FROM login_blocks WHERE blocked_until < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        } catch (Throwable $e) {}
    }

    public function getStats() {
        $stats = ['total_attempts' => 0, 'failed_attempts' => 0, 'active_blocks' => 0, 'unique_ips' => 0];
        try {
            $stats['total_attempts'] = (int) $this->pdo->query("SELECT COUNT(*) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
            $stats['failed_attempts'] = (int) $this->pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
            $stats['active_blocks'] = (int) $this->pdo->query("SELECT COUNT(*) FROM login_blocks WHERE blocked_until > NOW()")->fetchColumn();
            $stats['unique_ips'] = (int) $this->pdo->query("SELECT COUNT(DISTINCT ip) FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        } catch (Throwable $e) {}
        return $stats;
    }
}

<?php
/**
 * PartoCMS - Ads Module - AdTracker v2.0
 * ردیابی حرفه‌ای نمایش و کلیک تبلیغات
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 */

class AdTracker
{
    private PDO $pdo;

    /**
     * مدت جلوگیری از تکرار impression برای یک تبلیغ (ثانیه)
     */
    private const IMPRESSION_COOLDOWN = 30;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ═══════════════════════════════════════════════════════════
    // ثبت نمایش
    // ═══════════════════════════════════════════════════════════

    public function trackImpression(int $adId): bool
    {
        if ($adId < 1) return false;

        // بررسی وجود تبلیغ
        $stmt = $this->pdo->prepare("SELECT id FROM ads WHERE id = ? LIMIT 1");
        $stmt->execute([$adId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        // جلوگیری از تکرار در سشن (rate limiting)
        $sessionKey = 'ad_imp_' . $adId;
        $lastTime = $_SESSION[$sessionKey] ?? 0;
        if (time() - $lastTime < self::IMPRESSION_COOLDOWN) {
            return true; // قبلاً ثبت شده
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ad_impressions 
                (ad_id, user_id, ip_address, user_agent, referer, page_url, language, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $adId,
                $_SESSION['user_id'] ?? null,
                $this->getIp(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
                substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
                $_SESSION['language'] ?? null,
            ]);

            $_SESSION[$sessionKey] = time();
            return true;
        } catch (Throwable $e) {
            error_log('AdTracker impression error: ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ثبت کلیک
    // ═══════════════════════════════════════════════════════════

    public function trackClick(int $adId): bool
    {
        if ($adId < 1) return false;

        // بررسی وجود تبلیغ
        $stmt = $this->pdo->prepare("SELECT id FROM ads WHERE id = ? LIMIT 1");
        $stmt->execute([$adId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ad_clicks 
                (ad_id, user_id, ip_address, user_agent, referer, page_url, language, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $adId,
                $_SESSION['user_id'] ?? null,
                $this->getIp(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
                substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
                $_SESSION['language'] ?? null,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('AdTracker click error: ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // آمار
    // ═══════════════════════════════════════════════════════════

    /**
     * آمار یک تبلیغ
     */
    public function getAdStats(int $adId): array
    {
        $impressions = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_impressions WHERE ad_id = $adId
        ")->fetchColumn();

        $clicks = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_clicks WHERE ad_id = $adId
        ")->fetchColumn();

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

        $today = date('Y-m-d');
        $todayImpressions = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_impressions 
            WHERE ad_id = $adId AND DATE(created_at) = '$today'
        ")->fetchColumn();

        $todayClicks = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_clicks 
            WHERE ad_id = $adId AND DATE(created_at) = '$today'
        ")->fetchColumn();

        return [
            'impressions'       => $impressions,
            'clicks'            => $clicks,
            'ctr'               => $ctr,
            'today_impressions' => $todayImpressions,
            'today_clicks'      => $todayClicks,
        ];
    }

    /**
     * آمار روزانه یک تبلیغ
     */
    public function getDailyStats(int $adId, int $days = 30): array
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT
                d.day,
                COALESCE(i.cnt, 0) AS impressions,
                COALESCE(c.cnt, 0) AS clicks
            FROM (
                SELECT DATE(created_at) AS day FROM ad_impressions 
                WHERE ad_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                UNION
                SELECT DATE(created_at) AS day FROM ad_clicks 
                WHERE ad_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ) d
            LEFT JOIN (
                SELECT DATE(created_at) AS day, COUNT(*) AS cnt 
                FROM ad_impressions WHERE ad_id = ? GROUP BY DATE(created_at)
            ) i ON i.day = d.day
            LEFT JOIN (
                SELECT DATE(created_at) AS day, COUNT(*) AS cnt 
                FROM ad_clicks WHERE ad_id = ? GROUP BY DATE(created_at)
            ) c ON c.day = d.day
            ORDER BY d.day ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$adId, $days, $adId, $days, $adId, $adId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * آمار کلی همه تبلیغات
     */
    public function getOverallStats(int $days = 30): array
    {
        $days = max(1, min(365, $days));

        $impressions = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_impressions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        ")->fetchColumn();

        $clicks = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_clicks 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        ")->fetchColumn();

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'ctr'         => $ctr,
        ];
    }

    /**
     * TOP تبلیغات
     */
    public function getTopAds(int $limit = 10, int $days = 30): array
    {
        $limit = max(1, min(100, $limit));
        $days = max(1, min(365, $days));

        $sql = "
            SELECT
                a.id,
                a.title,
                a.status,
                (SELECT COUNT(*) FROM ad_impressions 
                 WHERE ad_id = a.id AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS impressions,
                (SELECT COUNT(*) FROM ad_clicks 
                 WHERE ad_id = a.id AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS clicks
            FROM ads a
            ORDER BY impressions DESC, clicks DESC
            LIMIT $limit
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days, $days]);
            $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($ads as &$ad) {
                $ad['ctr'] = $ad['impressions'] > 0
                    ? round(($ad['clicks'] / $ad['impressions']) * 100, 2)
                    : 0;
            }

            return $ads;
        } catch (Throwable $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════
    // پاکسازی
    // ═══════════════════════════════════════════════════════════

    /**
     * حذف آمار قدیمی (پیش‌فرض ۹۰ روز)
     */
    public function cleanOldStats(int $days = 90): int
    {
        $days = max(30, min(3650, $days));

        $count = (int) $this->pdo->query("
            SELECT COUNT(*) FROM ad_impressions 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)
        ")->fetchColumn();

        $this->pdo->exec("
            DELETE FROM ad_impressions 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)
        ");

        $this->pdo->exec("
            DELETE FROM ad_clicks 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)
        ");

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    // ابزار کمکی
    // ═══════════════════════════════════════════════════════════

    private function getIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}

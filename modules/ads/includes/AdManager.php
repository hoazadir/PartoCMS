<?php
/**
 * PartoCMS - Ads Module - AdManager v2.0
 * کلاس اصلی مدیریت تبلیغات — حرفه‌ای
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 */

class AdManager
{
    private PDO $pdo;

    /**
     * اندازه‌های استاندارد IAB
     */
    public const STANDARD_SIZES = [
        'leaderboard'    => ['w' => 728, 'h' => 90,  'label' => 'لیدربورد'],
        'billboard'      => ['w' => 970, 'h' => 250, 'label' => 'بیلبورد'],
        'medium_rect'    => ['w' => 300, 'h' => 250, 'label' => 'مستطیل متوسط'],
        'large_rect'     => ['w' => 336, 'h' => 280, 'label' => 'مستطیل بزرگ'],
        'half_page'      => ['w' => 300, 'h' => 600, 'label' => 'نیم‌صفحه'],
        'wide_skyscraper'=> ['w' => 160, 'h' => 600, 'label' => 'آسمان‌خراش'],
        'mobile_banner'  => ['w' => 320, 'h' => 50,  'label' => 'بنر موبایل'],
        'mobile_large'   => ['w' => 320, 'h' => 100, 'label' => 'بنر موبایل بزرگ'],
        'square'         => ['w' => 250, 'h' => 250, 'label' => 'مربع'],
        'button'         => ['w' => 125, 'h' => 125, 'label' => 'دکمه'],
        'slider'         => ['w' => 1200, 'h' => 500, 'label' => 'اسلایدر'],
        'popup'          => ['w' => 600, 'h' => 400, 'label' => 'پاپ‌آپ'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ═══════════════════════════════════════════════════════════
    // آمار کلی
    // ═══════════════════════════════════════════════════════════

    public function getStats(): array
    {
        $cacheKey = 'ads_stats';
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        $stats = [
            'total_ads'         => (int) $this->pdo->query("SELECT COUNT(*) FROM ads")->fetchColumn(),
            'active_ads'        => (int) $this->pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn(),
            'total_positions'   => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_positions")->fetchColumn(),
            'total_campaigns'   => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_campaigns")->fetchColumn(),
            'total_impressions' => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_impressions")->fetchColumn(),
            'total_clicks'      => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_clicks")->fetchColumn(),
            'today_impressions' => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_impressions WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'today_clicks'      => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_clicks WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'week_impressions'  => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_impressions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'week_clicks'       => (int) $this->pdo->query("SELECT COUNT(*) FROM ad_clicks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        ];

        $this->cacheSet($cacheKey, $stats, 60);
        return $stats;
    }

    public function getCtr(): float
    {
        $stats = $this->getStats();
        if ($stats['total_impressions'] === 0) return 0.0;
        return round(($stats['total_clicks'] / $stats['total_impressions']) * 100, 2);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD — تبلیغات
    // ═══════════════════════════════════════════════════════════

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $sql = "SELECT a.*, 
                       p.title AS position_title, 
                       p.slug AS position_slug,
                       c.name AS campaign_name
                FROM ads a
                LEFT JOIN ad_positions p ON p.id = a.position_id
                LEFT JOIN ad_campaigns c ON c.id = a.campaign_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND a.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['position_id'])) {
            $sql .= " AND a.position_id = ?";
            $params[] = (int) $filters['position_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (a.title LIKE ? OR a.description LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY a.priority DESC, a.id DESC";

        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT $perPage OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM ads WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['position_id'])) {
            $sql .= " AND position_id = ?";
            $params[] = (int) $filters['position_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE ? OR description LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, 
                   p.title AS position_title,
                   p.slug AS position_slug,
                   p.width AS position_width,
                   p.height AS position_height,
                   c.name AS campaign_name
            FROM ads a
            LEFT JOIN ad_positions p ON p.id = a.position_id
            LEFT JOIN ad_campaigns c ON c.id = a.campaign_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ads (
                title, description, type, position_id, campaign_id,
                image_url, target_url, html_content, adsense_code, alt_text,
                width, height, target_blank,
                start_date, end_date, priority, status, language,
                target_audience, max_impressions, max_clicks, created_by
            ) VALUES (
                :title, :description, :type, :position_id, :campaign_id,
                :image_url, :target_url, :html_content, :adsense_code, :alt_text,
                :width, :height, :target_blank,
                :start_date, :end_date, :priority, :status, :language,
                :target_audience, :max_impressions, :max_clicks, :created_by
            )
        ");
        $stmt->execute($this->prepareData($data, true));
        $this->cacheClear();
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ads SET
                title = :title,
                description = :description,
                type = :type,
                position_id = :position_id,
                campaign_id = :campaign_id,
                image_url = :image_url,
                target_url = :target_url,
                html_content = :html_content,
                adsense_code = :adsense_code,
                alt_text = :alt_text,
                width = :width,
                height = :height,
                target_blank = :target_blank,
                start_date = :start_date,
                end_date = :end_date,
                priority = :priority,
                status = :status,
                language = :language,
                target_audience = :target_audience,
                max_impressions = :max_impressions,
                max_clicks = :max_clicks
            WHERE id = :id
        ");
        $params = $this->prepareData($data, false);
        $params[':id'] = $id;
        $result = $stmt->execute($params);
        $this->cacheClear();
        return $result;
    }

    public function delete(int $id): bool
    {
        $this->pdo->prepare("DELETE FROM ad_impressions WHERE ad_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM ad_clicks WHERE ad_id = ?")->execute([$id]);
        $result = $this->pdo->prepare("DELETE FROM ads WHERE id = ?")->execute([$id]);
        $this->cacheClear();
        return $result;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['active', 'paused', 'expired', 'draft'];
        if (!in_array($status, $allowed, true)) return false;
        
        $result = $this->pdo->prepare("UPDATE ads SET status = ? WHERE id = ?")->execute([$status, $id]);
        $this->cacheClear();
        return $result;
    }

    public function duplicate(int $id): ?int
    {
        $ad = $this->getById($id);
        if (!$ad) return null;
        
        unset($ad['id']);
        unset($ad['created_at']);
        unset($ad['updated_at']);
        $ad['title'] = $ad['title'] . ' (کپی)';
        $ad['status'] = 'draft';
        
        return $this->create($ad);
    }

    // ═══════════════════════════════════════════════════════════
    // دریافت تبلیغات فعال برای نمایش
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت تبلیغات فعال یک موقعیت
     * 
     * @param string $positionSlug نام موقعیت
     * @param int $limit حداکثر تعداد (0 = بدون محدودیت)
     * @param bool $random ترتیب تصادفی (برای rotation)
     */
    public function getActiveAdsForPosition(string $positionSlug, int $limit = 0, bool $random = false): array
    {
        $cacheKey = 'ads_pos_' . $positionSlug . '_' . $limit . '_' . ($random ? 'r' : 's');
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) return $cached;

        $sql = "
            SELECT a.*
            FROM ads a
            INNER JOIN ad_positions p ON p.id = a.position_id
            WHERE p.slug = ?
              AND p.is_active = 1
              AND a.status = 'active'
              AND (a.start_date IS NULL OR a.start_date <= NOW())
              AND (a.end_date IS NULL OR a.end_date >= NOW())
        ";

        // بررسی حداکثر نمایش/کلیک
        $sql .= " AND (
            a.max_impressions IS NULL 
            OR a.max_impressions = 0
            OR (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = a.id) < a.max_impressions
        )";
        
        $sql .= " AND (
            a.max_clicks IS NULL 
            OR a.max_clicks = 0
            OR (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = a.id) < a.max_clicks
        )";

        if ($random) {
            $sql .= " ORDER BY RAND()";
        } else {
            $sql .= " ORDER BY a.priority DESC, a.id ASC";
        }

        if ($limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$positionSlug]);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // فیلتر تبلیغات خالی
        $ads = array_values(array_filter($ads, [$this, 'isValidAd']));

        $this->cacheSet($cacheKey, $ads, 300); // ۵ دقیقه
        return $ads;
    }

    /**
     * بررسی معتبر بودن تبلیغ
     */
    public function isValidAd(array $ad): bool
    {
        $type = $ad['type'] ?? 'image';
        switch ($type) {
            case 'image':
            case 'slider':
                return !empty($ad['image_url']);
            case 'html':
                return !empty(trim($ad['html_content'] ?? ''));
            case 'adsense':
                return !empty(trim($ad['adsense_code'] ?? ''));
            case 'text':
                return !empty($ad['title']) || !empty($ad['description']);
            default:
                return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // موقعیت‌ها
    // ═══════════════════════════════════════════════════════════

    public function getPositions(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM ad_positions";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPositionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ad_positions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPositionBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ad_positions WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createPosition(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_positions (slug, title, description, width, height, is_active, sort_order)
            VALUES (:slug, :title, :description, :width, :height, :is_active, :sort_order)
        ");
        $stmt->execute([
            ':slug'        => $data['slug'],
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':width'       => (int) ($data['width'] ?? 0),
            ':height'      => (int) ($data['height'] ?? 0),
            ':is_active'   => !empty($data['is_active']) ? 1 : 0,
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
        $this->cacheClear();
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePosition(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ad_positions SET
                slug = :slug, title = :title, description = :description,
                width = :width, height = :height,
                is_active = :is_active, sort_order = :sort_order
            WHERE id = :id
        ");
        $result = $stmt->execute([
            ':id'          => $id,
            ':slug'        => $data['slug'],
            ':title'       => $data['title'],
            ':description' => $data['description'] ?? null,
            ':width'       => (int) ($data['width'] ?? 0),
            ':height'      => (int) ($data['height'] ?? 0),
            ':is_active'   => !empty($data['is_active']) ? 1 : 0,
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
        $this->cacheClear();
        return $result;
    }

    public function deletePosition(int $id): bool
    {
        $result = $this->pdo->prepare("DELETE FROM ad_positions WHERE id = ?")->execute([$id]);
        $this->cacheClear();
        return $result;
    }

    // ═══════════════════════════════════════════════════════════
    // کمپین‌ها
    // ═══════════════════════════════════════════════════════════

    public function getCampaigns(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM ad_campaigns";
        if ($activeOnly) $sql .= " WHERE status = 'active'";
        $sql .= " ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCampaignById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ad_campaigns WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createCampaign(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_campaigns
            (name, description, advertiser_name, advertiser_email, advertiser_phone,
             budget, start_date, end_date, status, created_by)
            VALUES
            (:name, :description, :advertiser_name, :advertiser_email, :advertiser_phone,
             :budget, :start_date, :end_date, :status, :created_by)
        ");
        $stmt->execute([
            ':name'             => $data['name'],
            ':description'      => $data['description'] ?? null,
            ':advertiser_name'  => $data['advertiser_name'] ?? null,
            ':advertiser_email' => $data['advertiser_email'] ?? null,
            ':advertiser_phone' => $data['advertiser_phone'] ?? null,
            ':budget'           => (float) ($data['budget'] ?? 0),
            ':start_date'       => !empty($data['start_date']) ? $data['start_date'] : null,
            ':end_date'         => !empty($data['end_date']) ? $data['end_date'] : null,
            ':status'           => $data['status'] ?? 'draft',
            ':created_by'       => $_SESSION['user_id'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCampaign(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE ad_campaigns SET
                name = :name, description = :description,
                advertiser_name = :advertiser_name, advertiser_email = :advertiser_email,
                advertiser_phone = :advertiser_phone, budget = :budget,
                start_date = :start_date, end_date = :end_date, status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'               => $id,
            ':name'             => $data['name'],
            ':description'      => $data['description'] ?? null,
            ':advertiser_name'  => $data['advertiser_name'] ?? null,
            ':advertiser_email' => $data['advertiser_email'] ?? null,
            ':advertiser_phone' => $data['advertiser_phone'] ?? null,
            ':budget'           => (float) ($data['budget'] ?? 0),
            ':start_date'       => !empty($data['start_date']) ? $data['start_date'] : null,
            ':end_date'         => !empty($data['end_date']) ? $data['end_date'] : null,
            ':status'           => $data['status'] ?? 'draft',
        ]);
    }

    public function deleteCampaign(int $id): bool
    {
        $this->pdo->prepare("UPDATE ads SET campaign_id = NULL WHERE campaign_id = ?")->execute([$id]);
        return $this->pdo->prepare("DELETE FROM ad_campaigns WHERE id = ?")->execute([$id]);
    }

    // ═══════════════════════════════════════════════════════════
    // توابع کمکی
    // ═══════════════════════════════════════════════════════════

    private function prepareData(array $data, bool $includeCreatedBy = false): array
    {
        $params = [
            ':title'          => $data['title'] ?? '',
            ':description'    => $data['description'] ?? null,
            ':type'           => $data['type'] ?? 'image',
            ':position_id'    => !empty($data['position_id']) ? (int) $data['position_id'] : null,
            ':campaign_id'    => !empty($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            ':image_url'      => $data['image_url'] ?? null,
            ':target_url'     => $data['target_url'] ?? null,
            ':html_content'   => $data['html_content'] ?? null,
            ':adsense_code'   => $data['adsense_code'] ?? null,
            ':alt_text'       => $data['alt_text'] ?? null,
            ':width'          => !empty($data['width']) ? (int) $data['width'] : null,
            ':height'         => !empty($data['height']) ? (int) $data['height'] : null,
            ':target_blank'   => !empty($data['target_blank']) ? 1 : 0,
            ':start_date'     => !empty($data['start_date']) ? $data['start_date'] : null,
            ':end_date'       => !empty($data['end_date']) ? $data['end_date'] : null,
            ':priority'       => (int) ($data['priority'] ?? 0),
            ':status'         => $data['status'] ?? 'active',
            ':language'       => $data['language'] ?? null,
            ':target_audience'=> $data['target_audience'] ?? null,
            ':max_impressions'=> !empty($data['max_impressions']) ? (int) $data['max_impressions'] : null,
            ':max_clicks'     => !empty($data['max_clicks']) ? (int) $data['max_clicks'] : null,
        ];

        if ($includeCreatedBy) {
            $params[':created_by'] = $_SESSION['user_id'] ?? null;
        }

        return $params;
    }

    // ═══════════════════════════════════════════════════════════
    // کش فایل (سبک)
    // ═══════════════════════════════════════════════════════════

    private function getCacheDir(): string
    {
        $dir = __DIR__ . '/../../../logs/ads_cache/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function cacheGet(string $key): ?array
    {
        $file = $this->getCacheDir() . md5($key) . '.json';
        if (!file_exists($file)) return null;
        if (filemtime($file) < time() - 300) {
            @unlink($file);
            return null;
        }
        $data = @json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private function cacheSet(string $key, array $data, int $ttl = 300): void
    {
        $file = $this->getCacheDir() . md5($key) . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function cacheClear(): void
    {
        $dir = $this->getCacheDir();
        if (!is_dir($dir)) return;
        foreach (glob($dir . '*.json') as $file) {
            @unlink($file);
        }
    }
}

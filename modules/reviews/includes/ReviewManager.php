<?php
/**
 * 🆕 PartoCMS - Reviews Module - ReviewManager
 *
 * مدیریت نظرات و امتیازدهی برای هر نوع محتوا
 * پشتیبانی از: product, post, page, property, vehicle, ...
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

class ReviewManager
{
    private PDO $pdo;

    /** @var array کش برای آمار */
    private array $statsCache = [];

    /** @var array انواع محتوای مجاز */
    public const ALLOWED_ENTITIES = [
        'product', 'post', 'page', 'property',
        'vehicle', 'course', 'doctor', 'furniture',
        'restaurant', 'article', 'service'
    ];

    public const ALLOWED_STATUS = ['pending', 'approved', 'rejected', 'spam'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ═══════════════════════════════════════════════════════════
    // ۱. خواندن نظرات
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت نظرات یک entity
     */
    public function getByEntity(
        string $entityType,
        int $entityId,
        array $options = []
    ): array {
        $status = $options['status'] ?? 'approved';
        $limit = (int) ($options['limit'] ?? 20);
        $offset = (int) ($options['offset'] ?? 0);
        $sort = $options['sort'] ?? 'newest';

        $sql = "SELECT r.*,
                    u.username AS user_username,
                    u.email AS user_email
                FROM reviews r
                LEFT JOIN users u ON u.id = r.user_id
                WHERE r.entity_type = ?
                  AND r.entity_id = ?
                  AND r.parent_id IS NULL";

        $params = [$entityType, $entityId];

        if ($status !== 'all') {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }

        $orderBy = match ($sort) {
            'oldest'      => 'r.created_at ASC',
            'highest'     => 'r.rating DESC, r.created_at DESC',
            'lowest'      => 'r.rating ASC, r.created_at DESC',
            'helpful'     => 'r.helpful_count DESC, r.created_at DESC',
            default       => 'r.created_at DESC',
        };
        $sql .= " ORDER BY {$orderBy}";
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // بارگذاری پاسخ‌ها برای هر نظر
        foreach ($reviews as &$review) {
            $review['replies'] = $this->getReplies((int) $review['id']);
        }

        return $reviews;
    }

    /**
     * دریافت پاسخ‌های یک نظر
     */
    public function getReplies(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.username AS user_username
            FROM reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.parent_id = ?
              AND r.status = 'approved'
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * شمارش نظرات یک entity
     */
    public function countByEntity(
        string $entityType,
        int $entityId,
        string $status = 'approved'
    ): int {
        $sql = "SELECT COUNT(*) FROM reviews
                WHERE entity_type = ? AND entity_id = ? AND parent_id IS NULL";
        $params = [$entityType, $entityId];

        if ($status !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════
    // ۲. آمار و امتیاز
    // ═══════════════════════════════════════════════════════════

    /**
     * میانگین امتیاز
     */
    public function getAverageRating(string $entityType, int $entityId): float
    {
        $cacheKey = "{$entityType}:{$entityId}";
        if (isset($this->statsCache[$cacheKey])) {
            return $this->statsCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) FROM reviews
            WHERE entity_type = ?
              AND entity_id = ?
              AND status = 'approved'
              AND rating IS NOT NULL
              AND parent_id IS NULL
        ");
        $stmt->execute([$entityType, $entityId]);
        $avg = (float) $stmt->fetchColumn();

        return $this->statsCache[$cacheKey] = round($avg, 2);
    }

    /**
     * توزیع امتیازها (تعداد هر ستاره)
     */
    public function getRatingDistribution(
        string $entityType,
        int $entityId
    ): array {
        $stmt = $this->pdo->prepare("
            SELECT rating, COUNT(*) as count
            FROM reviews
            WHERE entity_type = ?
              AND entity_id = ?
              AND status = 'approved'
              AND rating IS NOT NULL
              AND parent_id IS NULL
            GROUP BY rating
            ORDER BY rating DESC
        ");
        $stmt->execute([$entityType, $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            $distribution[(int) $row['rating']] = (int) $row['count'];
        }
        return $distribution;
    }

    /**
     * خلاصه کامل آمار
     */
    public function getStats(string $entityType, int $entityId): array
    {
        $avg = $this->getAverageRating($entityType, $entityId);
        $count = $this->countByEntity($entityType, $entityId);
        $distribution = $this->getRatingDistribution($entityType, $entityId);

        return [
            'average'      => $avg,
            'count'        => $count,
            'distribution' => $distribution,
            'has_reviews'  => $count > 0,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // ۳. اعتبارسنجی
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی معتبر بودن entity
     */
    public function isValidEntity(string $entityType, int $entityId): bool
    {
        if (!in_array($entityType, self::ALLOWED_ENTITIES, true)) {
            return false;
        }
        if ($entityId < 1) {
            return false;
        }
        return true;
    }


    // ═══════════════════════════════════════════════════════════
    // ۴. ایجاد و ویرایش
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت نظر جدید
     */
    public function create(array $data): array
    {
        // ─── اعتبارسنجی ───
        $entityType = $data['entity_type'] ?? '';
        $entityId   = (int) ($data['entity_id'] ?? 0);

        if (!$this->isValidEntity($entityType, $entityId)) {
            return ['ok' => false, 'error' => 'entity نامعتبر است'];
        }

        $authorName = trim($data['author_name'] ?? '');
        $content    = trim($data['content'] ?? '');

        if ($authorName === '') {
            return ['ok' => false, 'error' => 'نام الزامی است'];
        }
        if (mb_strlen($authorName) > 100) {
            return ['ok' => false, 'error' => 'نام بیش از حد طولانی است'];
        }
        if ($content === '') {
            return ['ok' => false, 'error' => 'متن نظر الزامی است'];
        }
        if (mb_strlen($content) < 5) {
            return ['ok' => false, 'error' => 'متن نظر خیلی کوتاه است (حداقل ۵ کاراکتر)'];
        }
        if (mb_strlen($content) > 5000) {
            return ['ok' => false, 'error' => 'متن نظر خیلی طولانی است (حداکثر ۵۰۰۰ کاراکتر)'];
        }

        $rating = isset($data['rating']) ? (int) $data['rating'] : null;
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            return ['ok' => false, 'error' => 'امتیاز باید بین ۱ تا ۵ باشد'];
        }

        $email = trim($data['author_email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'ایمیل نامعتبر است'];
        }

        // ─── تنظیمات ───
        $settings = $this->getSettings($entityType);
        $status = !empty($settings['auto_approve']) ? 'approved' : 'pending';

        // ─── درج ───
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reviews
                    (entity_type, entity_id, user_id, author_name, author_email,
                     author_ip, rating, title, content, status, parent_id, language)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $entityType,
                $entityId,
                $data['user_id'] ?? null,
                $authorName,
                $email ?: null,
                $data['author_ip'] ?? null,
                $rating,
                trim($data['title'] ?? '') ?: null,
                $content,
                $status,
                isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                $data['language'] ?? 'fa-IR',
            ]);

            $id = (int) $this->pdo->lastInsertId();

            return [
                'ok'        => true,
                'id'        => $id,
                'status'    => $status,
                'message'   => $status === 'approved'
                    ? 'نظر شما ثبت و تأیید شد'
                    : 'نظر شما ثبت شد و در انتظار تأیید است',
            ];

        } catch (Throwable $e) {
            error_log('ReviewManager::create error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'خطا در ثبت نظر'];
        }
    }

    /**
     * تأیید یا رد یک نظر
     */
    public function updateStatus(int $id, string $status, ?int $adminId = null): bool
    {
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            return false;
        }

        $approvedAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare("
            UPDATE reviews
            SET status = ?, approved_at = ?, approved_by = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $approvedAt, $adminId, $id]);
    }

    /**
     * حذف نظر (با پاسخ‌ها)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reviews WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * دریافت یک نظر
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.username AS user_username
            FROM reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ═══════════════════════════════════════════════════════════
    // ۵. رأی مفید/غیرمفید
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت رأی
     */
    public function vote(int $reviewId, string $type, ?int $userId = null, ?string $ip = null): array
    {
        if (!in_array($type, ['helpful', 'not_helpful'], true)) {
            return ['ok' => false, 'error' => 'نوع رأی نامعتبر'];
        }

        $review = $this->getById($reviewId);
        if (!$review) {
            return ['ok' => false, 'error' => 'نظر پیدا نشد'];
        }

        // چک رأی تکراری
        $stmt = $this->pdo->prepare("
            SELECT id FROM review_votes
            WHERE review_id = ?
              AND ((user_id IS NOT NULL AND user_id = ?) OR (ip_address = ?))
        ");
        $stmt->execute([$reviewId, $userId, $ip]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'قبلاً رأی داده‌اید'];
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO review_votes (review_id, user_id, ip_address, vote_type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$reviewId, $userId, $ip, $type]);

            // بروزرسانی شمارنده
            $column = $type === 'helpful' ? 'helpful_count' : 'not_helpful_count';
            $this->pdo->prepare("UPDATE reviews SET {$column} = {$column} + 1 WHERE id = ?")
                ->execute([$reviewId]);

            return ['ok' => true, 'message' => 'رأی شما ثبت شد'];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'خطا در ثبت رأی'];
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ۶. تنظیمات
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت تنظیمات
     */
    public function getSettings(string $entityType = 'global'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT setting_key, setting_value
            FROM review_settings
            WHERE entity_type IN (?, 'global')
        ");
        $stmt->execute([$entityType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [
            'enabled'              => true,
            'auto_approve'         => false,
            'allow_guest'          => true,
            'require_email'        => true,
            'max_rating'           => 5,
            'allow_helpful_votes'  => true,
            'items_per_page'       => 10,
            // 🆕 Display settings
            'enable_on_post'       => false,
            'enable_on_product'    => false,
            'enable_on_page'       => false,
            'display_position'     => 'after',
            'show_summary'         => true,
            'show_form'            => true,
            'show_list'            => true,
            // 🆕 Comments compatibility
            'enable_comments'      => true,
            'comments_auto_approve'=> false,
        ];

        foreach ($rows as $row) {
            $val = $row['setting_value'];
            if (in_array($val, ['0', '1'], true)) {
                $val = (bool) $val;
            } elseif (is_numeric($val)) {
                $val = (int) $val;
            }
            $settings[$row['setting_key']] = $val;
        }

        return $settings;
    }

    /**
     * بروزرسانی تنظیمات
     */
    public function updateSettings(string $entityType, array $settings): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO review_settings (entity_type, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");

        $ok = true;
        foreach ($settings as $key => $value) {
            if (is_bool($value)) $value = $value ? '1' : '0';
            try {
                $stmt->execute([$entityType, $key, (string) $value]);
            } catch (Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }
}

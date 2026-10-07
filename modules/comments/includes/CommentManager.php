<?php
/**
 * PartoCMS - Comments Module - CommentManager
 * منطق اصلی دیدگاه‌ها
 */

class CommentManager
{
    private PDO $pdo;

    public const ALLOWED_STATUS = ['pending', 'approved', 'spam', 'rejected'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت دیدگاه‌های یک محتوا (فقط تأیید‌شده)
     */
    public function getByContent(int $contentId, string $status = 'approved', int $limit = 50, int $offset = 0): array
    {
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            $status = 'approved';
        }
        $sql = "SELECT c.*,
                       u.username AS user_name,
                       u.avatar AS user_avatar
                FROM comments c
                LEFT JOIN users u ON u.id = c.user_id
                WHERE c.content_id = ?
                  AND c.parent_id IS NULL
                  AND c.status = ?
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $contentId, PDO::PARAM_INT);
        $stmt->bindValue(2, $status, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * دریافت پاسخ‌های یک دیدگاه
     */
    public function getReplies(int $parentId, string $status = 'approved'): array
    {
        $sql = "SELECT c.*, u.username AS user_name, u.avatar AS user_avatar
                FROM comments c
                LEFT JOIN users u ON u.id = c.user_id
                WHERE c.parent_id = ?
                  AND c.status = ?
                ORDER BY c.created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$parentId, $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * شمارش دیدگاه‌های یک محتوا
     */
    public function countByContent(int $contentId, string $status = 'approved'): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM comments
            WHERE content_id = ? AND status = ?
        ");
        $stmt->execute([$contentId, $status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * دریافت دیدگاه با id
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ایجاد دیدگاه جدید
     */
    public function create(array $data): array
    {
        $contentId = (int) ($data['content_id'] ?? 0);
        $authorName = trim($data['author_name'] ?? '');
        $authorEmail = trim($data['author_email'] ?? '');
        $comment = trim($data['comment'] ?? '');

        if ($contentId < 1) return ['ok' => false, 'error' => 'شناسه محتوا معتبر نیست'];
        if ($authorName === '') return ['ok' => false, 'error' => 'نام الزامی است'];
        if ($authorEmail === '' || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'ایمیل معتبر نیست'];
        }
        if (mb_strlen($comment) < 3) return ['ok' => false, 'error' => 'متن دیدگاه خیلی کوتاه است'];
        if (mb_strlen($comment) > 5000) return ['ok' => false, 'error' => 'متن دیدگاه خیلی بلند است'];

        // بررسی محتوا موجود است
        $chk = $this->pdo->prepare("SELECT id FROM content_items WHERE id = ? LIMIT 1");
        $chk->execute([$contentId]);
        if (!$chk->fetchColumn()) return ['ok' => false, 'error' => 'محتوا یافت نشد'];

        // 🆕 چک تنظیمات تأیید خودکار
        $status = 'pending';
        try {
            $__rvPath = __DIR__ . '/../../reviews/includes/ReviewManager.php';
            if (file_exists($__rvPath)) {
                require_once $__rvPath;
                $__settings = (new ReviewManager($this->pdo))->getSettings('global');
                if (!empty($__settings['comments_auto_approve'])) {
                    $status = 'approved';
                }
            }
        } catch (Throwable $e) {
            // silent — fallback به pending
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO comments
                (content_id, parent_id, author_name, author_email, author_website,
                 author_ip, comment, status, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $contentId,
                !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
                $authorName,
                $authorEmail,
                !empty($data['author_website']) ? trim($data['author_website']) : null,
                $data['author_ip'] ?? null,
                $comment,
                $status,
                !empty($data['user_id']) ? (int) $data['user_id'] : null,
            ]);
            return ['ok' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log('CommentManager::create: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'خطا در ثبت دیدگاه'];
        }
    }

    /**
     * تغییر وضعیت
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::ALLOWED_STATUS, true)) return false;
        $stmt = $this->pdo->prepare("UPDATE comments SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * حذف
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM comments WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * پاسخ ادمین به دیدگاه
     */
    public function adminReply(int $parentId, string $text, int $adminUserId): array
    {
        $parent = $this->getById($parentId);
        if (!$parent) return ['ok' => false, 'error' => 'دیدگاه یافت نشد'];

        $text = trim($text);
        if (mb_strlen($text) < 2) return ['ok' => false, 'error' => 'پاسخ خیلی کوتاه است'];

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO comments
                (content_id, parent_id, author_name, author_email,
                 comment, status, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $parent['content_id'],
                $parentId,
                'مدیر',
                null,
                $text,
                'approved',
                $adminUserId,
            ]);
            return ['ok' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log('CommentManager::adminReply: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'خطا در ثبت پاسخ'];
        }
    }

    /**
     * آمار کلی
     */
    public function getStats(): array
    {
        $stats = [
            'total'    => 0,
            'pending'  => 0,
            'approved' => 0,
            'spam'     => 0,
            'rejected' => 0,
        ];
        try {
            $stats['total'] = (int) $this->pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM comments WHERE status = ?");
            foreach (['pending','approved','spam','rejected'] as $s) {
                $stmt->execute([$s]);
                $stats[$s] = (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            error_log('CommentManager::getStats: ' . $e->getMessage());
        }
        return $stats;
    }
}

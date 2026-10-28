<?php
declare(strict_types=1);

namespace PartoCMS\Comments\Widget;

class CommentWidget
{
    public static function render(array $options = []): string
    {
        $pdo = getDB();
        $limit = $options['limit'] ?? 5;

        try {
            // واکشی آخرین دیدگاه‌های تایید شده
            $stmt = $pdo->prepare("SELECT c.*, u.username FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.status = 'approved' ORDER BY c.created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">خطا در بارگذاری دیدگاه‌ها.</div>';
        }

        if (empty($comments)) {
            return '<div class="alert alert-info">هیچ دیدگاهی برای نمایش وجود ندارد.</div>';
        }

        ob_start();
        ?>
        <div class="container my-4">
            <div class="row g-3">
                <?php foreach ($comments as $c): ?>
                    <div class="col-md-12">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-body d-flex gap-3">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-weight: bold; flex-shrink:0;">
                                    <?= mb_substr($c['username'] ?? 'کاربر', 0, 1) ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($c['username'] ?? 'کاربر ناشناس') ?></h6>
                                    <p class="text-muted mb-2" style="font-size: 13px;"><?= htmlspecialchars($c['content'] ?? '') ?></p>
                                    <small class="text-muted"><i class="bi bi-clock"></i> <?= date('Y/m/d', strtotime($c['created_at'])) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

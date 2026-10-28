<?php
declare(strict_types=1);

namespace PartoCMS\Content\Widget;

class ContentWidget
{
    public static function render(array $options = []): string
    {
        $pdo = getDB();
        $limit = $options['limit'] ?? 3;
        
        try {
            // واکشی ۳ مقاله یا خبر آخر
            $stmt = $pdo->prepare("SELECT ci.*, c.name as cat_name FROM content_items ci LEFT JOIN categories c ON c.id = ci.category_id WHERE ci.status = 'published' ORDER BY ci.created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return '<div class="alert alert-warning">خطا در بارگذاری مقالات.</div>';
        }

        if (empty($posts)) {
            return '<div class="alert alert-info">هیچ مقاله‌ای برای نمایش وجود ندارد.</div>';
        }

        ob_start();
        ?>
        <div class="container my-5">
            <div class="row g-4">
                <?php foreach ($posts as $post): ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border-0 rounded-4">
                            <?php if (!empty($post['featured_image'])): ?>
                                <img src="<?= htmlspecialchars($post['featured_image']) ?>" class="card-img-top rounded-top-4" style="height:200px; object-fit:cover;" alt="<?= htmlspecialchars($post['title']) ?>">
                            <?php else: ?>
                                <div class="card-img-top rounded-top-4 d-flex align-items-center justify-content-center bg-light" style="height:200px; color:#94a3b8;">
                                    <i class="bi bi-file-text fs-1"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <?php if ($post['cat_name']): ?>
                                    <span class="badge bg-primary mb-2"><?= htmlspecialchars($post['cat_name']) ?></span>
                                <?php endif; ?>
                                <h5 class="card-title fw-bold"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text text-muted" style="font-size:13px; line-height:1.8;">
                                    <?= htmlspecialchars(mb_substr(strip_tags($post['excerpt'] ?? $post['content']), 0, 100)) ?>...
                                </p>
                            </div>
                            <div class="card-footer bg-white border-0 pb-3">
                                <a href="/post.php?id=<?= $post['id'] ?>" class="btn btn-outline-primary w-100 rounded-pill">مشاهده مقاله</a>
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

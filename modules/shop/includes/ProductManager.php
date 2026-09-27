<?php
/**
 * PartoCMS - Product Manager
 * مدیریت محصولات فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class ProductManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت تمام محصولات با فیلتر
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                FROM shop_products p
                LEFT JOIN shop_categories c ON c.id = p.category_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['featured'])) {
            $sql .= " AND p.featured = 1";
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY p.sort_order ASC, p.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * شمارش کل محصولات
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM shop_products WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND category_id = ?";
            $params[] = $filters['category_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * دریافت یک محصول
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM shop_products p
            LEFT JOIN shop_categories c ON c.id = p.category_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * دریافت محصول با slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM shop_products p
            LEFT JOIN shop_categories c ON c.id = p.category_id
            WHERE p.slug = ?
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * ساخت محصول جدید
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shop_products
            (name, slug, description, short_description, sku, price, compare_price,
             cost_price, quantity, category_id, image, gallery, attributes, tags,
             weight, dimensions, status, featured, downloadable, virtual,
             meta_title, meta_description, meta_keywords, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['short_description'] ?? null,
            $data['sku'] ?? null,
            $data['price'] ?? 0,
            $data['compare_price'] ?? null,
            $data['cost_price'] ?? null,
            $data['quantity'] ?? 0,
            $data['category_id'] ?? null,
            $data['image'] ?? null,
            $data['gallery'] ?? null,
            $data['attributes'] ?? null,
            $data['tags'] ?? null,
            $data['weight'] ?? null,
            $data['dimensions'] ?? null,
            $data['status'] ?? 'draft',
            $data['featured'] ?? 0,
            $data['downloadable'] ?? 0,
            $data['virtual'] ?? 0,
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['meta_keywords'] ?? null,
            $data['sort_order'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * به‌روزرسانی محصول
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE shop_products SET
                name = ?, slug = ?, description = ?, short_description = ?,
                sku = ?, price = ?, compare_price = ?, cost_price = ?,
                quantity = ?, category_id = ?, image = ?, gallery = ?,
                attributes = ?, tags = ?, weight = ?, dimensions = ?,
                status = ?, featured = ?, downloadable = ?, virtual = ?,
                meta_title = ?, meta_description = ?, meta_keywords = ?, sort_order = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['short_description'] ?? null,
            $data['sku'] ?? null,
            $data['price'] ?? 0,
            $data['compare_price'] ?? null,
            $data['cost_price'] ?? null,
            $data['quantity'] ?? 0,
            $data['category_id'] ?? null,
            $data['image'] ?? null,
            $data['gallery'] ?? null,
            $data['attributes'] ?? null,
            $data['tags'] ?? null,
            $data['weight'] ?? null,
            $data['dimensions'] ?? null,
            $data['status'] ?? 'draft',
            $data['featured'] ?? 0,
            $data['downloadable'] ?? 0,
            $data['virtual'] ?? 0,
            $data['meta_title'] ?? null,
            $data['meta_description'] ?? null,
            $data['meta_keywords'] ?? null,
            $data['sort_order'] ?? 0,
            $id,
        ]);
    }

    /**
     * حذف محصول
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * محصولات ویژه
     */
    public function getFeatured(int $limit = 6): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM shop_products p
            LEFT JOIN shop_categories c ON c.id = p.category_id
            WHERE p.featured = 1 AND p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * محصولات مرتبط
     */
    public function getRelated(int $productId, int $categoryId, int $limit = 4): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM shop_products p
            LEFT JOIN shop_categories c ON c.id = p.category_id
            WHERE p.category_id = ? AND p.id != ? AND p.status = 'published'
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $productId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * افزایش بازدید
     */
    public function incrementViews(int $id): void
    {
        $this->pdo->prepare("UPDATE shop_products SET views = views + 1 WHERE id = ?")
                  ->execute([$id]);
    }

    /**
     * کاهش موجودی
     */
    public function decreaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE shop_products SET quantity = quantity - ?
            WHERE id = ? AND quantity >= ?
        ");
        return $stmt->execute([$quantity, $id, $quantity]);
    }
}

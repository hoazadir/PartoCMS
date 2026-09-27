<?php
/**
 * PartoCMS - Category Manager
 * مدیریت دسته‌بندی محصولات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class CategoryManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت تمام دسته‌ها (سلسله‌مراتبی)
     */
    public function getAll(bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM shop_categories";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت دسته‌ها به صورت درختی
     */
    public function getTree(?int $parentId = null): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM shop_categories
            WHERE parent_id " . ($parentId === null ? "IS NULL" : "= ?") . "
            ORDER BY sort_order ASC, name ASC
        ");

        if ($parentId === null) {
            $stmt->execute();
        } else {
            $stmt->execute([$parentId]);
        }

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as &$cat) {
            $cat['children'] = $this->getTree((int) $cat['id']);
        }

        return $categories;
    }

    /**
     * دریافت یک دسته
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * دریافت دسته با slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_categories WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * ساخت دسته جدید
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shop_categories
            (name, slug, description, parent_id, image, icon, color, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['image'] ?? null,
            $data['icon'] ?? null,
            $data['color'] ?? '#3498db',
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * به‌روزرسانی دسته
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE shop_categories SET
                name = ?,
                slug = ?,
                description = ?,
                parent_id = ?,
                image = ?,
                icon = ?,
                color = ?,
                sort_order = ?,
                is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['image'] ?? null,
            $data['icon'] ?? null,
            $data['color'] ?? '#3498db',
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    /**
     * حذف دسته
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * شمارش محصولات هر دسته
     */
    public function getProductCount(int $categoryId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM shop_products
            WHERE category_id = ? AND status = 'published'
        ");
        $stmt->execute([$categoryId]);
        return (int) $stmt->fetchColumn();
    }
}

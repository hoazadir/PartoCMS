<?php
/**
 * PartoCMS - Shipping Manager
 * مدیریت روش‌های ارسال
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class ShippingManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت تمام روش‌های ارسال
     */
    public function getAll(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM shop_shipping_methods";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, cost ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت یک روش ارسال
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_shipping_methods WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * محاسبه هزینه ارسال
     */
    public function calculateCost(?int $methodId, float $subtotal): float
    {
        // اگر روش انتخاب نشده، ارزان‌ترین روش را انتخاب کن
        if (!$methodId) {
            $methods = $this->getAll();
            if (empty($methods)) return 0;
            $methodId = (int) $methods[0]['id'];
        }

        $method = $this->getById($methodId);
        if (!$method) return 0;

        // اگر ارسال رایگان برای این مبلغ فعال است
        if ($method['free_over'] && $subtotal >= $method['free_over']) {
            return 0;
        }

        return (float) $method['cost'];
    }

    /**
     * ایجاد روش ارسال
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shop_shipping_methods
            (name, description, cost, free_over, min_days, max_days, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['cost'] ?? 0,
            !empty($data['free_over']) ? $data['free_over'] : null,
            (int) ($data['min_days'] ?? 1),
            (int) ($data['max_days'] ?? 7),
            isset($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * به‌روزرسانی روش ارسال
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE shop_shipping_methods SET
                name = ?, description = ?, cost = ?, free_over = ?,
                min_days = ?, max_days = ?, is_active = ?, sort_order = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['cost'] ?? 0,
            !empty($data['free_over']) ? $data['free_over'] : null,
            (int) ($data['min_days'] ?? 1),
            (int) ($data['max_days'] ?? 7),
            isset($data['is_active']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    /**
     * حذف روش ارسال
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_shipping_methods WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

<?php
/**
 * PartoCMS - Coupon Manager
 * مدیریت کوپن‌های تخفیف
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class CouponManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت تمام کوپن‌ها
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM shop_coupons ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت یک کوپن
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_coupons WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * دریافت کوپن با کد
     */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_coupons WHERE code = ? LIMIT 1");
        $stmt->execute([strtoupper(trim($code))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * اعتبارسنجی کوپن
     */
    public function validate(string $code, float $subtotal): array
    {
        $coupon = $this->getByCode($code);
        if (!$coupon) {
            return ['ok' => false, 'error' => 'کد تخفیف یافت نشد'];
        }

        if (!$coupon['is_active']) {
            return ['ok' => false, 'error' => 'این کد تخفیف غیرفعال است'];
        }

        // بررسی تاریخ
        $now = date('Y-m-d H:i:s');
        if ($coupon['starts_at'] && $coupon['starts_at'] > $now) {
            return ['ok' => false, 'error' => 'این کد تخفیف هنوز فعال نشده است'];
        }
        if ($coupon['expires_at'] && $coupon['expires_at'] < $now) {
            return ['ok' => false, 'error' => 'این کد تخفیف منقضی شده است'];
        }

        // بررسی حداقل مبلغ
        if ($coupon['min_order_amount'] && $subtotal < $coupon['min_order_amount']) {
            return [
                'ok' => false,
                'error' => 'حداقل مبلغ سفارش برای استفاده از این کد ' . number_format($coupon['min_order_amount']) . ' تومان است'
            ];
        }

        // بررسی محدودیت استفاده
        if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
            return ['ok' => false, 'error' => 'ظرفیت استفاده از این کد تخفیف پر شده است'];
        }

        // محاسبه تخفیف
        $discount = 0;
        if ($coupon['type'] === 'percentage') {
            $discount = ($subtotal * $coupon['value']) / 100;
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = (float) $coupon['max_discount'];
            }
        } else {
            $discount = (float) $coupon['value'];
        }

        if ($discount > $subtotal) {
            $discount = $subtotal;
        }

        return [
            'ok' => true,
            'coupon' => $coupon,
            'discount' => round($discount, 2),
            'code' => $coupon['code'],
        ];
    }

    /**
     * ایجاد کوپن جدید
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO shop_coupons
            (code, description, type, value, min_order_amount, max_discount,
             usage_limit, usage_limit_per_user, starts_at, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['description'] ?? null,
            $data['type'] ?? 'percentage',
            $data['value'] ?? 0,
            !empty($data['min_order_amount']) ? $data['min_order_amount'] : null,
            !empty($data['max_discount']) ? $data['max_discount'] : null,
            !empty($data['usage_limit']) ? $data['usage_limit'] : null,
            (int) ($data['usage_limit_per_user'] ?? 1),
            !empty($data['starts_at']) ? $data['starts_at'] : null,
            !empty($data['expires_at']) ? $data['expires_at'] : null,
            isset($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * به‌روزرسانی کوپن
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE shop_coupons SET
                code = ?, description = ?, type = ?, value = ?,
                min_order_amount = ?, max_discount = ?, usage_limit = ?,
                usage_limit_per_user = ?, starts_at = ?, expires_at = ?, is_active = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            strtoupper(trim($data['code'])),
            $data['description'] ?? null,
            $data['type'] ?? 'percentage',
            $data['value'] ?? 0,
            !empty($data['min_order_amount']) ? $data['min_order_amount'] : null,
            !empty($data['max_discount']) ? $data['max_discount'] : null,
            !empty($data['usage_limit']) ? $data['usage_limit'] : null,
            (int) ($data['usage_limit_per_user'] ?? 1),
            !empty($data['starts_at']) ? $data['starts_at'] : null,
            !empty($data['expires_at']) ? $data['expires_at'] : null,
            isset($data['is_active']) ? 1 : 0,
            $id,
        ]);
    }

    /**
     * حذف کوپن
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_coupons WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * ثبت استفاده از کوپن
     */
    public function recordUsage(int $couponId, ?int $userId, int $orderId, float $discountAmount): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO shop_coupon_usage (coupon_id, user_id, order_id, discount_amount)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$couponId, $userId, $orderId, $discountAmount]);

            $stmt = $this->pdo->prepare("UPDATE shop_coupons SET used_count = used_count + 1 WHERE id = ?");
            $stmt->execute([$couponId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}

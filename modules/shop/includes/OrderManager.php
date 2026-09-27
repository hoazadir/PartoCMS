<?php
/**
 * PartoCMS - Order Manager
 * مدیریت سفارشات فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class OrderManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * ساخت شماره سفارش یکتا
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD-';
        try {
            $stmt = $this->pdo->query("SELECT setting_value FROM shop_settings WHERE setting_key = 'order_prefix'");
            $prefix = $stmt->fetchColumn() ?: 'ORD-';
        } catch (Exception $e) {}

        return $prefix . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * ساخت سفارش جدید
     */
    public function create(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $orderNumber = $this->generateOrderNumber();

            $stmt = $this->pdo->prepare("
                INSERT INTO shop_orders
                (order_number, user_id, customer_id, status, payment_status,
                 payment_method, subtotal, discount, tax, shipping, total,
                 currency, coupon_code, shipping_method, shipping_address,
                 billing_address, customer_notes)
                VALUES (?, ?, ?, 'pending', 'unpaid', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $orderNumber,
                $data['user_id'] ?? null,
                $data['customer_id'] ?? null,
                $data['payment_method'] ?? null,
                $data['subtotal'] ?? 0,
                $data['discount'] ?? 0,
                $data['tax'] ?? 0,
                $data['shipping'] ?? 0,
                $data['total'] ?? 0,
                $data['currency'] ?? 'IRR',
                $data['coupon_code'] ?? null,
                $data['shipping_method'] ?? null,
                $data['shipping_address'] ?? null,
                $data['billing_address'] ?? null,
                $data['customer_notes'] ?? null,
            ]);

            $orderId = (int) $this->pdo->lastInsertId();

            // افزودن آیتم‌ها
            if (!empty($data['items'])) {
                $stmtItem = $this->pdo->prepare("
                    INSERT INTO shop_order_items
                    (order_id, product_id, variant_id, product_name, product_price, quantity, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['items'] as $item) {
                    $stmtItem->execute([
                        $orderId,
                        $item['product_id'],
                        $item['variant_id'] ?? null,
                        $item['product_name'],
                        $item['product_price'],
                        $item['quantity'],
                        $item['product_price'] * $item['quantity'],
                    ]);
                }
            }

            // ثبت تاریخچه
            $stmt = $this->pdo->prepare("INSERT INTO shop_order_status_history (order_id, status, notes) VALUES (?, 'pending', ?)");
            $stmt->execute([$orderId, 'سفارش ایجاد شد']);

            $this->pdo->commit();

            return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * دریافت سفارش
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_orders WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * دریافت آیتم‌های سفارش
     */
    public function getItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت سفارشات یک کاربر
     */
    public function getUserOrders(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت تمام سفارشات
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM shop_orders WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $sql .= " AND payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (order_number LIKE ? OR shipping_address LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * شمارش سفارشات
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM shop_orders WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['payment_status'])) {
            $sql .= " AND payment_status = ?";
            $params[] = $filters['payment_status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * به‌روزرسانی وضعیت
     */
    public function updateStatus(int $orderId, string $status, string $notes = ''): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE shop_orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $orderId]);

            $stmt = $this->pdo->prepare("INSERT INTO shop_order_status_history (order_id, status, notes) VALUES (?, ?, ?)");
            $stmt->execute([$orderId, $status, $notes]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * علامت‌گذاری به عنوان پرداخت‌شده
     */
    public function markAsPaid(int $orderId, string $gatewaySlug, string $transactionId = ''): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE shop_orders SET payment_status = 'paid', paid_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);

            $stmt = $this->pdo->prepare("
                INSERT INTO shop_transactions (order_id, gateway_slug, transaction_id, amount, status, paid_at)
                SELECT id, ?, ?, total, 'success', NOW() FROM shop_orders WHERE id = ?
            ");
            $stmt->execute([$gatewaySlug, $transactionId, $orderId]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * آمار سفارشات
     */
    public function getStats(): array
    {
        try {
            return [
                'total' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders")->fetchColumn(),
                'pending' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'pending'")->fetchColumn(),
                'processing' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'processing'")->fetchColumn(),
                'completed' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'completed'")->fetchColumn(),
                'revenue' => (float) $this->pdo->query("SELECT COALESCE(SUM(total), 0) FROM shop_orders WHERE payment_status = 'paid'")->fetchColumn(),
            ];
        } catch (Exception $e) {
            return ['total' => 0, 'pending' => 0, 'processing' => 0, 'completed' => 0, 'revenue' => 0];
        }
    }
}

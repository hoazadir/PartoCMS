<?php
/**
 * PartoCMS - Report Manager
 * مدیریت گزارش‌های فروش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class ReportManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * آمار کلی فروش
     */
    public function getOverview(?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?: date('Y-m-d', strtotime('-30 days'));
        $to = $toDate ?: date('Y-m-d');

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(total), 0) AS total_revenue,
                    COALESCE(AVG(total), 0) AS avg_order_value,
                    COUNT(DISTINCT user_id) AS unique_customers
                FROM shop_orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND payment_status = 'paid'
            ");
            $stmt->execute([$from, $to]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_orders' => (int) $stats['total_orders'],
                'total_revenue' => (float) $stats['total_revenue'],
                'avg_order_value' => (float) $stats['avg_order_value'],
                'unique_customers' => (int) $stats['unique_customers'],
                'from' => $from,
                'to' => $to,
            ];
        } catch (Exception $e) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'unique_customers' => 0,
                'from' => $from,
                'to' => $to,
            ];
        }
    }

    /**
     * فروش روزانه (برای نمودار)
     */
    public function getDailySales(int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(created_at) AS date,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total), 0) AS revenue
                FROM shop_orders
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND payment_status = 'paid'
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * پرفروش‌ترین محصولات
     */
    public function getTopProducts(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    oi.product_id,
                    oi.product_name,
                    SUM(oi.quantity) AS total_sold,
                    SUM(oi.subtotal) AS total_revenue
                FROM shop_order_items oi
                INNER JOIN shop_orders o ON o.id = oi.order_id
                WHERE o.payment_status = 'paid'
                GROUP BY oi.product_id, oi.product_name
                ORDER BY total_sold DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * آمار بر اساس وضعیت سفارش
     */
    public function getOrdersByStatus(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT status, COUNT(*) AS count, SUM(total) AS revenue
                FROM shop_orders
                GROUP BY status
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * آمار بر اساس روش پرداخت
     */
    public function getPaymentStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    payment_method,
                    COUNT(*) AS count,
                    SUM(total) AS revenue
                FROM shop_orders
                WHERE payment_status = 'paid' AND payment_method IS NOT NULL
                GROUP BY payment_method
                ORDER BY revenue DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * آمار مشتریان برتر
     */
    public function getTopCustomers(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    o.user_id,
                    u.username,
                    u.email,
                    COUNT(*) AS total_orders,
                    SUM(o.total) AS total_spent
                FROM shop_orders o
                LEFT JOIN users u ON u.id = o.user_id
                WHERE o.payment_status = 'paid' AND o.user_id IS NOT NULL
                GROUP BY o.user_id, u.username, u.email
                ORDER BY total_spent DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * فروش بر اساس دسته‌بندی
     */
    public function getSalesByCategory(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    c.name AS category_name,
                    COUNT(DISTINCT o.id) AS orders,
                    SUM(oi.subtotal) AS revenue
                FROM shop_order_items oi
                INNER JOIN shop_orders o ON o.id = oi.order_id
                LEFT JOIN shop_products p ON p.id = oi.product_id
                LEFT JOIN shop_categories c ON c.id = p.category_id
                WHERE o.payment_status = 'paid'
                GROUP BY c.id, c.name
                ORDER BY revenue DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

<?php
/**
 * PartoCMS - Payment Manager
 * مدیریت درگاه‌های پرداخت
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class PaymentManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * دریافت درگاه‌های فعال
     */
    public function getActiveGateways(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM shop_payment_gateways
            WHERE is_enabled = 1
            ORDER BY sort_order ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * دریافت درگاه با slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_payment_gateways WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * بررسی فعال بودن درگاه
     */
    public function isEnabled(string $slug): bool
    {
        $gateway = $this->getBySlug($slug);
        return $gateway && (int) $gateway['is_enabled'] === 1;
    }

    /**
     * بارگذاری درایور درگاه
     */
    public function loadGateway(string $slug): ?object
    {
        $gateway = $this->getBySlug($slug);
        if (!$gateway) return null;

        $config = !empty($gateway['config']) ? json_decode($gateway['config'], true) : [];
        $file = __DIR__ . '/../payment/gateways/' . $slug . '.php';

        if (!file_exists($file)) return null;

        require_once $file;

        $className = 'Gateway_' . ucfirst($slug);
        if (!class_exists($className)) return null;

        return new $className($config, (int) $gateway['is_test_mode'] === 1);
    }

    /**
     * شروع پرداخت
     */
    public function startPayment(int $orderId, string $gatewaySlug): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['ok' => false, 'error' => 'سفارش یافت نشد'];
        }

        $gateway = $this->loadGateway($gatewaySlug);
        if (!$gateway) {
            return ['ok' => false, 'error' => 'درگاه پرداخت یافت نشد'];
        }

        try {
            $result = $gateway->request($order);
            return $result;
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تایید پرداخت
     */
    public function verifyPayment(int $orderId, string $gatewaySlug, array $params = []): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['ok' => false, 'error' => 'سفارش یافت نشد'];
        }

        $gateway = $this->loadGateway($gatewaySlug);
        if (!$gateway) {
            return ['ok' => false, 'error' => 'درگاه پرداخت یافت نشد'];
        }

        try {
            $result = $gateway->verify($order, $params);

            if ($result['ok']) {
                // به‌روزرسانی وضعیت پرداخت
                $this->pdo->prepare("
                    UPDATE shop_orders SET payment_status = 'paid', paid_at = NOW()
                    WHERE id = ?
                ")->execute([$orderId]);

                // ثبت تراکنش
                $this->pdo->prepare("
                    INSERT INTO shop_transactions
                    (order_id, gateway_slug, transaction_id, reference_id, amount, status, response, paid_at)
                    VALUES (?, ?, ?, ?, ?, 'success', ?, NOW())
                ")->execute([
                    $orderId,
                    $gatewaySlug,
                    $result['transaction_id'] ?? '',
                    $result['reference_id'] ?? '',
                    $order['total'],
                    json_encode($result, JSON_UNESCAPED_UNICODE),
                ]);
            }

            return $result;
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * دریافت سفارش
     */
    private function getOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shop_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * ذخیره تنظیمات درگاه
     */
    public function saveGatewayConfig(string $slug, array $config, bool $enabled = false, bool $testMode = true): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE shop_payment_gateways
                SET config = ?, is_enabled = ?, is_test_mode = ?
                WHERE slug = ?
            ");
            return $stmt->execute([
                json_encode($config, JSON_UNESCAPED_UNICODE),
                $enabled ? 1 : 0,
                $testMode ? 1 : 0,
                $slug,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}

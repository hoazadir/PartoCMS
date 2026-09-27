<?php
/**
 * PartoCMS - Shop Manager
 * کلاس اصلی مدیریت فروشگاه
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class ShopManager
{
    private PDO $pdo;
    private array $settings;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * بارگذاری تنظیمات فروشگاه
     */
    private function loadSettings(): void
    {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM shop_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $this->settings = [];
        }
    }

    /**
     * دریافت یک تنظیم
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * ذخیره تنظیم
     */
    public function setSetting(string $key, $value): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO shop_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
            $this->settings[$key] = $value;
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * آمار کلی فروشگاه
     */
    public function getStats(): array
    {
        try {
            return [
                'products' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_products WHERE status = 'published'")->fetchColumn(),
                'categories' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_categories")->fetchColumn(),
                'orders' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders")->fetchColumn(),
                'customers' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_customers")->fetchColumn(),
                'revenue' => (float) $this->pdo->query("SELECT COALESCE(SUM(total), 0) FROM shop_orders WHERE status = 'completed'")->fetchColumn(),
                'pending_orders' => (int) $this->pdo->query("SELECT COUNT(*) FROM shop_orders WHERE status = 'pending'")->fetchColumn(),
            ];
        } catch (Exception $e) {
            return [
                'products' => 0,
                'categories' => 0,
                'orders' => 0,
                'customers' => 0,
                'revenue' => 0,
                'pending_orders' => 0,
            ];
        }
    }

    /**
     * تولید slug یکتا
     */
    public function generateSlug(string $text, string $table = 'shop_products'): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        $original = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $table)) {
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * بررسی وجود slug
     */
    private function slugExists(string $slug, string $table): bool
    {
        $allowedTables = ['shop_products', 'shop_categories'];
        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
            $stmt->execute([$slug]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * فرمت قیمت با واحد پول
     */
    public function formatPrice(float $price): string
    {
        $symbol = $this->getSetting('currency_symbol', 'تومان');
        return number_format($price, 0, '.', ',') . ' ' . $symbol;
    }

    /**
     * محاسبه قیمت نهایی با تخفیف و مالیات
     */
    public function calculateTotal(float $subtotal, float $discount = 0, float $tax = 0, float $shipping = 0): float
    {
        return max(0, $subtotal - $discount + $tax + $shipping);
    }

    /**
     * بررسی دسترسی ادمین
     */
    public function isAdmin(): bool
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * دریافت URL ماژول
     */
    public function getUrl(string $path = ''): string
    {
        $base = SITE_URL . '/modules/shop';
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }

    /**
     * دریافت URL ادمین
     */
    public function getAdminUrl(string $path = ''): string
    {
        $base = SITE_URL . '/modules/shop/admin';
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }

    /**
     * بررسی فعال بودن درگاه پرداخت
     */
    public function isGatewayEnabled(string $gateway): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT is_enabled FROM shop_payment_gateways
                WHERE slug = ? LIMIT 1
            ");
            $stmt->execute([$gateway]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * دریافت لیست درگاه‌های فعال
     */
    public function getActiveGateways(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM shop_payment_gateways
                WHERE is_enabled = 1
                ORDER BY sort_order ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

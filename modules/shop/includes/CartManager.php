<?php
/**
 * PartoCMS - Cart Manager
 * مدیریت سبد خرید
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class CartManager
{
    private PDO $pdo;
    private int $cartId = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeCart();
    }

    /**
     * راه‌اندازی یا دریافت سبد فعلی
     */
    private function initializeCart(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = session_id();

        if ($userId) {
            // کاربر لاگین: جستجو بر اساس user_id
            $stmt = $this->pdo->prepare("SELECT id FROM shop_carts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $this->cartId = (int) $existing;
                return;
            }

            // ایجاد سبد جدید
            $stmt = $this->pdo->prepare("INSERT INTO shop_carts (user_id, session_id, currency) VALUES (?, ?, 'IRR')");
            $stmt->execute([$userId, $sessionId]);
            $this->cartId = (int) $this->pdo->lastInsertId();
        } else {
            // کاربر مهمان: جستجو بر اساس session_id
            $stmt = $this->pdo->prepare("SELECT id FROM shop_carts WHERE session_id = ? AND user_id IS NULL ORDER BY id DESC LIMIT 1");
            $stmt->execute([$sessionId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $this->cartId = (int) $existing;
                return;
            }

            // ایجاد سبد جدید
            $stmt = $this->pdo->prepare("INSERT INTO shop_carts (session_id, currency) VALUES (?, 'IRR')");
            $stmt->execute([$sessionId]);
            $this->cartId = (int) $this->pdo->lastInsertId();
        }
    }

    /**
     * دریافت شناسه سبد
     */
    public function getCartId(): int
    {
        return $this->cartId;
    }

    /**
     * دریافت آیتم‌های سبد
     */
    public function getItems(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, p.name, p.slug, p.image, p.quantity AS stock, p.price AS current_price
            FROM shop_cart_items ci
            LEFT JOIN shop_products p ON p.id = ci.product_id
            WHERE ci.cart_id = ?
            ORDER BY ci.id ASC
        ");
        $stmt->execute([$this->cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * افزودن محصول به سبد
     */
    public function add(int $productId, int $quantity = 1): array
    {
        // بررسی محصول
        $stmt = $this->pdo->prepare("SELECT id, name, price, quantity FROM shop_products WHERE id = ? AND status = 'published'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['ok' => false, 'error' => 'محصول یافت نشد'];
        }

        if ($product['quantity'] < $quantity) {
            return ['ok' => false, 'error' => 'موجودی کافی نیست'];
        }

        // بررسی وجود در سبد
        $stmt = $this->pdo->prepare("SELECT id, quantity FROM shop_cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$this->cartId, $productId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = (int) $existing['quantity'] + $quantity;
            if ($newQty > $product['quantity']) {
                return ['ok' => false, 'error' => 'موجودی کافی نیست'];
            }
            $stmt = $this->pdo->prepare("UPDATE shop_cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQty, $existing['id']]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO shop_cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$this->cartId, $productId, $quantity, $product['price']]);
        }

        return ['ok' => true, 'message' => 'محصول به سبد اضافه شد', 'count' => $this->count()];
    }

    /**
     * به‌روزرسانی تعداد
     */
    public function updateQuantity(int $itemId, int $quantity): array
    {
        if ($quantity < 1) {
            return $this->remove($itemId);
        }

        $stmt = $this->pdo->prepare("
            SELECT ci.id, p.quantity AS stock
            FROM shop_cart_items ci
            LEFT JOIN shop_products p ON p.id = ci.product_id
            WHERE ci.id = ? AND ci.cart_id = ?
        ");
        $stmt->execute([$itemId, $this->cartId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return ['ok' => false, 'error' => 'آیتم یافت نشد'];
        }

        if ($quantity > $item['stock']) {
            return ['ok' => false, 'error' => 'موجودی کافی نیست'];
        }

        $stmt = $this->pdo->prepare("UPDATE shop_cart_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$quantity, $itemId]);

        return ['ok' => true, 'message' => 'تعداد به‌روزرسانی شد', 'count' => $this->count()];
    }

    /**
     * حذف از سبد
     */
    public function remove(int $itemId): array
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$itemId, $this->cartId]);
        return ['ok' => true, 'message' => 'محصول از سبد حذف شد', 'count' => $this->count()];
    }

    /**
     * خالی کردن سبد
     */
    public function clear(): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM shop_cart_items WHERE cart_id = ?");
        return $stmt->execute([$this->cartId]);
    }

    /**
     * تعداد آیتم‌ها
     */
    public function count(): int
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM shop_cart_items WHERE cart_id = ?");
        $stmt->execute([$this->cartId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * جمع کل
     */
    public function subtotal(): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(quantity * price), 0) FROM shop_cart_items WHERE cart_id = ?");
        $stmt->execute([$this->cartId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * خالی بودن سبد
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }
}

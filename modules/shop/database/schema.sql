-- ==================== PartoCMS Shop Module Database ====================
-- جداول ماژول فروشگاه
-- Author: Hooman Oliaei
-- Version: 1.0.0
-- Date: 2026-09-27

-- ==================== ۱. دسته‌بندی محصولات ====================
CREATE TABLE IF NOT EXISTS `shop_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `icon` VARCHAR(100) DEFAULT NULL,
    `color` VARCHAR(20) DEFAULT '#3498db',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۲. محصولات ====================
CREATE TABLE IF NOT EXISTS `shop_products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `short_description` TEXT,
    `sku` VARCHAR(100) DEFAULT NULL,
    `price` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `compare_price` DECIMAL(15,2) DEFAULT NULL,
    `cost_price` DECIMAL(15,2) DEFAULT NULL,
    `quantity` INT DEFAULT 0,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `gallery` JSON DEFAULT NULL,
    `attributes` JSON DEFAULT NULL,
    `tags` JSON DEFAULT NULL,
    `weight` DECIMAL(10,2) DEFAULT NULL,
    `dimensions` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `featured` TINYINT(1) DEFAULT 0,
    `downloadable` TINYINT(1) DEFAULT 0,
    `virtual` TINYINT(1) DEFAULT 0,
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT,
    `meta_keywords` VARCHAR(500) DEFAULT NULL,
    `views` INT DEFAULT 0,
    `sales_count` INT DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_featured` (`featured`),
    FULLTEXT `idx_search` (`name`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۳. تصاویر محصولات ====================
CREATE TABLE IF NOT EXISTS `shop_product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image` VARCHAR(500) NOT NULL,
    `alt` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`product_id`) REFERENCES `shop_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۴. تنوع محصولات ====================
CREATE TABLE IF NOT EXISTS `shop_product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) DEFAULT NULL,
    `price` DECIMAL(15,2) DEFAULT NULL,
    `quantity` INT DEFAULT 0,
    `attributes` JSON DEFAULT NULL,
    `image` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`product_id`) REFERENCES `shop_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۵. ویژگی‌های محصولات ====================
CREATE TABLE IF NOT EXISTS `shop_product_attributes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `attribute_name` VARCHAR(100) NOT NULL,
    `attribute_value` TEXT,
    `sort_order` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`product_id`) REFERENCES `shop_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۶. سبد خرید ====================
CREATE TABLE IF NOT EXISTS `shop_carts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `currency` VARCHAR(10) DEFAULT 'IRR',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۷. آیتم‌های سبد خرید ====================
CREATE TABLE IF NOT EXISTS `shop_cart_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price` DECIMAL(15,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cart` (`cart_id`),
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`cart_id`) REFERENCES `shop_carts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۸. مشتریان ====================
CREATE TABLE IF NOT EXISTS `shop_customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `mobile` VARCHAR(20) DEFAULT NULL,
    `address` TEXT,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'Iran',
    `total_orders` INT DEFAULT 0,
    `total_spent` DECIMAL(15,2) DEFAULT 0,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۹. سفارشات ====================
CREATE TABLE IF NOT EXISTS `shop_orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    `payment_status` ENUM('unpaid', 'paid', 'failed', 'refunded') DEFAULT 'unpaid',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `discount` DECIMAL(15,2) DEFAULT 0,
    `tax` DECIMAL(15,2) DEFAULT 0,
    `shipping` DECIMAL(15,2) DEFAULT 0,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(10) DEFAULT 'IRR',
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `shipping_method` VARCHAR(100) DEFAULT NULL,
    `shipping_address` TEXT,
    `billing_address` TEXT,
    `customer_notes` TEXT,
    `admin_notes` TEXT,
    `tracking_code` VARCHAR(100) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `shipped_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۰. آیتم‌های سفارش ====================
CREATE TABLE IF NOT EXISTS `shop_order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_price` DECIMAL(15,2) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order` (`order_id`),
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۱. تاریخچه وضعیت سفارش ====================
CREATE TABLE IF NOT EXISTS `shop_order_status_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order` (`order_id`),
    FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۲. کوپن‌های تخفیف ====================
CREATE TABLE IF NOT EXISTS `shop_coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `value` DECIMAL(15,2) NOT NULL,
    `min_order_amount` DECIMAL(15,2) DEFAULT NULL,
    `max_discount` DECIMAL(15,2) DEFAULT NULL,
    `usage_limit` INT DEFAULT NULL,
    `usage_limit_per_user` INT DEFAULT 1,
    `used_count` INT DEFAULT 0,
    `starts_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۳. استفاده از کوپن ====================
CREATE TABLE IF NOT EXISTS `shop_coupon_usage` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `order_id` INT UNSIGNED DEFAULT NULL,
    `discount_amount` DECIMAL(15,2) NOT NULL,
    `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_coupon` (`coupon_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۴. درگاه‌های پرداخت ====================
CREATE TABLE IF NOT EXISTS `shop_payment_gateways` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `type` ENUM('iranian', 'international', 'crypto') NOT NULL DEFAULT 'iranian',
    `logo` VARCHAR(500) DEFAULT NULL,
    `description` TEXT,
    `config` JSON DEFAULT NULL,
    `is_enabled` TINYINT(1) DEFAULT 0,
    `is_test_mode` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_slug` (`slug`),
    INDEX `idx_type` (`type`),
    INDEX `idx_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۵. تراکنش‌ها ====================
CREATE TABLE IF NOT EXISTS `shop_transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `gateway_slug` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `reference_id` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'IRR',
    `status` ENUM('pending', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    `response` TEXT,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order` (`order_id`),
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۶. روش‌های ارسال ====================
CREATE TABLE IF NOT EXISTS `shop_shipping_methods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `cost` DECIMAL(15,2) DEFAULT 0,
    `free_over` DECIMAL(15,2) DEFAULT NULL,
    `min_days` INT DEFAULT 1,
    `max_days` INT DEFAULT 7,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۷. نرخ مالیات ====================
CREATE TABLE IF NOT EXISTS `shop_tax_rates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `country` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ۱۸. تنظیمات فروشگاه ====================
CREATE TABLE IF NOT EXISTS `shop_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== داده‌های اولیه ====================
INSERT IGNORE INTO `shop_settings` (`setting_key`, `setting_value`) VALUES
('enabled', '1'),
('currency', 'IRR'),
('currency_symbol', 'تومان'),
('tax_rate', '9'),
('shipping_cost', '50000'),
('free_shipping_over', '1000000'),
('products_per_page', '12'),
('order_prefix', 'ORD-'),
('low_stock_threshold', '5'),
('guest_checkout', '1');

INSERT IGNORE INTO `shop_payment_gateways` (`name`, `slug`, `type`, `is_enabled`, `is_test_mode`, `sort_order`) VALUES
('زرین‌پال', 'zarinpal', 'iranian', 0, 1, 10),
('آیدی‌پی', 'idpay', 'iranian', 0, 1, 20),
('پی‌پینگ', 'payping', 'iranian', 0, 1, 30),
('نکست‌پی', 'nextpay', 'iranian', 0, 1, 40),
('بانک ملت', 'mellat', 'iranian', 0, 1, 50),
('بانک سامان', 'saman', 'iranian', 0, 1, 60),
('PayPal', 'paypal', 'international', 0, 1, 100),
('Stripe', 'stripe', 'international', 0, 1, 110),
('NowPayments', 'nowpayments', 'crypto', 0, 1, 200),
('CoinGate', 'coingate', 'crypto', 0, 1, 210);

-- ==================== پایان ====================

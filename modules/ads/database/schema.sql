-- ═══════════════════════════════════════════════════════════
-- PartoCMS - Ads Module Database Schema
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `ad_positions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(50) NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `advertiser_name` VARCHAR(200) NULL,
    `advertiser_email` VARCHAR(255) NULL,
    `advertiser_phone` VARCHAR(50) NULL,
    `budget` DECIMAL(15,2) NULL,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `status` ENUM('draft','active','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `type` ENUM('image','html','adsense','text','slider') NOT NULL DEFAULT 'image',
    `position_id` INT UNSIGNED NULL,
    `campaign_id` INT UNSIGNED NULL,
    `image_url` VARCHAR(500) NULL,
    `target_url` VARCHAR(500) NULL,
    `html_content` TEXT NULL,
    `adsense_code` TEXT NULL,
    `alt_text` VARCHAR(200) NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `target_blank` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `status` ENUM('active','paused','expired','draft') NOT NULL DEFAULT 'active',
    `language` VARCHAR(10) NULL,
    `target_audience` VARCHAR(50) NULL,
    `max_impressions` INT UNSIGNED NULL,
    `max_clicks` INT UNSIGNED NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `type` (`type`),
    KEY `position_id` (`position_id`),
    KEY `campaign_id` (`campaign_id`),
    KEY `status` (`status`),
    KEY `priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_impressions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `referer` VARCHAR(500) NULL,
    `page_url` VARCHAR(500) NULL,
    `language` VARCHAR(10) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ad_id` (`ad_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_clicks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `referer` VARCHAR(500) NULL,
    `page_url` VARCHAR(500) NULL,
    `language` VARCHAR(10) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ad_id` (`ad_id`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ad_positions` (`slug`, `title`, `description`, `width`, `height`, `is_active`, `sort_order`) VALUES
('header',        'هدر (بالای صفحه)',   'بنر بالای صفحه اصلی',  728, 90, 1, 1),
('sidebar',       'سایدبار',             'بنر ستون کنار',         300, 250, 1, 2),
('footer',        'فوتر (پایین صفحه)',  'بنر پایین صفحه',        728, 90, 1, 3),
('in-content',    'داخل محتوا',          'بنر داخل متن مقالات',   468, 60, 1, 4),
('popup',         'پاپ‌آپ',               'پنجره پاپ‌آپ',           600, 400, 1, 5),
('sticky-footer', 'فوتر چسبان',          'نوار چسبان پایین',      970, 90, 1, 6),
('slider-home',   'اسلایدر صفحه اصلی',  'اسلایدشو در صفحه اصلی', 1200, 500, 1, 7),
('home-top',      'بالای صفحه اصلی',    'بنر بالای محتوای اصلی', 970, 250, 1, 8);

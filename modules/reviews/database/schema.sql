-- ═══════════════════════════════════════════════════════════
-- PartoCMS - Reviews Module Database Schema
-- سیستم نظرات و امتیازدهی
-- ═══════════════════════════════════════════════════════════

-- ─── ۱. جدول نظرات ───
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'product, post, page, property, vehicle, ...',
    `entity_id` INT NOT NULL COMMENT 'ID آیتم مورد نظر',
    `user_id` INT NULL COMMENT 'اگر کاربر لاگین است',
    `author_name` VARCHAR(100) NOT NULL,
    `author_email` VARCHAR(255) NULL,
    `author_ip` VARCHAR(45) NULL,
    `rating` TINYINT NULL COMMENT '۱ تا ۵',
    `title` VARCHAR(200) NULL,
    `content` TEXT NOT NULL,
    `status` ENUM('pending','approved','rejected','spam') NOT NULL DEFAULT 'pending',
    `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0,
    `helpful_count` INT NOT NULL DEFAULT 0,
    `not_helpful_count` INT NOT NULL DEFAULT 0,
    `parent_id` INT NULL COMMENT 'برای پاسخ‌ها',
    `language` VARCHAR(10) NULL DEFAULT 'fa-IR',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL,
    `approved_by` INT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_status` (`status`),
    KEY `idx_user` (`user_id`),
    KEY `idx_rating` (`rating`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reviews_parent` FOREIGN KEY (`parent_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ۲. جدول رأی‌های مفید ───
CREATE TABLE IF NOT EXISTS `review_votes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `review_id` INT NOT NULL,
    `user_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `vote_type` ENUM('helpful','not_helpful') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_vote` (`review_id`, `user_id`, `ip_address`),
    KEY `idx_review` (`review_id`),
    KEY `idx_user` (`user_id`),
    CONSTRAINT `fk_votes_review` FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ۳. جدول گزارش تخلف ───
CREATE TABLE IF NOT EXISTS `review_reports` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `review_id` INT NOT NULL,
    `reporter_id` INT NULL,
    `reporter_ip` VARCHAR(45) NULL,
    `reason` ENUM('spam','offensive','irrelevant','fake','other') NOT NULL,
    `details` TEXT NULL,
    `status` ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_review` (`review_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_reports_review` FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ۴. جدول تنظیمات (per entity) ───
CREATE TABLE IF NOT EXISTS `review_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(50) NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_entity_setting` (`entity_type`, `setting_key`),
    KEY `idx_entity` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── درج تنظیمات پیش‌فرض ───
INSERT INTO `review_settings` (`entity_type`, `setting_key`, `setting_value`) VALUES
('global', 'enabled', '1'),
('global', 'auto_approve', '0'),
('global', 'allow_guest', '1'),
('global', 'require_email', '1'),
('global', 'max_rating', '5'),
('global', 'allow_helpful_votes', '1'),
('global', 'items_per_page', '10')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ═══════════════════════════════════════════════════════════
-- پایان schema
-- ═══════════════════════════════════════════════════════════

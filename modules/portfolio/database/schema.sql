-- ============================================================
-- PartoCMS — Portfolio Module
-- Schema: portfolio_items
-- ============================================================
-- Author: Hooman Oliaei
-- License: Proprietary
-- Version: 1.0.0
-- Date: 2026-10-19
-- ============================================================

CREATE TABLE IF NOT EXISTS `portfolio_items` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(200) NOT NULL,
    `slug`          VARCHAR(200) DEFAULT NULL,
    `client_name`   VARCHAR(150) DEFAULT NULL,
    `description`   TEXT         DEFAULT NULL COMMENT 'توضیح کوتاه (TinyMCE)',
    `content`       LONGTEXT     DEFAULT NULL COMMENT 'محتوای کامل (TinyMCE)',
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `gallery`       TEXT         DEFAULT NULL COMMENT 'JSON array از URLها',
    `category`      VARCHAR(100) DEFAULT NULL,
    `tags`          TEXT         DEFAULT NULL COMMENT 'JSON array از برچسب‌ها',
    `project_date`  DATE         DEFAULT NULL,
    `project_url`   VARCHAR(255) DEFAULT NULL,
    `status`        ENUM('published','hidden') NOT NULL DEFAULT 'published',
    `is_featured`   TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`    INT(11)      NOT NULL DEFAULT 0,
    `language`      VARCHAR(10)  NOT NULL DEFAULT 'fa-IR',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_status`   (`status`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_sort`     (`sort_order`),
    KEY `idx_language` (`language`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='نمونه‌کارها (Portfolio)';

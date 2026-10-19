-- ============================================================
-- PartoCMS — Services Module
-- Schema: services
-- ============================================================

CREATE TABLE IF NOT EXISTS `services` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(150) NOT NULL,
    `slug`        VARCHAR(150) DEFAULT NULL,
    `description` TEXT         DEFAULT NULL,
    `content`     TEXT         DEFAULT NULL,
    `icon`        VARCHAR(100) DEFAULT NULL COMMENT 'emoji یا CSS class',
    `image_url`   VARCHAR(255) DEFAULT NULL,
    `price`       DECIMAL(10,2) DEFAULT NULL,
    `price_unit`  VARCHAR(50)  DEFAULT NULL COMMENT 'تومان، ریال، /ساعت',
    `features`    TEXT         DEFAULT NULL COMMENT 'JSON array',
    `category`    VARCHAR(100) DEFAULT NULL,
    `status`      ENUM('published','hidden') NOT NULL DEFAULT 'published',
    `is_featured` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`  INT(11)      NOT NULL DEFAULT 0,
    `language`    VARCHAR(10)  NOT NULL DEFAULT 'fa-IR',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_status` (`status`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_sort` (`sort_order`),
    KEY `idx_language` (`language`),
    KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='خدمات (Services)';

-- ============================================================
-- PartoCMS — Team Module
-- Schema: team_members
-- ============================================================
-- Author: Hooman Oliaei
-- License: Proprietary
-- Version: 1.0.0
-- Date: 2026-10-19
-- ============================================================

CREATE TABLE IF NOT EXISTS `team_members` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(100) NOT NULL COMMENT 'نام و نام خانوادگی',
    `position`       VARCHAR(100) DEFAULT NULL COMMENT 'سمت شغلی',
    `department`     VARCHAR(100) DEFAULT NULL COMMENT 'دپارتمان',
    `bio`            TEXT         DEFAULT NULL COMMENT 'بیوگرافی / توضیحات',
    `photo_url`      VARCHAR(255) DEFAULT NULL COMMENT 'URL عکس',
    `email`          VARCHAR(150) DEFAULT NULL,
    `phone`          VARCHAR(50)  DEFAULT NULL,
    `linkedin`       VARCHAR(255) DEFAULT NULL,
    `twitter`        VARCHAR(255) DEFAULT NULL,
    `instagram`      VARCHAR(255) DEFAULT NULL,
    `status`         ENUM('published','hidden') NOT NULL DEFAULT 'published',
    `sort_order`     INT(11)      NOT NULL DEFAULT 0 COMMENT 'ترتیب نمایش',
    `language`       VARCHAR(10)  NOT NULL DEFAULT 'fa-IR',
    `is_featured`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'نمایش در بخش ویژه',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status`     (`status`),
    KEY `idx_language`   (`language`),
    KEY `idx_sort`       (`sort_order`),
    KEY `idx_featured`   (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='اعضای تیم (Team Members)';

-- ============================================================
-- PartoCMS — Properties Module
-- Schema: properties
-- ============================================================
-- Author: Hooman Oliaei
-- License: Proprietary
-- Version: 1.0.0
-- Date: 2026-11-05
-- ============================================================

CREATE TABLE IF NOT EXISTS `properties` (
    `id`              INT(11)      NOT NULL AUTO_INCREMENT,
    `title`           VARCHAR(200) NOT NULL,
    `slug`            VARCHAR(200) DEFAULT NULL,
    `description`     LONGTEXT     DEFAULT NULL COMMENT 'توضیح کوتاه (TinyMCE)',
    `content`         LONGTEXT     DEFAULT NULL COMMENT 'محتوای کامل (TinyMCE)',

    -- نوع ملک و معامله
    `property_type`   ENUM('apartment','villa','land','office','shop','warehouse') NOT NULL DEFAULT 'apartment',
    `deal_type`       ENUM('sale','rent','mortgage') NOT NULL DEFAULT 'sale',

    -- مشخصات فیزیکی
    `area`            INT(11)      DEFAULT NULL COMMENT 'متراژ (متر مربع)',
    `bedrooms`        TINYINT      DEFAULT NULL,
    `bathrooms`       TINYINT      DEFAULT NULL,
    `floor`           INT(11)      DEFAULT NULL COMMENT 'طبقه',
    `total_floors`    INT(11)      DEFAULT NULL,
    `year_built`      SMALLINT     DEFAULT NULL,
    `document_type`   VARCHAR(50)  DEFAULT NULL COMMENT 'نوع سند',

    -- قیمت‌ها (فقط یکی از این‌ها پر می‌شود بر اساس deal_type)
    `price`           DECIMAL(15,2) DEFAULT NULL COMMENT 'قیمت کل (فروش)',
    `rent_price`      DECIMAL(15,2) DEFAULT NULL COMMENT 'اجاره ماهانه',
    `deposit`         DECIMAL(15,2) DEFAULT NULL COMMENT 'رهن/ودیعه',

    -- موقعیت
    `address`         VARCHAR(500) DEFAULT NULL,
    `city`            VARCHAR(100) DEFAULT NULL,
    `region`          VARCHAR(100) DEFAULT NULL,
    `latitude`        DECIMAL(10,7) DEFAULT NULL,
    `longitude`       DECIMAL(10,7) DEFAULT NULL,

    -- تصاویر
    `thumbnail_url`   VARCHAR(255) DEFAULT NULL,
    `gallery`         TEXT         DEFAULT NULL COMMENT 'JSON array',
    `amenities`       TEXT         DEFAULT NULL COMMENT 'JSON array (آسانسور، پارکینگ، ...)',

    -- مشاور (اتصال به Team)
    `agent_id`        INT(11)      DEFAULT NULL COMMENT 'FK به team_members',

    -- وضعیت و کنترل
    `status`          ENUM('published','hidden','sold','rented','pending') NOT NULL DEFAULT 'pending' COMMENT 'pending = در انتظار تأیید',
    `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`      INT(11)      NOT NULL DEFAULT 0,
    `language`        VARCHAR(10)  NOT NULL DEFAULT 'fa-IR',

    -- متادیتا
    `submitted_ip`    VARCHAR(45)  DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at`    TIMESTAMP    NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_status`         (`status`),
    KEY `idx_property_type`  (`property_type`),
    KEY `idx_deal_type`      (`deal_type`),
    KEY `idx_city`           (`city`),
    KEY `idx_region`         (`region`),
    KEY `idx_price`          (`price`),
    KEY `idx_area`           (`area`),
    KEY `idx_featured`       (`is_featured`),
    KEY `idx_sort`           (`sort_order`),
    KEY `idx_language`       (`language`),
    KEY `idx_agent`          (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='املاک (Properties)';

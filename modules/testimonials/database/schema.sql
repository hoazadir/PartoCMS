-- ============================================================
-- PartoCMS — Testimonials Module
-- Schema: testimonials
-- ============================================================
-- Author: Hooman Oliaei
-- License: Proprietary
-- Version: 1.0.0
-- Date: 2026-10-19
-- ============================================================

CREATE TABLE IF NOT EXISTS `testimonials` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(100) NOT NULL COMMENT 'نام مشتری',
    `email`          VARCHAR(150) DEFAULT NULL COMMENT 'ایمیل (اختیاری، برای تایید)',
    `company`        VARCHAR(100) DEFAULT NULL COMMENT 'نام شرکت',
    `position`       VARCHAR(100) DEFAULT NULL COMMENT 'سمت (مثل مدیرعامل)',
    `content`        TEXT         NOT NULL COMMENT 'متن نظر',
    `rating`         TINYINT(1)   DEFAULT NULL COMMENT '۱ تا ۵ (NULL = بدون امتیاز)',
    `avatar_url`     VARCHAR(255) DEFAULT NULL COMMENT 'URL آواتار (اختیاری)',
    `status`         ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
    `is_featured`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'نمایش در بخش ویژه',
    `sort_order`     INT(11)      NOT NULL DEFAULT 0 COMMENT 'ترتیب نمایش',
    `language`       VARCHAR(10)  NOT NULL DEFAULT 'fa-IR',
    `submitted_ip`   VARCHAR(45)  DEFAULT NULL COMMENT 'IP ثبت‌کننده (برای جلوگیری از spam)',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at`   TIMESTAMP    NULL DEFAULT NULL COMMENT 'زمان انتشار',
    PRIMARY KEY (`id`),
    KEY `idx_status`     (`status`),
    KEY `idx_featured`   (`is_featured`),
    KEY `idx_language`   (`language`),
    KEY `idx_sort`       (`sort_order`),
    KEY `idx_created`    (`created_at`),
    KEY `idx_published`  (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='نظرات مشتریان (Testimonials)';

-- ============================================================
-- تنظیمات ماژول (اگر جدول settings عمومی استفاده می‌شود)
-- ============================================================
-- کلیدها در جدول settings با پیشوند 'testimonials_' ذخیره می‌شوند:
--   testimonials_auto_approve       (bool) — تایید خودکار
--   testimonials_require_email      (bool) — ایمیل اجباری
--   testimonials_items_per_page     (int)  — تعداد در صفحه
--   testimonials_allow_guest        (bool) — اجازه ثبت به مهمان
--   testimonials_default_status     (str)  — pending|published
--   testimonials_show_avatar        (bool) — نمایش آواتار
--   testimonials_show_company       (bool) — نمایش شرکت
--   testimonials_show_rating        (bool) — نمایش امتیاز


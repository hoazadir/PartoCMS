-- ═══════════════════════════════════════════════════════════
-- PartoCMS - Newsletter Module Schema
-- Version: 1.0.0
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ═══════════════════════════════════════════════════════════

-- ─── ۱. Subscribers ───
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`           VARCHAR(254) NOT NULL,
    `name`            VARCHAR(100) DEFAULT NULL,
    `status`          ENUM('pending','active','unsubscribed','bounced')
                      NOT NULL DEFAULT 'pending',
    `token`           CHAR(64) NOT NULL,
    `confirmed_at`    DATETIME DEFAULT NULL,
    `unsubscribed_at` DATETIME DEFAULT NULL,
    `source`          VARCHAR(50) DEFAULT NULL,
    `ip`              VARCHAR(45) DEFAULT NULL,
    `user_agent`      VARCHAR(255) DEFAULT NULL,
    `metadata`        JSON DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_email`  (`email`),
    UNIQUE KEY `uniq_token`  (`token`),
    KEY `idx_status`         (`status`),
    KEY `idx_created`        (`created_at`),
    KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ۲. Campaigns ───
CREATE TABLE IF NOT EXISTS `newsletter_campaigns` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(200) NOT NULL,
    `subject`       VARCHAR(200) NOT NULL,
    `preheader`     VARCHAR(200) DEFAULT NULL,
    `body_html`     LONGTEXT NOT NULL,
    `body_text`     LONGTEXT DEFAULT NULL,
    `status`        ENUM('draft','scheduled','sending','sent','paused','failed')
                    NOT NULL DEFAULT 'draft',
    `scheduled_at`  DATETIME DEFAULT NULL,
    `started_at`    DATETIME DEFAULT NULL,
    `completed_at`  DATETIME DEFAULT NULL,
    `sent_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `open_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `click_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_status`         (`status`),
    KEY `idx_scheduled`      (`scheduled_at`),
    KEY `idx_status_sched`   (`status`, `scheduled_at`),
    KEY `idx_created_by`     (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ۳. Send logs ───
CREATE TABLE IF NOT EXISTS `newsletter_sends` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`    BIGINT UNSIGNED NOT NULL,
    `subscriber_id`  BIGINT UNSIGNED NOT NULL,
    `status`         ENUM('queued','sent','opened','clicked','failed','bounced')
                     NOT NULL DEFAULT 'queued',
    `message_id`     VARCHAR(255) DEFAULT NULL,
    `error_message`  TEXT DEFAULT NULL,
    `sent_at`        DATETIME DEFAULT NULL,
    `opened_at`      DATETIME DEFAULT NULL,
    `clicked_at`     DATETIME DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_campaign_subscriber` (`campaign_id`, `subscriber_id`),
    KEY `idx_status`     (`status`),
    KEY `idx_campaign`   (`campaign_id`),
    KEY `idx_subscriber` (`subscriber_id`),
    KEY `idx_queue`      (`campaign_id`, `status`),

    CONSTRAINT `fk_sends_campaign`
        FOREIGN KEY (`campaign_id`)   REFERENCES `newsletter_campaigns`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_sends_subscriber`
        FOREIGN KEY (`subscriber_id`) REFERENCES `newsletter_subscribers`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

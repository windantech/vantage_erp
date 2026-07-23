-- Scheduled broadcasts: queue a broadcast for a future time, fired by wa_cron.php.
-- Run once in phpMyAdmin against the ERP database.
CREATE TABLE IF NOT EXISTS `wa_scheduled_broadcasts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template`     VARCHAR(190) NOT NULL,
    `language`     VARCHAR(16)  NOT NULL DEFAULT 'en',
    `audience`     VARCHAR(32)  NOT NULL DEFAULT 'all',
    `course_id`    INT UNSIGNED DEFAULT NULL,
    `vars`         TEXT         DEFAULT NULL,
    `scheduled_at` DATETIME     NOT NULL,
    `status`       ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `total`        INT UNSIGNED NOT NULL DEFAULT 0,
    `sent`         INT UNSIGNED NOT NULL DEFAULT 0,
    `failed`       INT UNSIGNED NOT NULL DEFAULT 0,
    `broadcast_id` INT UNSIGNED DEFAULT NULL,
    `error`        VARCHAR(255) DEFAULT NULL,
    `created_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `run_at`       DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_wa_sched_due` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

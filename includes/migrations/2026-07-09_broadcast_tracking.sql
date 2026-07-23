-- Broadcast history + per-recipient delivery tracking.
-- Run once in phpMyAdmin against the ERP database.

CREATE TABLE IF NOT EXISTS `wa_broadcasts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template`   VARCHAR(190) NOT NULL,
    `language`   VARCHAR(16)  NOT NULL DEFAULT 'en',
    `audience`   VARCHAR(32)  NOT NULL DEFAULT 'all',
    `course_id`  INT UNSIGNED DEFAULT NULL,
    `total`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `wa_messages`
    ADD COLUMN `broadcast_id` INT UNSIGNED DEFAULT NULL AFTER `referral_ad_id`,
    ADD KEY `idx_wa_messages_broadcast` (`broadcast_id`);

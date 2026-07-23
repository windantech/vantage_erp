-- Canned quick replies for the thread reply box.
-- Run once in phpMyAdmin against the ERP database.
CREATE TABLE IF NOT EXISTS `wa_quick_replies` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(190) NOT NULL,
    `body`       MEDIUMTEXT   NOT NULL,
    `sort`       INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

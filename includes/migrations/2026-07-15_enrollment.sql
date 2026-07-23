-- Guided WhatsApp enrollment: one active capture session per contact.
-- Run once in phpMyAdmin against the ERP database.
CREATE TABLE IF NOT EXISTS `wa_enroll_sessions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_id` INT UNSIGNED NOT NULL,
    `wa_id`      VARCHAR(32)  NOT NULL,
    `ref_type`   ENUM('course','event') NOT NULL,
    `ref_id`     INT UNSIGNED NOT NULL,
    `step`       INT          NOT NULL DEFAULT 0,       -- index into the field list; -1 = confirm
    `data`       TEXT         DEFAULT NULL,             -- collected fields as JSON
    `status`     ENUM('offered','collecting','confirm','done','cancelled') NOT NULL DEFAULT 'collecting',
    `result_ref` VARCHAR(64)  DEFAULT NULL,             -- ticket_id / entry_id after finalize
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_enroll_contact` (`contact_id`),
    KEY `idx_wa_enroll_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Corporate training proposal requests submitted on the public website
-- (corporate-proposal.php), forwarded server-to-server to
-- includes/receive_corporate_proposal.php.
--
-- The receiver self-provisions these tables (CREATE TABLE IF NOT EXISTS) on first POST,
-- so running this file is optional — it's kept for reference / manual setup.
--
-- SETUP: seed the shared secret ONCE (replace the value with output of `openssl rand -hex 32`).
-- Both the backend (reads it here) and the frontend (we hand it the value) use the same string;
-- the frontend never queries the DB.
--
--   CREATE TABLE IF NOT EXISTS `app_settings` (
--     `setting_key` VARCHAR(120) NOT NULL,
--     `setting_value` TEXT DEFAULT NULL,
--     `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     PRIMARY KEY (`setting_key`)
--   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
--   INSERT INTO `app_settings` (`setting_key`, `setting_value`)
--   VALUES ('corporate_proposal_secret', 'PASTE_A_LONG_RANDOM_STRING')
--   ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

CREATE TABLE IF NOT EXISTS `corporate_proposals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_name` VARCHAR(190) NOT NULL,
    `contact_email` VARCHAR(190) NOT NULL,
    `contact_phone` VARCHAR(60) NOT NULL,
    `org_name` VARCHAR(190) NOT NULL,
    `org_country` VARCHAR(100) NOT NULL,
    `org_sector` VARCHAR(120) NOT NULL,
    `org_size` VARCHAR(60) DEFAULT NULL,
    `city` VARCHAR(120) DEFAULT NULL,
    `participants_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `preferred_delivery` VARCHAR(80) NOT NULL,
    `preferred_dates` VARCHAR(190) DEFAULT NULL,
    `budget_range` VARCHAR(120) DEFAULT NULL,
    `audience_profile` TEXT DEFAULT NULL,
    `areas_of_interest` TEXT DEFAULT NULL,          -- JSON array of the checkbox values
    `additional_notes` TEXT DEFAULT NULL,
    `status` ENUM('new','contacted','proposal_sent','won','lost') NOT NULL DEFAULT 'new',
    `assigned_to` INT DEFAULT NULL,                 -- CRM staff attribution (BDE/BDO)
    `source` VARCHAR(60) DEFAULT 'website',
    `submitted_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_email` (`contact_email`),
    KEY `idx_submitted` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

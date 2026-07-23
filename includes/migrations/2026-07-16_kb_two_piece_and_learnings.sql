-- Two-piece knowledge base + "learn from the team" review queue, and the
-- registration "offer" step for enrollment.
-- Run once in phpMyAdmin against the ERP database. (The code also self-heals
-- these via wa_kb_ensure_schema() / wa_enroll_ensure_schema(), so a missed run
-- won't break anything.)

-- 0) Enrollment can now first OFFER the registration link before collecting.
ALTER TABLE `wa_enroll_sessions`
    MODIFY COLUMN `status` ENUM('offered','collecting','confirm','done','cancelled')
    NOT NULL DEFAULT 'collecting';

-- 0b) Training programmes (themes: M&E, Data Analysis, Academic Programs, ...).
--     Their country/location/dates are read LIVE from the Event table by matching
--     `keywords` against event titles; each programme has its own KB (ref_type
--     'program' below).
ALTER TABLE `wa_knowledge`
    MODIFY COLUMN `ref_type` ENUM('course','event','program') NOT NULL;

CREATE TABLE IF NOT EXISTS `wa_programs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(190) NOT NULL,
    `keywords`   VARCHAR(500) DEFAULT NULL,   -- comma-separated, matched vs event_title
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_program_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Store the KB in two pieces: the raw human input (body, already present) and
--    an AI-processed, cleanly-bulleted version (body_ai) that the live AI reads.
ALTER TABLE `wa_knowledge`
    ADD COLUMN IF NOT EXISTS `body_ai` MEDIUMTEXT DEFAULT NULL AFTER `body`,
    ADD COLUMN IF NOT EXISTS `ai_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `body_ai`;

-- 2) Things a human agent told a client about a specific course/event, captured
--    for a supervisor to review and (if correct) fold into that KB.
CREATE TABLE IF NOT EXISTS `wa_kb_learnings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref_type`     ENUM('course','event') NOT NULL,
    `ref_id`       INT UNSIGNED NOT NULL,
    `conversation_id` INT UNSIGNED DEFAULT NULL,
    `contact_id`   INT UNSIGNED DEFAULT NULL,
    `message_id`   BIGINT UNSIGNED DEFAULT NULL,        -- the outbound human reply
    `body`         MEDIUMTEXT   NOT NULL,               -- what the human said
    `status`       ENUM('pending','approved','dismissed') NOT NULL DEFAULT 'pending',
    `created_by`   INT UNSIGNED DEFAULT NULL,           -- staff who said it
    `reviewed_by`  INT UNSIGNED DEFAULT NULL,           -- supervisor who acted
    `reviewed_at`  DATETIME     DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wa_kb_learn_ref` (`ref_type`, `ref_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

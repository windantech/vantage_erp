-- =====================================================================
-- Vantage WhatsApp module — migration for the EXISTING ERP database.
-- Adds only wa_* tables; it does NOT touch register / course / Event /
-- staff / registered_users (those are read live for routing).
--
-- Run once against the ERP database:
--   mysql -u <erp_user> -p <erp_db> < whatsapp/sql/install.sql
-- or import via phpMyAdmin into the ERP database.
--
-- Safe to re-run (CREATE TABLE IF NOT EXISTS). utf8mb4 for Swahili + emoji.
-- =====================================================================

SET NAMES utf8mb4;

-- WhatsApp contacts (people who message the branded number).
CREATE TABLE IF NOT EXISTS `wa_contacts` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wa_id`           VARCHAR(32)  NOT NULL,            -- phone id, digits only
    `profile_name`    VARCHAR(190) DEFAULT NULL,
    `opted_in`        TINYINT(1)   NOT NULL DEFAULT 0,
    `opted_out`       TINYINT(1)   NOT NULL DEFAULT 0,   -- said STOP: excluded from ALL broadcasts
    `opted_out_at`    DATETIME     DEFAULT NULL,
    `last_inbound_at` DATETIME     DEFAULT NULL,        -- drives the 24h window
    `register_id`     INT UNSIGNED DEFAULT NULL,        -- optional link to register.id (lead)
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_contacts_wa_id` (`wa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Current assignment of a contact to a course/event + the owning staff user.
-- ref_type/ref_id point at the ERP course.course_id or Event.event_id.
-- assigned_user_id is a registered_users.id (the value inside assigned_to).
CREATE TABLE IF NOT EXISTS `wa_conversations` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_id`       INT UNSIGNED NOT NULL,
    `ref_type`         ENUM('course','event','unknown') NOT NULL DEFAULT 'unknown',
    `ref_id`           INT UNSIGNED DEFAULT NULL,        -- course.course_id OR Event.event_id
    `assigned_user_id` INT UNSIGNED DEFAULT NULL,        -- registered_users.id of the owning staff
    `handler`          ENUM('ai','human') NOT NULL DEFAULT 'ai',
    `escalated`        TINYINT(1)   NOT NULL DEFAULT 0,
    `status`           ENUM('open','closed') NOT NULL DEFAULT 'open',
    `last_route_reason`     VARCHAR(64)  DEFAULT NULL,  -- how it was routed (keyword/ai/ad)
    `last_route_confidence` DECIMAL(4,3) DEFAULT NULL,  -- 0.000 - 1.000
    `last_read_at`     DATETIME     DEFAULT NULL,        -- when a staff member last opened it (drives unread)
    `last_human_at`    DATETIME     DEFAULT NULL,        -- when a human last replied (pauses the AI briefly)
    `last_message_at`  DATETIME     DEFAULT NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_conv_contact` (`contact_id`),
    KEY `idx_wa_conv_assigned` (`assigned_user_id`),
    KEY `idx_wa_conv_ref` (`ref_type`, `ref_id`),
    CONSTRAINT `fk_wa_conv_contact` FOREIGN KEY (`contact_id`)
        REFERENCES `wa_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every inbound + outbound message. UNIQUE wa_message_id = de-dup.
CREATE TABLE IF NOT EXISTS `wa_messages` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `wa_message_id`  VARCHAR(128) DEFAULT NULL,
    `contact_id`     INT UNSIGNED NOT NULL,
    `direction`      ENUM('inbound','outbound') NOT NULL,
    `type`           VARCHAR(32)  NOT NULL DEFAULT 'text',
    `body`           MEDIUMTEXT   DEFAULT NULL,
    `media_id`       VARCHAR(190) DEFAULT NULL,
    `media_mime`     VARCHAR(120) DEFAULT NULL,
    `referral_ad_id` VARCHAR(190) DEFAULT NULL,
    `broadcast_id`   INT UNSIGNED DEFAULT NULL,          -- set when sent as part of a broadcast
    `wa_timestamp`   DATETIME     DEFAULT NULL,
    `status`         VARCHAR(32)  DEFAULT NULL,          -- sent -> delivered -> read (or failed)
    `raw_payload`    JSON         DEFAULT NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_messages_wamid` (`wa_message_id`),
    KEY `idx_wa_messages_contact` (`contact_id`),
    KEY `idx_wa_messages_broadcast` (`broadcast_id`),
    CONSTRAINT `fk_wa_messages_contact` FOREIGN KEY (`contact_id`)
        REFERENCES `wa_contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-approved Meta templates (for proactive / outside-window sends).
CREATE TABLE IF NOT EXISTS `wa_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(190) NOT NULL,
    `language`   VARCHAR(16)  NOT NULL DEFAULT 'en',
    `category`   VARCHAR(32)  DEFAULT NULL,
    `body`       MEDIUMTEXT   DEFAULT NULL,
    `status`     ENUM('approved','pending','rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_templates` (`name`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per broadcast run. Per-recipient delivery is derived by joining
-- wa_messages on broadcast_id (status: sent -> delivered -> read / failed).
CREATE TABLE IF NOT EXISTS `wa_broadcasts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template`   VARCHAR(190) NOT NULL,
    `language`   VARCHAR(16)  NOT NULL DEFAULT 'en',
    `audience`   VARCHAR(32)  NOT NULL DEFAULT 'all',    -- all | optedin | course
    `course_id`  INT UNSIGNED DEFAULT NULL,
    `total`      INT UNSIGNED NOT NULL DEFAULT 0,        -- intended recipients
    `created_by` INT UNSIGNED DEFAULT NULL,              -- staff user id
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Broadcasts queued for a future time. A cron-driven runner (wa_cron.php)
-- picks up due rows, resolves the audience THEN (so opt-outs are honoured at
-- send time) and executes them, linking to the wa_broadcasts run it creates.
CREATE TABLE IF NOT EXISTS `wa_scheduled_broadcasts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template`     VARCHAR(190) NOT NULL,
    `language`     VARCHAR(16)  NOT NULL DEFAULT 'en',
    `audience`     VARCHAR(32)  NOT NULL DEFAULT 'all',
    `course_id`    INT UNSIGNED DEFAULT NULL,
    `vars`         TEXT         DEFAULT NULL,             -- JSON array of variable values
    `scheduled_at` DATETIME     NOT NULL,
    `status`       ENUM('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    `total`        INT UNSIGNED NOT NULL DEFAULT 0,
    `sent`         INT UNSIGNED NOT NULL DEFAULT 0,
    `failed`       INT UNSIGNED NOT NULL DEFAULT 0,
    `broadcast_id` INT UNSIGNED DEFAULT NULL,             -- the run it produced
    `error`        VARCHAR(255) DEFAULT NULL,
    `created_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `run_at`       DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_wa_sched_due` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canned quick replies agents can insert into the thread reply box.
-- {name} in the body is substituted with the contact's name on insert.
CREATE TABLE IF NOT EXISTS `wa_quick_replies` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(190) NOT NULL,
    `body`       MEDIUMTEXT   NOT NULL,
    `ref_type`   ENUM('course','event') DEFAULT NULL,    -- NULL = global (all chats); else only that course/event's chats
    `ref_id`     INT UNSIGNED DEFAULT NULL,
    `sort`       INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Click-to-WhatsApp ad mapping: ad_id -> a course or event.
CREATE TABLE IF NOT EXISTS `wa_ad_map` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id`      VARCHAR(190) NOT NULL,
    `ref_type`   ENUM('course','event') NOT NULL,
    `ref_id`     INT UNSIGNED NOT NULL,               -- course.course_id OR Event.event_id
    `label`      VARCHAR(190) DEFAULT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_ad_map_ad` (`ad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-course / per-event AI knowledge base (kept out of ERP tables). Stored in
-- two pieces: body = the raw human input (what admins type / edit), body_ai =
-- the AI-processed, cleanly-bulleted version the live AI actually answers from.
CREATE TABLE IF NOT EXISTS `wa_knowledge` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref_type`   ENUM('course','event','program') NOT NULL,
    `ref_id`     INT UNSIGNED NOT NULL,
    `body`       MEDIUMTEXT   DEFAULT NULL,            -- raw input: fees / schedule / FAQ
    `body_ai`    MEDIUMTEXT   DEFAULT NULL,            -- AI-processed bullets (what the AI reads)
    `ai_updated_at` TIMESTAMP NULL DEFAULT NULL,       -- when body_ai was last regenerated
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_knowledge` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training programmes (themes: M&E, Data Analysis, Academic Programs, ...). Their
-- country/location/dates come LIVE from the Event table by matching `keywords`
-- against event titles; each has its own KB (wa_knowledge ref_type='program').
CREATE TABLE IF NOT EXISTS `wa_programs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(190) NOT NULL,
    `keywords`   VARCHAR(500) DEFAULT NULL,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_program_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Things a human agent told a client about a specific course/event, captured for
-- a supervisor to review and (if correct) fold into that course's knowledge base.
CREATE TABLE IF NOT EXISTS `wa_kb_learnings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref_type`     ENUM('course','event') NOT NULL,
    `ref_id`       INT UNSIGNED NOT NULL,
    `conversation_id` INT UNSIGNED DEFAULT NULL,
    `contact_id`   INT UNSIGNED DEFAULT NULL,
    `message_id`   BIGINT UNSIGNED DEFAULT NULL,
    `body`         MEDIUMTEXT   NOT NULL,
    `status`       ENUM('pending','approved','dismissed') NOT NULL DEFAULT 'pending',
    `created_by`   INT UNSIGNED DEFAULT NULL,
    `reviewed_by`  INT UNSIGNED DEFAULT NULL,
    `reviewed_at`  DATETIME     DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wa_kb_learn_ref` (`ref_type`, `ref_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual course/event -> rep fallback, used ONLY when the ERP's own
-- course.assigned_to / Event.assigned_to has no owner for that ref.
CREATE TABLE IF NOT EXISTS `wa_course_owner` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref_type`   ENUM('course','event') NOT NULL,
    `ref_id`     INT UNSIGNED NOT NULL,               -- course.course_id OR Event.event_id
    `user_id`    INT UNSIGNED NOT NULL,               -- registered_users.id of the rep
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_course_owner` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Simple key/value settings (active AI provider, auto-reply toggle, …).
CREATE TABLE IF NOT EXISTS `wa_settings` (
    `key`        VARCHAR(64)  NOT NULL,
    `value`      VARCHAR(500) DEFAULT NULL,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

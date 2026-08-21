-- =====================================================================
-- Phase 2.2 — Vala call memory.
--
-- Run ONCE against the ERP database, as an administrator, BEFORE deploying the
-- complete_call endpoint:
--
--     mysql -u <admin_user> -p <erp_db> < db_schema/wa_voice_phase22.sql
--
-- No database is named here on purpose, so it applies to any environment by
-- selecting the schema on the command line. Safe to re-run.
--
-- A RECORDED CALL IS IMMUTABLE.
--
-- vantage_voice holds SELECT and INSERT on these three tables. It holds no
-- UPDATE anywhere, so once a call has been written neither a retry, a replayed
-- spool file nor a forged resubmission can alter its summary, its contact, its
-- timestamps, its programmes or its follow-up fields. The first valid
-- submission wins and a second returns duplicate without touching anything.
--
-- That is enforced by MySQL rather than by the endpoint remembering to check.
-- The interest actions ARE updated as they are processed — but by the cron,
-- which connects as the application, not by the voice account.
--
-- THE VOICE ACCOUNT WRITES HERE AND NOWHERE ELSE. It
-- has no write on wa_messages, wa_conversations, wa_contacts, course, Event or
-- wa_knowledge — a phone call cannot reach into the CRM's own records. An
-- interest change is INSERTED here as a pending action and applied later by the
-- privileged cron, which re-validates it and uses the module's existing routing
-- and ownership rules. The grants are in the deployment notes, not in this file:
-- an account name beside a password does not belong in a repository.
--
-- NO TRANSCRIPT AND NO AUDIO. Not in these tables, not in any table. The voice
-- service holds a bounded transcript in memory for the length of one call and
-- drops it once the summary exists.
--
-- ref_type is ('course','event','program') because that is what the module
-- actually uses. Academic and online courses are Event rows marked 'ACADEMIC#',
-- corporate ones 'CORPORATE#', so `event` already covers them; adding types the
-- rest of the CRM does not recognise would create references nothing can resolve.
--
-- IF NOT EXISTS on an index requires MariaDB 10.1.4+ / MySQL 8.0.29+.
-- =====================================================================

-- ---------------------------------------------------------------------
-- One row per completed call.
-- ---------------------------------------------------------------------
-- call_id is the OpenAI Realtime call identifier and it is UNIQUE, which is what
-- makes finalisation idempotent for free: a retried submission, a drained spool
-- file and a duplicate webhook all collide on the same key instead of producing
-- three records of one conversation.
--
-- contact_id is NULLABLE on purpose. A caller we do not recognise still made a
-- call worth recording, and creating a wa_contacts row for them would put a
-- person who has never sent a WhatsApp message into the triage pool, the inbox
-- counts and the broadcast audience.
CREATE TABLE IF NOT EXISTS `wa_voice_calls` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `call_id`               VARCHAR(128) NOT NULL,
    `contact_id`            INT UNSIGNED NULL DEFAULT NULL,
    `conversation_id`       INT UNSIGNED NULL DEFAULT NULL,
    -- 254*****48. Enough to correlate two records, never a telephone number.
    `caller_masked`         VARCHAR(24)  NOT NULL DEFAULT '',
    `started_at`            DATETIME     NOT NULL,
    `ended_at`              DATETIME     NULL DEFAULT NULL,
    `duration_seconds`      INT UNSIGNED NULL DEFAULT NULL,
    `outcome`               ENUM('completed','transferred','disconnected','failed')
                            NOT NULL DEFAULT 'completed',
    `summary`               VARCHAR(1200) NULL DEFAULT NULL,
    `questions_answered`    VARCHAR(600)  NULL DEFAULT NULL,
    `unresolved_questions`  VARCHAR(600)  NULL DEFAULT NULL,
    `objections_or_concerns` VARCHAR(600) NULL DEFAULT NULL,
    `requested_next_step`   VARCHAR(255)  NULL DEFAULT NULL,
    `follow_up_required`    TINYINT(1)    NOT NULL DEFAULT 0,
    `follow_up_priority`    ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    `requested_callback_at` DATETIME      NULL DEFAULT NULL,
    `transfer_requested`    TINYINT(1)    NOT NULL DEFAULT 0,
    `transfer_completed`    TINYINT(1)    NOT NULL DEFAULT 0,
    -- Which produced the summary. 'fallback' is assembled from structured facts
    -- when the model failed; 'none' is a call with nothing to summarise. A rep
    -- reading a terse card deserves to know which of the three they are looking at.
    `summary_source`        ENUM('model','fallback','none') NOT NULL DEFAULT 'none',
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Deliberately NOT `ON UPDATE CURRENT_TIMESTAMP`. The voice account has no
    -- UPDATE on this table, so the clause would only ever be dead — and a column
    -- that looks like it tracks modifications, on a row nothing may modify,
    -- invites somebody to write the UPDATE it implies is expected.
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_voice_call` (`call_id`),
    -- The thread card reads by contact, newest first.
    KEY `idx_wa_voice_contact` (`contact_id`, `started_at`),
    KEY `idx_wa_voice_conv` (`conversation_id`, `started_at`),
    -- The follow-up queue reads by priority within what still needs doing.
    KEY `idx_wa_voice_followup` (`follow_up_required`, `follow_up_priority`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Which programmes a call touched, one row each.
-- ---------------------------------------------------------------------
-- A comma-separated list would have been smaller and unqueryable: "how many
-- calls discussed the Kigali event" is the question this table exists to answer,
-- and it cannot be asked of a string.
--
-- `relation` is the whole point of Phase 2.2's interest model. A programme that
-- came up in conversation is 'discussed'. What the CRM already believed is
-- 'previous_interest'. Only a caller's plain, in-call agreement produces
-- 'confirmed_interest' — never the summariser, never an inference.
CREATE TABLE IF NOT EXISTS `wa_voice_call_programmes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `voice_call_id` INT UNSIGNED NOT NULL,
    `ref_type`      ENUM('course','event','program') NOT NULL,
    `ref_id`        INT UNSIGNED NOT NULL,
    `relation`      ENUM('discussed','previous_interest','confirmed_interest') NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- The same programme may legitimately be both discussed and confirmed, so
    -- the relation is part of the key. What this prevents is a retried
    -- submission inserting the same relation twice.
    UNIQUE KEY `uq_wa_voice_prog` (`voice_call_id`, `ref_type`, `ref_id`, `relation`),
    KEY `idx_wa_voice_prog_ref` (`ref_type`, `ref_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Interest changes, proposed by a call and applied by the privileged cron.
-- ---------------------------------------------------------------------
-- This table is the privilege boundary made concrete. The voice account can
-- INSERT a row saying "this caller confirmed they now want the Data Analysis
-- programme". It cannot act on it. The cron — which runs as the application and
-- does have the rights — re-validates the reference, checks the conversation is
-- still safe to reroute, applies the module's existing routing, and records what
-- happened including which rep held it before and after.
--
-- Everything about it is append-then-stamp: a row is written once as 'pending'
-- and only ever has its status, owner, attempt and error columns updated. It is
-- the audit trail for a class of change the CRM has never had one for.
CREATE TABLE IF NOT EXISTS `wa_voice_interest_actions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `voice_call_id`     INT UNSIGNED NOT NULL,
    `contact_id`        INT UNSIGNED NOT NULL,
    `conversation_id`   INT UNSIGNED NULL DEFAULT NULL,
    `from_ref_type`     ENUM('course','event','program','unknown') NULL DEFAULT NULL,
    `from_ref_id`       INT UNSIGNED NULL DEFAULT NULL,
    `to_ref_type`       ENUM('course','event','program') NOT NULL,
    `to_ref_id`         INT UNSIGNED NOT NULL,
    -- Set only by the in-call confirmation state machine. A row without it is
    -- never applied, whatever else it says.
    `confirmation_recorded` TINYINT(1) NOT NULL DEFAULT 0,
    `status`            ENUM('pending','applied','rejected','failed') NOT NULL DEFAULT 'pending',
    `previous_owner_id` INT UNSIGNED NULL DEFAULT NULL,
    `resulting_owner_id` INT UNSIGNED NULL DEFAULT NULL,
    `attempts`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`        VARCHAR(190) NULL DEFAULT NULL,
    `processed_at`      DATETIME NULL DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- One action per call. A resubmitted complete_call cannot queue a second
    -- reroute, and the cron cannot apply one twice.
    `idempotency_key`   VARCHAR(160) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wa_voice_action` (`idempotency_key`),
    KEY `idx_wa_voice_action_due` (`status`, `attempts`, `created_at`),
    KEY `idx_wa_voice_action_contact` (`contact_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify afterwards:
--   SHOW CREATE TABLE `wa_voice_calls`;
--   SHOW GRANTS FOR 'vantage_voice'@'localhost';
--   -- vantage_voice must show SELECT, INSERT on these three tables and SELECT
--   -- elsewhere. If it shows UPDATE on ANY table, INSERT on wa_messages, or any
--   -- write on wa_conversations, the Phase 2.2 grants were applied wrongly.
--   -- Revoke them: a recorded call must not be rewritable by the thing that
--   -- recorded it.

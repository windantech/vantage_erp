-- =====================================================================
-- Phase 2.1A — voice context API security tables.
--
-- Run ONCE against the ERP database, as an administrator, BEFORE deploying
-- wa_voice_api.php:
--
--     mysql -u <admin_user> -p <erp_db> < db_schema/wa_voice_phase21a.sql
--
-- No database is named in this file on purpose, so it can be applied to any
-- environment by selecting the schema on the command line. Safe to re-run.
--
-- WHY THIS IS A MIGRATION AND NOT A SCHEMA-ENSURE.
--
-- The rest of the WhatsApp module creates its tables lazily, from inside a
-- request, with CREATE TABLE IF NOT EXISTS. The voice API deliberately does not:
-- a public request path that can create a table is a path that needs the CREATE
-- privilege, and the whole point of the restricted account below is that it has
-- none. At runtime the endpoint only CHECKS that both tables exist (against
-- information_schema) and answers 503 schema_unavailable if either is missing, so
-- a forgotten migration is loud rather than a silently disabled replay guard.
--
-- THE DATABASE USER AND ITS GRANTS ARE NOT IN THIS FILE.
--
-- They are configured separately during deployment, because a committed file is
-- the wrong place for an account name paired with a password, and a GRANT with a
-- placeholder in it is one careless paste away from being executed for real.
-- The deployment notes carry the CREATE USER and GRANT statements. In outline,
-- the voice account needs:
--
--   SELECT  on the CRM tables the three read-only actions use
--           (wa_contacts, wa_conversations, wa_messages, wa_enroll_sessions,
--            wa_programs, wa_knowledge, wa_settings, registered_users, staff,
--            course, Event)
--   INSERT, DELETE          on wa_voice_nonces
--   INSERT, UPDATE, DELETE  on wa_voice_rate
--
-- and must NOT hold CREATE, ALTER, DROP or INDEX anywhere, nor any write
-- privilege on a CRM table. Its credentials live only in the server-only file
-- outside the document root (see includes/wa_voice_secrets.sample.php), never in
-- this repository.
-- =====================================================================

-- Replay protection for signed API requests.
-- The stored value is sha256(key_id || '|' || nonce): fixed width, the raw nonce
-- is never kept, and a nonce is scoped to the key that used it. The endpoint
-- claims one with INSERT IGNORE and treats "0 rows affected" as a replay, so the
-- primary key IS the check — there is no read-then-write for two simultaneous
-- copies of a request to race through.
CREATE TABLE IF NOT EXISTS `wa_voice_nonces` (
    `nonce_hash` CHAR(64)     NOT NULL,
    `seen_at`    INT UNSIGNED NOT NULL,
    PRIMARY KEY (`nonce_hash`),
    KEY `idx_voice_nonce_seen` (`seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

-- Fixed-window rate limiting, per API key and per caller number.
-- `bucket` holds the key id for scope='key', and an HMAC of the caller's number
-- for scope='phone' — never the number itself, so this table cannot become an
-- unmanaged second record of who telephones the business.
-- One row per bucket per window, no per-request history to retain or leak.
-- The counter is incremented and read back in ONE statement via
-- LAST_INSERT_ID(hits + 1), which is why the account needs UPDATE here but no
-- SELECT on this table.
CREATE TABLE IF NOT EXISTS `wa_voice_rate` (
    `scope`        VARCHAR(8)   NOT NULL,
    `bucket`       VARCHAR(64)  NOT NULL,
    `window_start` INT UNSIGNED NOT NULL,
    `hits`         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`scope`, `bucket`, `window_start`),
    KEY `idx_voice_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

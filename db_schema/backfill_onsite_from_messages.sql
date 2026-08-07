-- ===========================================================================
-- BACKFILL 2: recover onsite enquiries from BEFORE delivery_mode existed
--
-- delivery_mode shipped on 2026-08-03 (commit 116e17c3). Every chat before that
-- is 'unknown' no matter how plainly the client asked for in-person training --
-- the column simply was not being written yet. That is why the audit's "GAP"
-- section is full of pre-3-August chats saying "In person training", "Onsite",
-- "Physical class".
--
-- This reads the actual message text and sets delivery_mode retrospectively,
-- using the same rules as wa_detect_delivery_mode(): an in-person cue AND no
-- online cue in the same message.
--
-- Run 1 -> 2 -> 3. READ each SELECT before the UPDATE under it.
-- Then re-run backfill_onsite_program_owner.sql step 3a to give them reps.
-- ===========================================================================

SET time_zone = '+03:00';

-- ---------------------------------------------------------------------------
-- 1. PREVIEW — chats that plainly said onsite but were never recorded
-- ---------------------------------------------------------------------------
SELECT cv.id                AS conv_id,
       c.wa_id,
       c.profile_name,
       cv.delivery_mode     AS recorded_now,
       COUNT(*)             AS onsite_msgs,
       MIN(m.wa_timestamp)  AS first_said_onsite,
       SUBSTRING(MIN(CONCAT(m.wa_timestamp, '|', m.body)), 20, 90) AS what_they_said
  FROM wa_messages m
  JOIN wa_contacts c       ON c.id = m.contact_id
  JOIN wa_conversations cv ON cv.contact_id = c.id
 WHERE m.direction = 'inbound'
   AND cv.delivery_mode = 'unknown'
   AND m.body REGEXP '(on[[:space:]-]?site|in[[:space:]-]?person|physical|face[[:space:]-]?to[[:space:]-]?face|classroom|in[[:space:]-]?class)'
   AND m.body NOT REGEXP '(virtual|on[[:space:]-]?line|online|remote|zoom|web[[:space:]-]?based|e[[:space:]-]?learn)'
 GROUP BY cv.id, c.wa_id, c.profile_name, cv.delivery_mode
 ORDER BY first_said_onsite DESC;

-- ---------------------------------------------------------------------------
-- 2. APPLY — record them as onsite
--    Only touches rows still 'unknown', so a mode the app has since recorded
--    (or a human corrected) is never overwritten. Safe to re-run.
-- ---------------------------------------------------------------------------
UPDATE wa_conversations cv
   SET cv.delivery_mode = 'onsite'
 WHERE cv.delivery_mode = 'unknown'
   AND EXISTS (
        SELECT 1 FROM wa_messages m
         WHERE m.contact_id = cv.contact_id
           AND m.direction = 'inbound'
           AND m.body REGEXP '(on[[:space:]-]?site|in[[:space:]-]?person|physical|face[[:space:]-]?to[[:space:]-]?face|classroom|in[[:space:]-]?class)'
           AND m.body NOT REGEXP '(virtual|on[[:space:]-]?line|online|remote|zoom|web[[:space:]-]?based|e[[:space:]-]?learn)');

-- ---------------------------------------------------------------------------
-- 3. THE THREE 'Data Analysis Using SPSS' CHATS the first backfill missed
--    Plain LIKE could not pair that course title to the keyword "Data Analysis
--    training", so step 3a skipped them. The live router now matches word by
--    word and handles new ones; these existing three need placing by hand.
--    Check the id, then run the UPDATE.
-- ---------------------------------------------------------------------------
SELECT id, name, keywords, assigned_to FROM wa_programs
 WHERE name LIKE '%Data Analysis%';

-- UPDATE wa_conversations cv
--   JOIN wa_programs p ON p.id = <PUT THE DATA ANALYSIS PROGRAMME ID HERE>
--    SET cv.program_id        = p.id,
--        cv.assigned_user_id  = CAST(SUBSTRING_INDEX(p.assigned_to, ',', 1) AS UNSIGNED),
--        cv.last_route_reason = 'program_backfill'
--  WHERE (cv.assigned_user_id IS NULL OR cv.assigned_user_id = '')
--    AND cv.delivery_mode = 'onsite'
--    AND cv.ref_type = 'course'
--    AND cv.ref_id IN (SELECT course_id FROM course WHERE course LIKE '%Data Analysis%');

-- ---------------------------------------------------------------------------
-- 4. VERIFY
-- ---------------------------------------------------------------------------
SELECT delivery_mode, COUNT(*) AS chats,
       SUM(assigned_user_id IS NULL OR assigned_user_id = '') AS unassigned
  FROM wa_conversations GROUP BY delivery_mode;

-- ===========================================================================
-- ROLLBACK for step 2 is not possible by flag alone -- it does not stamp a
-- marker, because delivery_mode has no spare state to record "backfilled".
-- If you want to be able to undo it, snapshot first:
--   CREATE TABLE wa_conv_mode_backup AS
--     SELECT id, delivery_mode FROM wa_conversations WHERE delivery_mode = 'unknown';
-- and restore with:
--   UPDATE wa_conversations cv JOIN wa_conv_mode_backup b ON b.id = cv.id
--      SET cv.delivery_mode = b.delivery_mode;
-- ===========================================================================

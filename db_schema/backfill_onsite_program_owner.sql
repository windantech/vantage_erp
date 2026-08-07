-- ===========================================================================
-- BACKFILL: give existing onsite-but-unlocated WhatsApp chats a programme rep
--
-- These are the chats that went unfollowed: the client said "in person" but
-- never named a country, so routing deliberately left assigned_user_id NULL
-- and nobody saw them. Assign each to its training programme's first rep so
-- it appears in a real inbox.
--
-- Run steps 1-3 IN ORDER and READ each SELECT before running the UPDATE.
-- Deploy the code first: step 0 needs the new columns to exist.
-- ===========================================================================

SET time_zone = '+03:00';   -- app runs Nairobi (EAT)

-- ---------------------------------------------------------------------------
-- 0. Columns (no-ops once the app has run at least once after deploy)
-- ---------------------------------------------------------------------------
ALTER TABLE `wa_programs`
    ADD COLUMN IF NOT EXISTS `assigned_to` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `wa_conversations`
    ADD COLUMN IF NOT EXISTS `program_id` INT UNSIGNED DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 1. Set reps on your programmes  (nothing works until this is done)
--    First id in the CSV = who unlocated onsite chats are assigned to.
--    All ids in the CSV can see those chats in their inbox.
--    Find staff ids:
--      SELECT id, fullname FROM registered_users WHERE FIND_IN_SET('44', role);
-- ---------------------------------------------------------------------------
SELECT id, name, keywords, assigned_to FROM wa_programs ORDER BY name;

-- UPDATE wa_programs SET assigned_to = '12,7' WHERE name = 'Data Analysis & Visualization';
-- UPDATE wa_programs SET assigned_to = '5'    WHERE name = 'M&E Trainings';
-- UPDATE wa_programs SET assigned_to = '9'    WHERE name = 'Academic Programs';

-- ---------------------------------------------------------------------------
-- 2. PREVIEW — what would be assigned, and to whom
--    Read this list. If a chat matches the WRONG programme, fix it by hand in
--    step 3b rather than running 3a.
-- ---------------------------------------------------------------------------
SELECT cv.id                AS conv_id,
       c.wa_id,
       c.profile_name,
       co.course            AS bound_course,
       p.name               AS programme,
       p.assigned_to        AS programme_reps,
       CAST(SUBSTRING_INDEX(p.assigned_to, ',', 1) AS UNSIGNED) AS will_assign_to,
       ru.fullname          AS will_assign_name,
       cv.delivery_mode,
       cv.last_route_reason,
       cv.last_message_at
  FROM wa_conversations cv
  JOIN wa_contacts c   ON c.id = cv.contact_id
  JOIN course co       ON co.course_id = cv.ref_id AND cv.ref_type = 'course'
  JOIN wa_programs p   ON p.status = 1
                      AND COALESCE(p.assigned_to, '') <> ''
                      AND (co.course LIKE CONCAT('%', p.keywords, '%')
                           OR co.course LIKE CONCAT('%', p.name, '%'))
  LEFT JOIN registered_users ru
         ON ru.id = CAST(SUBSTRING_INDEX(p.assigned_to, ',', 1) AS UNSIGNED)
 WHERE (cv.assigned_user_id IS NULL OR cv.assigned_user_id = '')
   AND (cv.last_route_reason = 'await_onsite_location' OR cv.delivery_mode = 'onsite')
 ORDER BY cv.last_message_at DESC;

-- ---------------------------------------------------------------------------
-- 3a. APPLY — assign the matched chats to their programme's first rep
--     Only touches chats that are currently UNASSIGNED, so it can never take a
--     chat away from a human who already owns it. Safe to re-run.
-- ---------------------------------------------------------------------------
UPDATE wa_conversations cv
  JOIN course co     ON co.course_id = cv.ref_id AND cv.ref_type = 'course'
  JOIN wa_programs p ON p.status = 1
                    AND COALESCE(p.assigned_to, '') <> ''
                    AND (co.course LIKE CONCAT('%', p.keywords, '%')
                         OR co.course LIKE CONCAT('%', p.name, '%'))
   SET cv.program_id       = p.id,
       cv.assigned_user_id = CAST(SUBSTRING_INDEX(p.assigned_to, ',', 1) AS UNSIGNED),
       cv.last_route_reason = 'program_backfill'
 WHERE (cv.assigned_user_id IS NULL OR cv.assigned_user_id = '')
   AND (cv.last_route_reason = 'await_onsite_location' OR cv.delivery_mode = 'onsite');

-- ---------------------------------------------------------------------------
-- 3b. MANUAL — for chats step 2 did not match (see the note on matching below)
--     Look them up, then set the programme explicitly.
-- ---------------------------------------------------------------------------
SELECT cv.id AS conv_id, c.wa_id, c.profile_name, co.course AS bound_course,
       cv.delivery_mode, cv.last_route_reason, cv.last_message_at
  FROM wa_conversations cv
  JOIN wa_contacts c ON c.id = cv.contact_id
  LEFT JOIN course co ON co.course_id = cv.ref_id AND cv.ref_type = 'course'
 WHERE (cv.assigned_user_id IS NULL OR cv.assigned_user_id = '')
   AND (cv.last_route_reason = 'await_onsite_location' OR cv.delivery_mode = 'onsite')
 ORDER BY cv.last_message_at DESC;

-- UPDATE wa_conversations cv
--   JOIN wa_programs p ON p.name = 'M&E Trainings'
--    SET cv.program_id        = p.id,
--        cv.assigned_user_id  = CAST(SUBSTRING_INDEX(p.assigned_to, ',', 1) AS UNSIGNED),
--        cv.last_route_reason = 'program_backfill'
--  WHERE cv.id IN (123, 456);          -- conv_id values from the SELECT above

-- ---------------------------------------------------------------------------
-- 4. VERIFY — who now owns what
-- ---------------------------------------------------------------------------
SELECT COALESCE(ru.fullname, '(still unassigned)') AS rep,
       COALESCE(p.name, '(no programme)')          AS programme,
       COUNT(*)                                    AS chats
  FROM wa_conversations cv
  LEFT JOIN registered_users ru ON ru.id = cv.assigned_user_id
  LEFT JOIN wa_programs p       ON p.id  = cv.program_id
 WHERE cv.delivery_mode = 'onsite'
    OR cv.last_route_reason IN ('await_onsite_location', 'program_backfill')
 GROUP BY rep, programme
 ORDER BY chats DESC;

-- ===========================================================================
-- ON MATCHING — why step 3b exists
--
-- The live router matches a programme WORD BY WORD: the keyword "Data Analysis
-- training" still matches a course called "Data Analysis Using SPSS", because
-- generic words ('training', 'course', 'programme') are ignored and the rest
-- must mostly match. Plain SQL LIKE cannot do that -- it needs the whole phrase
-- present -- so step 2/3a will match FEWER chats than the app will from now on.
-- Whatever 3a leaves behind, 3b lists so you can place it by hand. Going
-- forward no backfill is needed: new onsite enquiries are assigned as they
-- arrive.
--
-- ROLLBACK, if a run assigns something you did not intend:
--   UPDATE wa_conversations
--      SET assigned_user_id = NULL, program_id = NULL,
--          last_route_reason = 'await_onsite_location'
--    WHERE last_route_reason = 'program_backfill';
-- ===========================================================================

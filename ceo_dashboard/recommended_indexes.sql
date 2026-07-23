-- ============================================================================
-- recommended_indexes.sql
-- Indexes that make the reworked CEO-dashboard queries fast.
--
-- WHY: the pages were rewritten to use JOIN / NOT EXISTS / GROUP BY instead of
-- per-row (N+1) lookups. Those set-based queries are only fast when the columns
-- they filter and join on are indexed. Without these, MySQL/MariaDB falls back
-- to full-table scans and the pages stay slow.
--
-- SAFETY: creating an index is non-destructive (no data changes). On a large
-- table it briefly uses extra I/O and may lock writes for a few seconds while
-- it builds — run during a low-traffic window. MariaDB 10.11 supports
-- "IF NOT EXISTS", so re-running this file is safe.
--
-- BEFORE RUNNING: confirm the column names match your schema (they are taken
-- directly from the queries, so they exist, but double-check spelling/case):
--     SHOW INDEX FROM dpo_payment;
--     SHOW INDEX FROM ticket_congress;
-- ============================================================================

-- ---- dpo_payment ----------------------------------------------------------
-- Used by: income, payments_dashboard, all_transaction, export_income, fee_balances.
-- Filtered by status=2 almost everywhere; joined to course via `purpose`;
-- deduped by `token`; grouped by `app_id` in fee_balances.

-- The NOT EXISTS dedup does: WHERE dp.token = <ticket.confirmation> AND dp.status = 2
-- A composite (token, status) index makes each existence check an instant lookup.
CREATE INDEX IF NOT EXISTS idx_dpo_token_status   ON dpo_payment (token, status);

-- WHERE status = 2 ... JOIN course ON purpose = course_id
CREATE INDEX IF NOT EXISTS idx_dpo_status_purpose ON dpo_payment (status, purpose);

-- fee_balances: WHERE status = 2 GROUP BY app_id
CREATE INDEX IF NOT EXISTS idx_dpo_status_appid   ON dpo_payment (status, app_id);

-- Helps ORDER BY datee and any date-range filtering
CREATE INDEX IF NOT EXISTS idx_dpo_datee          ON dpo_payment (datee);


-- ---- ticket_congress ------------------------------------------------------
-- Used by: income, payments_dashboard, all_transaction, export_income, fee_balances.
-- Filtered by status=2; joined to Event via event_id; deduped by confirmation.
CREATE INDEX IF NOT EXISTS idx_tc_status_event    ON ticket_congress (status, event_id);
CREATE INDEX IF NOT EXISTS idx_tc_confirmation    ON ticket_congress (confirmation);
CREATE INDEX IF NOT EXISTS idx_tc_date_sent       ON ticket_congress (date_sent);


-- ---- custom_income --------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_custom_income_date ON custom_income (income_date);


-- ---- customer name/phone batch lookup (register / ticket_congress by email) --
-- The reworked pages resolve customer name+phone with:
--   SELECT ... FROM register        WHERE email IN (...)
--   SELECT ... FROM ticket_congress WHERE email IN (...)
-- Index `email` on both so those IN(...) lookups are fast.
CREATE INDEX IF NOT EXISTS idx_register_email     ON register (email);
CREATE INDEX IF NOT EXISTS idx_tc_email           ON ticket_congress (email);


-- ---- course / Event -------------------------------------------------------
-- course_id / event_id are join keys. They are usually the PRIMARY KEY already
-- (so already indexed). Only add these if SHOW INDEX shows they are NOT indexed.
-- CREATE INDEX IF NOT EXISTS idx_course_courseid ON course (course_id);
-- CREATE INDEX IF NOT EXISTS idx_event_eventid   ON Event (event_id);


-- ---- expenses -------------------------------------------------------------
-- expenses page groups/filters by category and expense_date.
CREATE INDEX IF NOT EXISTS idx_expenses_date      ON expenses (expense_date);
CREATE INDEX IF NOT EXISTS idx_expenses_category  ON expenses (category);


-- ---- fee_balances join chain (register / intake) --------------------------
CREATE INDEX IF NOT EXISTS idx_register_intake    ON register (intake_id);
CREATE INDEX IF NOT EXISTS idx_register_datee     ON register (datee);
CREATE INDEX IF NOT EXISTS idx_intake_course      ON intake (course_id);


-- ============================================================================
-- OPTIONAL FURTHER SPEEDUP (needs a small code change, not just an index):
--
-- The year filters use  WHERE YEAR(datee) = 2024  — a function on the column,
-- which prevents MySQL from using the datee index. If a specific year is slow,
-- change those to a range so the index is used:
--     WHERE datee >= '2024-01-01' AND datee < '2025-01-01'
-- (Not urgent: the default view is "all years", which has no such filter.)
-- ============================================================================

-- After running, verify the planner now uses an index (look for "Using index"
-- / a non-NULL key, not "ALL"):
--   EXPLAIN SELECT 1 FROM dpo_payment dp WHERE dp.token = 'x' AND dp.status = 2;

-- =====================================================================
-- WhatsApp inbox — indexes for the paginated conversation list.
--
-- Run ONCE against the ERP database:
--
--     mysql -u <admin_user> -p <erp_db> < db_schema/wa_inbox_indexes.sql
--
-- No database is named here on purpose, so it applies to any environment by
-- selecting the schema on the command line.
--
-- The inbox now fetches one page of conversations rather than all of them, in
-- two phases: pick the ids for the page, then fetch the display fields for those
-- ids only. Both phases lean on indexes that do not exist yet, and without them
-- the first phase still sorts the whole table on every poll — which is most of
-- what the change set out to avoid.
--
-- IF NOT EXISTS on an index requires MariaDB 10.1.4+ / MySQL 8.0.29+. On an
-- older server, drop the clause and ignore "Duplicate key name" on a re-run.
-- =====================================================================

-- Phase one orders by (last_message_at DESC, id DESC) and takes 51 rows. With
-- this index that is a backwards range scan that stops after 51; without it,
-- every conversation is read and sorted to produce the first page.
ALTER TABLE `wa_conversations`
    ADD INDEX IF NOT EXISTS `idx_wa_conv_recent` (`last_message_at`, `id`);

-- Phase two asks each row for its last message twice — the body and who sent it —
-- with ORDER BY m.id DESC LIMIT 1 per contact. The existing index is on
-- contact_id alone, so MySQL finds the contact's messages and then sorts them.
-- Adding id makes the newest one the first entry read, and nothing is sorted.
ALTER TABLE `wa_messages`
    ADD INDEX IF NOT EXISTS `idx_wa_msg_contact_id` (`contact_id`, `id`);

-- The unread count and the re-engagement test both filter a contact's messages by
-- direction and date. Ordered this way the index answers both without touching
-- the rows themselves.
ALTER TABLE `wa_messages`
    ADD INDEX IF NOT EXISTS `idx_wa_msg_contact_dir_created` (`contact_id`, `direction`, `created_at`);

-- Triage asks whether a contact ever wrote a message of real length. It is an
-- EXISTS over inbound messages for one contact, which the index above already
-- serves; this one narrows it further for the common case of a chat with many
-- outbound messages and few inbound ones.
ALTER TABLE `wa_messages`
    ADD INDEX IF NOT EXISTS `idx_wa_msg_contact_type_dir` (`contact_id`, `type`, `direction`);

-- The 24-hour window countdown and the Closing-soon tab both read
-- wa_contacts.last_inbound_at for every conversation in scope.
ALTER TABLE `wa_contacts`
    ADD INDEX IF NOT EXISTS `idx_wa_contacts_last_inbound` (`last_inbound_at`);

-- Verify afterwards:
--   SHOW INDEX FROM `wa_conversations`;
--   SHOW INDEX FROM `wa_messages`;
--   EXPLAIN SELECT cv.id FROM wa_conversations cv
--            JOIN wa_contacts c ON c.id = cv.contact_id
--        ORDER BY cv.last_message_at DESC, cv.id DESC LIMIT 51;
--   -- the wa_conversations row should show idx_wa_conv_recent and no "Using filesort".

-- Opt-out (STOP) support: exclude contacts who unsubscribed from every broadcast.
-- Run once in phpMyAdmin against the ERP database.
ALTER TABLE `wa_contacts`
    ADD COLUMN `opted_out`    TINYINT(1) NOT NULL DEFAULT 0 AFTER `opted_in`,
    ADD COLUMN `opted_out_at` DATETIME   DEFAULT NULL        AFTER `opted_out`;

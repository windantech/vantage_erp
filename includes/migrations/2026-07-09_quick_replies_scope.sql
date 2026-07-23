-- Scope a quick reply to a course OR an event (NULL = global, all chats).
-- Supersedes the earlier course-only version. Run once in phpMyAdmin.

ALTER TABLE `wa_quick_replies`
    ADD COLUMN `ref_type` ENUM('course','event') DEFAULT NULL AFTER `body`,
    ADD COLUMN `ref_id`   INT UNSIGNED           DEFAULT NULL AFTER `ref_type`;

-- ONLY if you already ran the earlier course-only migration (i.e. a `course_id`
-- column exists), also run these two lines to migrate it over and drop it:
-- UPDATE `wa_quick_replies` SET `ref_type` = 'course', `ref_id` = `course_id` WHERE `course_id` IS NOT NULL;
-- ALTER TABLE `wa_quick_replies` DROP COLUMN `course_id`;

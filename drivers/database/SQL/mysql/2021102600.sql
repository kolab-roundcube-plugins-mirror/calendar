-- changing table format and dropping foreign keys is needed for some versions of MySQL
ALTER TABLE `calendars` DROP FOREIGN KEY `fk_calendars_user_id`;
ALTER TABLE `events` DROP FOREIGN KEY`fk_events_calendar_id`;
ALTER TABLE `attachments` DROP FOREIGN KEY`fk_attachments_event_id`;
ALTER TABLE `itipinvitations` DROP FOREIGN KEY`fk_itipinvitations_user_id`;

ALTER TABLE `calendars` ROW_FORMAT=DYNAMIC;
ALTER TABLE `events` ROW_FORMAT=DYNAMIC;
ALTER TABLE `attachments` ROW_FORMAT=DYNAMIC;
ALTER TABLE `itipinvitations` ROW_FORMAT=DYNAMIC;

ALTER TABLE `calendars` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `events` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `attachments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `itipinvitations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `calendars` ADD CONSTRAINT `fk_calendars_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `events` ADD CONSTRAINT `fk_events_calendar_id` FOREIGN KEY (`calendar_id`)
    REFERENCES `calendars`(`calendar_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `attachments` ADD CONSTRAINT `fk_attachments_event_id` FOREIGN KEY (`event_id`)
    REFERENCES `events`(`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `itipinvitations` ADD CONSTRAINT `fk_itipinvitations_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- changing table format and dropping foreign keys is needed for some versions of MySQL
ALTER TABLE `kolab_alarms` DROP FOREIGN KEY `fk_kolab_alarms_user_id`;
ALTER TABLE `itipinvitations` DROP FOREIGN KEY`fk_itipinvitations_user_id`;

ALTER TABLE `kolab_alarms` ROW_FORMAT=DYNAMIC;
ALTER TABLE `itipinvitations` ROW_FORMAT=DYNAMIC;

ALTER TABLE `kolab_alarms` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `itipinvitations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `kolab_alarms` ADD CONSTRAINT `fk_kolab_alarms_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `itipinvitations` ADD CONSTRAINT `fk_itipinvitations_user_id` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

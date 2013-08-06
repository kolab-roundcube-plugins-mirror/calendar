-- MySQL database updates since version 0.9.1

ALTER TABLE `events` ADD `custom` TEXT NULL AFTER `attendees`;

-- One to rule them all

CREATE TABLE IF NOT EXISTS `#__blc_links_storage` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `link_id` int(10) unsigned NOT NULL,
  `log` longtext COLLATE 'utf8mb4_bin' NOT NULL,
  `data` longtext COLLATE 'utf8mb4_bin' NOT NULL,
   UNIQUE KEY `link_id` (`link_id`),
   CONSTRAINT `#__blc_links_storage_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `#__blc_links` (`id`) ON DELETE CASCADE
);

ALTER TABLE `#__blc_links` DROP `data` /** CAN FAIL **/;
ALTER TABLE `#__blc_links` DROP `log` /** CAN FAIL **/;
ALTER TABLE `#__blc_links` DROP `state` /** CAN FAIL **/;
ALTER TABLE `#__blc_links` DROP `timeout` /** CAN FAIL **/;
ALTER TABLE `#__blc_links` DROP `status_text` /** CAN FAIL **/;
ALTER TABLE `#__blc_links` DROP `warning`  /** CAN FAIL **/;

ALTER TABLE `#__blc_instances` DROP `data`  /** CAN FAIL **/;

ALTER TABLE `#__blc_links` ADD COLUMN `added` datetime NOT NULL DEFAULT current_timestamp() AFTER `final_url`  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD COLUMN `mime` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'not/checked' /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD COLUMN `parked` tinyint(1) NOT NULL DEFAULT 0 AFTER `being_checked`;

ALTER TABLE `#__blc_links` ADD INDEX `working` (`working`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `redirect_count` (`redirect_count`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `mime` (`mime`)   /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `being_checked` (`being_checked`) /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `parked` (`parked`)  /** CAN FAIL **/;

ALTER TABLE `#__blc_instances` ADD INDEX `parser` (`parser`) /** CAN FAIL **/;

ALTER TABLE `#__blc_synch` CHANGE `last_synch` `last_synch` datetime NOT NULL DEFAULT '1970-01-01 00:00:00'  /** CAN FAIL **/;

ALTER TABLE `#__blc_links` CHANGE `redirect_count` `redirect_count` int(11) NOT NULL DEFAULT '0'  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `broken` `broken` tinyint(1) NOT NULL DEFAULT '0'  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `working` `working` tinyint(1) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `being_checked` `being_checked` tinyint(1) NOT NULL DEFAULT '1'  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `mime` `mime` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'not/checked' AFTER `working`  /** CAN FAIL **/;

ALTER TABLE `#__blc_synch` CHANGE `container_plugin` `plugin_name` varchar(40) COLLATE 'utf8mb4_unicode_ci' NOT NULL COMMENT 'Plugin class name'  /** CAN FAIL **/;

ALTER TABLE `#__blc_instances` CHANGE `parser` `parser` varchar(40) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT ''  /** CAN FAIL **/;
ALTER TABLE `#__blc_instances` CHANGE `link_text` `link_text` varchar(512) COLLATE 'utf8mb4_unicode_ci' /** CAN FAIL **/;
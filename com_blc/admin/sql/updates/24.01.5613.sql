ALTER TABLE `#__blc_links` ADD INDEX `being_checked` (`being_checked`) /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__blc_links_storage` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `link_id` int(10) unsigned NOT NULL,
  `log` longtext COLLATE 'utf8mb4_bin' NOT NULL,
  `data` longtext COLLATE 'utf8mb4_bin' NOT NULL,
   UNIQUE KEY `link_id` (`link_id`),
   CONSTRAINT `#__blc_links_storage_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `#__blc_links` (`id`) ON DELETE CASCADE
);


INSERT IGNORE INTO  `#__blc_links_storage` (`link_id`,`log`,`data` )SELECT `id`,`log`,`data` FROM `#__blc_links`  /** CAN FAIL **/;
SET FOREIGN_KEY_CHECKS=0; --just in case a table is not dropped on previous uninstall
-- order is not important, disabled checks 
DROP TABLE IF EXISTS `#__blc_links`;
CREATE TABLE `#__blc_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(2048) NOT NULL DEFAULT '',
  `internal_url` varchar(2048) NOT NULL DEFAULT '',
  `final_url` varchar(2048) NOT NULL DEFAULT '',
  `added` datetime NOT NULL DEFAULT current_timestamp(),
  `last_check` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `first_failure` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `check_count` int(11) NOT NULL DEFAULT 0,
  `http_code` smallint(6) unsigned NOT NULL DEFAULT 0,
  `request_duration` float NOT NULL DEFAULT 0,
  `last_check_attempt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_success` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `redirect_count` int(11) NOT NULL DEFAULT 0,
  `broken` tinyint(1) NOT NULL DEFAULT 0,
  `being_checked` tinyint(1) NOT NULL DEFAULT 1,
  `parked` tinyint(1) NOT NULL DEFAULT 0,
  `working` tinyint(1) NOT NULL DEFAULT 0,
  `mime` varchar(255) NOT NULL DEFAULT 'not/checked',
  `md5sum` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `md5sum` (`md5sum`),
  KEY `http_code` (`http_code`),
  KEY `broken` (`broken`),
  KEY `last_check_attempt` (`last_check_attempt`),
  KEY `check_count` (`check_count`),
  KEY `working` (`working`),
  KEY `redirect_count` (`redirect_count`),
  KEY `mime` (`mime`),
  KEY `being_checked` (`being_checked`),
  KEY `parked` (`parked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__blc_synch`;
CREATE TABLE `#__blc_synch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(40) NOT NULL COMMENT 'Plugin class name',
  `container_id` int(20) unsigned NOT NULL,
  `synched` tinyint(2) unsigned NOT NULL DEFAULT 0,
  `last_synch` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugin_name_container_id` (`plugin_name`,`container_id`),
  KEY `synched` (`synched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__blc_instances`;
CREATE TABLE `#__blc_instances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `link_id` int(10) unsigned NOT NULL,
  `synch_id` int(10) unsigned NOT NULL,
  `field` varchar(255) NOT NULL DEFAULT '',
  `link_text` varchar(512) DEFAULT NULL,
  `parser` varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `synch_id` (`synch_id`),
  KEY `link_id` (`link_id`),
  KEY `parser` (`parser`),
  CONSTRAINT `#__blc_instances_ibfk_1` FOREIGN KEY (`synch_id`) REFERENCES `#__blc_synch` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `#__blc_instances_ibfk_2` FOREIGN KEY (`link_id`) REFERENCES `#__blc_links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__blc_links_storage`;
CREATE TABLE `#__blc_links_storage` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(10) unsigned NOT NULL,
  `log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `query_id` int(11) GENERATED ALWAYS AS (cast(json_value(`data`,'$.query.id') as unsigned)) STORED,
  `query_option` varchar(64) GENERATED ALWAYS AS (cast(json_value(`data`,'$.query.option') as char charset utf8mb4)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link_id` (`link_id`),
  KEY `query_id` (`query_id`),
  KEY `query_option` (`query_option`),
  CONSTRAINT `#__blc_links_storage_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `#__blc_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS=1;
 
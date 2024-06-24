ALTER TABLE `#__blc_links` ADD INDEX `working` (`working`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `timeout` (`timeout`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `warning` (`warning`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` ADD INDEX `redirect_count` (`redirect_count`)  /** CAN FAIL **/;
ALTER TABLE `#__blc_links`  ADD `added` datetime NOT NULL DEFAULT current_timestamp() AFTER `final_url`  /** CAN FAIL **/;
ALTER TABLE `#__blc_links`  ADD  `mime` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'text/plain' /** CAN FAIL **/;
ALTER TABLE `#__blc_synch` CHANGE `last_synch` `last_synch` datetime NOT NULL DEFAULT '1970-01-01 00:00:00'  /** CAN FAIL **/;
ALTER TABLE `#__blc_instances` CHANGE `parser` `parser` varchar(40) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT ''  /** CAN FAIL **/;
ALTER TABLE `#__blc_links`  CHANGE `mime` `mime` varchar(255) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT 'text/plain' AFTER `log`  /** CAN FAIL **/;
ALTER TABLE `#__blc_links`  CHANGE `urlid` `urlid` binary(16) NOT NULL AFTER `mime`  /** CAN FAIL **/;



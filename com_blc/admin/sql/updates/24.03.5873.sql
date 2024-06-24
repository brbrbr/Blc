UPDATE `#__blc_instances` SET `link_text` = SUBSTR(`link_text`, 0, 512)  /** CAN FAIL **/;
ALTER TABLE `#__blc_instances` CHANGE `link_text` `link_text` varchar(512) COLLATE 'utf8mb4_unicode_ci' /** CAN FAIL **/;
ALTER TABLE `#__blc_instances` DROP `data`  /** CAN FAIL **/;
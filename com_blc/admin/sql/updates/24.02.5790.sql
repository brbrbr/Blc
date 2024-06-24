ALTER TABLE `#__blc_links` CHANGE `redirect_count` `redirect_count` int(11) NOT NULL DEFAULT '0'  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `broken` `broken` tinyint(4) NOT NULL DEFAULT '0'  /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `warning` `warning` tinyint(4) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `being_checked` `being_checked` tinyint(4) NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__blc_links` CHANGE `working` `working` tinyint(4) NOT NULL DEFAULT '0' /** CAN FAIL **/;
UPDATE `#__blc_links` SET   `broken` = 0 WHERE `http_code` = 0  /** CAN FAIL **/;
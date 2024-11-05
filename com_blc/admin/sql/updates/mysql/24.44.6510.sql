ALTER TABLE `#__blc_links_storage` DROP  COLUMN `query_option` /** CAN FAIL **/;
ALTER TABLE `#__blc_links_storage` DROP  COLUMN `query_id` /** CAN FAIL **/;

ALTER TABLE `#__blc_links_storage` ADD COLUMN `queryOption` varchar(64) COLLATE 'utf8mb4_unicode_ci' NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__blc_links_storage` ADD COLUMN `queryId` int(11)   NOT NULL DEFAULT 0 /** CAN FAIL **/;

UPDATE `#__blc_links_storage` SET `queryId` = COALESCE(CAST(JSON_VALUE(`data`,'$.query.id') AS UNSIGNED),0) ,`queryOption` = COALESCE(CAST(JSON_VALUE(`data`,'$.query.option') AS char),'');

ALTER TABLE `#__blc_links_storage` ADD INDEX `queryId_queryOption` (`queryId`, `queryOption`) /** CAN FAIL **/;

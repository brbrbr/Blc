ALTER TABLE `#__blc_links_storage` DROP `query_id` /** CAN FAIL **/;
ALTER TABLE `#__blc_links_storage` DROP `query_option` /** CAN FAIL **/;

ALTER TABLE  `#__blc_links_storage` ADD COLUMN `query_id` int AS (CAST(JSON_VALUE(`data`,'$.query.id') as UNSIGNED)) STORED;
CREATE INDEX `query_id` ON `#__blc_links_storage`(`query_id`);

ALTER TABLE  `#__blc_links_storage` ADD COLUMN `query_option` varchar(64) AS (CAST(JSON_VALUE(`data`,'$.query.option') as CHAR)) STORED;
CREATE INDEX `query_content` ON `#__blc_links_storage`(`query_option`);




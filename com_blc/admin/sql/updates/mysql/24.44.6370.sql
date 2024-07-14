ALTER TABLE `#__blc_links` ADD `md5sum` char(32) NOT NULL DEFAULT '';
UPDATE  `#__blc_links` set `md5sum` = HEX(`urlid`) ;
ALTER TABLE `#__blc_links` ADD UNIQUE `md5sum` (`md5sum`);
ALTER TABLE `#__blc_links `DROP `urlid`;
-- ALTER TABLE  `#__blc_links_storage` ADD COLUMN `query_id` int AS (JSON_VALUE(`data`,'$.query.id')) STORED;
-- CREATE INDEX `query_id` ON `#__blc_links_storage`(`query_id`);

--ALTER TABLE  "__blc_links_storage" ADD "query_id" int GENERATED ALWAYS AS (("data"::json #>> '{query,id}')::int) STORED;

ALTER TABLE  "__blc_links_storage" ADD "query_option"  character varying(64) GENERATED ALWAYS AS (("data"::json #>> '{query,option}')) STORED;
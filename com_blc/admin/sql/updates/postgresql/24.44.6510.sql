
ALTER TABLE  "#__blc_links_storage" DROP "query_option" /** CAN FAIL **/;
ALTER TABLE  "#__blc_links_storage" DROP "query_id" /** CAN FAIL **/;

ALTER TABLE  "#__blc_links_storage" ADD "queryId" int;
ALTER TABLE  "#__blc_links_storage" ADD "queryOption"  character varying(64) GENERATED ALWAYS AS (("data"::json #>> '{query,option}')) STORED;
CREATE INDEX "#__blc_links_storage_queryId" ON "#__blc_links_storage" ("queryId");
CREATE INDEX "#__blc_links_storage_queryOption" ON "#__blc_links_storage" ("queryOption");
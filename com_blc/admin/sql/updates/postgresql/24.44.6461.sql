
ALTER TABLE  "#__blc_links_storage" DROP "query_option" /** CAN FAIL **/;
ALTER TABLE  "#__blc_links_storage" DROP "query_id" /** CAN FAIL **/;

ALTER TABLE  "#__blc_links_storage" ADD "query_id" int GENERATED ALWAYS AS (("data"::json #>> '{query,id}')::int) STORED;
ALTER TABLE  "#__blc_links_storage" ADD "query_option"  character varying(64) GENERATED ALWAYS AS (("data"::json #>> '{query,option}')) STORED;
CREATE INDEX "#__blc_links_storage_query_id" ON "#__blc_links_storage" ("query_id");
CREATE INDEX "#__blc_links_storage_query_option" ON "#__blc_links_storage" ("query_option");
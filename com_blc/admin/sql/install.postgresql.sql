DROP TABLE IF EXISTS "#__blc_links_storage";
DROP SEQUENCE IF EXISTS "#__blc_links_storage_id_seq";

DROP TABLE IF EXISTS "#__blc_instances";
DROP SEQUENCE IF EXISTS "#__blc_instances_id_seq";

DROP TABLE IF EXISTS "#__blc_links";
DROP SEQUENCE IF EXISTS "#__blc_links_id_seq";

DROP TABLE IF EXISTS "#__blc_synch";
DROP SEQUENCE IF EXISTS "#__blc_synch_id_seq";

CREATE TABLE "#__blc_links" (
    "id" serial NOT NULL,
    "url" character varying(2048) DEFAULT '' NOT NULL,
    "internal_url" character varying(2048) DEFAULT '' NOT NULL,
    "final_url" character varying(2048) DEFAULT '' NOT NULL,
    "added" timestamp without time zone DEFAULT now() NOT NULL,
    "last_check" timestamp without time zone NOT NULL,
    "first_failure" timestamp without time zone NOT NULL,
    "check_count" integer DEFAULT '0' NOT NULL,
    "http_code" smallint DEFAULT '0' NOT NULL,
    "request_duration" double precision DEFAULT '0' NOT NULL,
    "last_check_attempt" timestamp without time zone NOT NULL,
    "last_success" timestamp without time zone NOT NULL,
    "redirect_count" integer DEFAULT '0' NOT NULL,
    "broken" smallint DEFAULT '0' NOT NULL,
    "being_checked" smallint DEFAULT '1' NOT NULL,
    "parked" smallint DEFAULT '0' NOT NULL,
    "working" smallint DEFAULT '0' NOT NULL,
    "mime" character varying(255) DEFAULT 'not/checked' NOT NULL,
    "md5sum" varchar(32) DEFAULT NULL,
    CONSTRAINT "#__blc_links_pkey" PRIMARY KEY ("id"),
    CONSTRAINT "#__blc_links_md5sum" UNIQUE ("md5sum")
);

CREATE INDEX "#__blc_links_http_code" ON "#__blc_links"  ("http_code");
CREATE INDEX "#__blc_links_broken" ON "#__blc_links"  ("broken");
CREATE INDEX "#__blc_links_last_check_attempt" ON "#__blc_links"  ("last_check_attempt");
CREATE INDEX "#__blc_links_check_count" ON "#__blc_links"  ("check_count");
CREATE INDEX "#__blc_links_working" ON "#__blc_links"  ("working");
CREATE INDEX "#__blc_links_redirect_count" ON "#__blc_links"  ("redirect_count");
CREATE INDEX "#__blc_links_mime" ON "#__blc_links"  ("mime");
CREATE INDEX "#__blc_links_being_checked" ON "#__blc_links"  ("being_checked");
CREATE INDEX "#__blc_links_parked" ON "#__blc_links"  ("parked");

CREATE TABLE "#__blc_synch" (
   "id" serial NOT NULL,
  "plugin_name" character varying(40) NOT NULL ,
  "container_id" bigint NOT NULL,
  "synched" smallint NOT NULL DEFAULT 0,
  "last_synch"  timestamp without time zone NOT NULL DEFAULT '1970-01-01 00:00:00',
  "data" text NOT NULL ,
  PRIMARY KEY ("id"),
  CONSTRAINT  "#__plugin_name_container_id" UNIQUE ("plugin_name","container_id")
);

CREATE INDEX "#__blc_synch_synched" ON "#__blc_synch"  ("synched");

ALTER TABLE "#__blc_synch" COMMENT ON COLUMN "#__blc_synch"."container_id" IS 'postgresql does not have unsigned. We use to store an unsigned crc32 which does not fit in 32 bir value.';

CREATE TABLE "#__blc_instances" (
  "id" serial NOT NULL,
  "link_id" int  NOT NULL,
  "synch_id" int  NOT NULL,
  "field" character varying(255) NOT NULL DEFAULT '',
  "link_text" character varying(512) NOT NULL DEFAULT '',
  "parser" character varying(40) NOT NULL DEFAULT '',
  PRIMARY KEY ("id")
 
);

CREATE INDEX "#__blc_instances_link_id" ON "#__blc_instances" ("link_id");
CREATE INDEX "#__blc_instances_parser" ON "#__blc_instances" ("parser");
CREATE INDEX "#__blc_instances_synch_id" ON "#__blc_instances" ("synch_id");

ALTER TABLE ONLY "#__blc_instances" ADD CONSTRAINT "#__blc_instances_ibfk_1" FOREIGN KEY (synch_id) REFERENCES "#__blc_synch"(id) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE;
ALTER TABLE ONLY "#__blc_instances" ADD CONSTRAINT "#__blc_instances_ibfk_2" FOREIGN KEY (link_id) REFERENCES "#__blc_links"(id) ON DELETE CASCADE ON UPDATE CASCADE  NOT DEFERRABLE;

CREATE TABLE "#__blc_links_storage" (
  "id" serial NOT NULL,
  "link_id" int  NOT NULL,
  "log" text  NOT NULL,
  "data" text  NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__blc_links_storage_link_id" UNIQUE ("link_id")
);

ALTER TABLE ONLY "#__blc_links_storage" ADD CONSTRAINT "#__blc_links_storage_ibfk_1" FOREIGN KEY (link_id) REFERENCES "#__blc_links"(id) ON DELETE CASCADE ON UPDATE CASCADE  NOT DEFERRABLE;
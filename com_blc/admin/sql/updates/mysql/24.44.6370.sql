ALTER TABLE `#__blc_links` ADD `md5sum` char(32)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '';
UPDATE  `#__blc_links` set `md5sum` = HEX(`urlid`) ;
ALTER TABLE `#__blc_links` ADD UNIQUE `md5sum` (`md5sum`);
ALTER TABLE `#__blc_links`DROP `urlid`;
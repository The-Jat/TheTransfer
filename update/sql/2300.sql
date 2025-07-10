UPDATE `settings` SET `value` = '{\"version\":\"23.0.0\", \"code\":\"2300\"}' WHERE `key` = 'product_info';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

update files set `file_uuid` =  '';

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

alter table files modify file_uuid binary(16) null;

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --

update files set `file_uuid` =  UNHEX(REPLACE(UUID(), '-', ''));

-- SEPARATOR --
-- NULLED BY LOSTKOREAN - BABIA.TO --
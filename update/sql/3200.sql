UPDATE `settings` SET `value` = '{\"version\":\"29.0.0\", \"code\":\"2900\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
alter table pages add plans_ids text null after pages_category_id;
-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"30.0.0\", \"code\":\"3000\"}' WHERE `key` = 'product_info';
-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"31.0.0\", \"code\":\"3100\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
UPDATE users SET email = LOWER(email);


-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('klarna', '{"is_enabled":1,"mode":"https:\/\/api.playground.klarna.com\/","username":"","password":"","currencies":["USD"]}');

-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('paddle_billing', '{"is_enabled":1,"mode":"sandbox","api_key":"","secret_key":"","client_side_token":"","currencies":["USD"]}');

-- SEPARATOR --

alter table plans add additional_settings text null after settings;

-- SEPARATOR --UPDATE `settings` SET `value` = '{\"version\":\"32.0.0\", \"code\":\"3200\"}' WHERE `key` = 'product_info';
-- SEPARATOR --
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('plisio', '{\"is_enabled\":false,\"secret_key\":\"\",\"accepted_cryptocurrencies\":[\"DOGE\",\"SOL\",\"ETH\",\"BTC\"],\"default_cryptocurrency\":\"SOL\",\"currencies\":[\"USD\"]}');
-- SEPARATOR --

INSERT INTO `settings` (`key`, `value`) VALUES ('revolut', '{\"is_enabled\":false,\"mode\":\"sandbox\",\"secret_key\":\"\",\"webhook_id\":\"\",\"currencies\":[\"USD\"]}');
-- SEPARATOR --

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('plisio_whitelabel', '{\"is_enabled\":false,\"secret_key\":\"\",\"accepted_cryptocurrencies\":[\"DOGE\",\"SOL\",\"ETH\",\"BTC\"],\"default_cryptocurrency\":\"SOL\",\"currencies\":[\"USD\"]}');

-- SEPARATOR --

create index `status` on users (status);

-- SEPARATOR --

create index users_logs_datetime_index on users_logs (datetime);

-- SEPARATOR --

create index internal_notifications_datetime_index on internal_notifications (datetime);

-- SEPARATOR --

alter table files add offload_id varchar(64) null after file_uuid;

-- SEPARATOR --

create index files_datetime_index on files (datetime);
-- SEPARATOR --
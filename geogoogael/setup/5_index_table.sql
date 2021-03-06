ALTER TABLE `geogoogael`.`ip2location_records`
    ADD UNIQUE INDEX `IP_RANGE_START_IP_RANGE_END` (`IP_RANGE_START`, `IP_RANGE_END`),
    ADD INDEX `IP_RANGE_END` (`IP_RANGE_END`),
    ADD INDEX `COUNTRY_NAME` (`COUNTRY_NAME`),
    ADD INDEX `REGION_NAME` (`REGION_NAME`),
    ADD INDEX `CITY_NAME` (`CITY_NAME`),
    ADD INDEX `AREA_CODE` (`AREA_CODE`),
    ADD INDEX `ISO-639-1` (`ISO-639-1`),
    ADD INDEX `TIMEZONE` (`TIMEZONE`);
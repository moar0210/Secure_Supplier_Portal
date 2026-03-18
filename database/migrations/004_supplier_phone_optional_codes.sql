START TRANSACTION;

ALTER TABLE `phones_entities`
  MODIFY `country_prefix` int(4) DEFAULT NULL,
  MODIFY `area_code` int(2) DEFAULT NULL,
  MODIFY `phone_as_string` varchar(50)
    GENERATED ALWAYS AS (
      trim(
        concat(
          if(`country_prefix` is null or `country_prefix` = 0, '', concat('+', `country_prefix`, ' ')),
          if(`area_code` is null or `area_code` = 0, '', concat('(', `area_code`, ') ')),
          `phone_number`
        )
      )
    ) STORED;

UPDATE `phones_entities`
SET `country_prefix` = NULL
WHERE `entity_name` = 'SUPPLIER'
  AND `country_prefix` = 0;

UPDATE `phones_entities`
SET `area_code` = NULL
WHERE `entity_name` = 'SUPPLIER'
  AND `area_code` = 0;

COMMIT;

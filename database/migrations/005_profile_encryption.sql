START TRANSACTION;

ALTER TABLE `suppliers`
  MODIFY `email` varchar(255) NOT NULL DEFAULT '',
  MODIFY `unique_id` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `persons`
  MODIFY `first_name` varchar(700) NOT NULL,
  MODIFY `last_name` varchar(700) NOT NULL,
  MODIFY `full_name` varchar(700) NOT NULL,
  MODIFY `email_address` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `addresses`
  MODIFY `city` varchar(700) NOT NULL DEFAULT '',
  MODIFY `description` varchar(700) NOT NULL DEFAULT '',
  MODIFY `complement` varchar(700) NOT NULL DEFAULT '',
  MODIFY `region` varchar(700) NOT NULL DEFAULT '',
  MODIFY `postal_code` varchar(700) NOT NULL DEFAULT '',
  MODIFY `fc_address_description` varchar(4000)
    GENERATED ALWAYS AS (
      concat(`description`,' ',`complement`,' ',`postal_code`,' ',`city`,' - ',`region`,`district`,' - ',`country_code_ISO2`)
    ) VIRTUAL;

ALTER TABLE `phones_entities`
  DROP INDEX `Entity_PhoneType`,
  MODIFY `country_prefix` varchar(120) DEFAULT NULL,
  MODIFY `area_code` varchar(120) DEFAULT NULL,
  MODIFY `phone_number` varchar(120) DEFAULT '',
  MODIFY `phone_as_string` varchar(500)
    GENERATED ALWAYS AS (
      trim(
        concat(
          if(`country_prefix` is null or `country_prefix` = '', '', concat('+', `country_prefix`, ' ')),
          if(`area_code` is null or `area_code` = '', '', concat('(', `area_code`, ') ')),
          `phone_number`
        )
      )
    ) STORED;

COMMIT;

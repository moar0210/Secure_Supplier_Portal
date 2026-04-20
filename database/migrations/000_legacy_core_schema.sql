START TRANSACTION;

CREATE TABLE IF NOT EXISTS `addresses` (
  `id_address` int(11) NOT NULL AUTO_INCREMENT,
  `uuid_address` varchar(36) NOT NULL,
  `LanguageId` int(11) DEFAULT NULL,
  `country_code_ISO2` char(2) NOT NULL DEFAULT '',
  `ID_TimeZone` varchar(50) DEFAULT NULL,
  `city` varchar(100) NOT NULL DEFAULT '',
  `care_of` varchar(100) DEFAULT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `complement` varchar(255) NOT NULL DEFAULT '',
  `district` varchar(100) NOT NULL DEFAULT '',
  `region` varchar(100) NOT NULL DEFAULT '',
  `postal_code` varchar(30) NOT NULL DEFAULT '',
  `access_information` varchar(255) DEFAULT NULL,
  `website` varchar(100) NOT NULL DEFAULT '',
  `geo_latitude` decimal(10,7) DEFAULT NULL,
  `geo_longitude` decimal(10,7) DEFAULT NULL,
  `uuid_address_connected` varchar(36) DEFAULT NULL,
  `uuid_reporter` varchar(36) DEFAULT NULL,
  `source_info` varchar(50) NOT NULL DEFAULT '',
  `digital_document` varchar(255) DEFAULT NULL,
  `uuid_recorder` varchar(36) DEFAULT NULL,
  `IDC_role_recorder` varchar(50) DEFAULT NULL,
  `time_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `additional_notes` text DEFAULT NULL,
  `fc_address_description` varchar(1000)
    GENERATED ALWAYS AS (
      concat(`description`,' ',`complement`,' ',`postal_code`,' ',`city`,' - ',`region`,`district`,' - ',`country_code_ISO2`)
    ) VIRTUAL,
  PRIMARY KEY (`id_address`),
  UNIQUE KEY `uq_addresses_uuid` (`uuid_address`),
  KEY `idx_addresses_source_info` (`source_info`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `persons` (
  `id_person` int(11) NOT NULL AUTO_INCREMENT,
  `uuid_entity` varchar(36) NOT NULL,
  `abbreviation` varchar(20) NOT NULL DEFAULT '',
  `username_App` varchar(100) DEFAULT NULL,
  `country_code_ISO2` char(2) NOT NULL DEFAULT '',
  `contact_preferred_time_of_the_day` varchar(50) DEFAULT NULL,
  `uuid_address_main` varchar(36) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `full_name` varchar(200) NOT NULL DEFAULT '',
  `Title` varchar(50) DEFAULT NULL,
  `Suffix` varchar(50) DEFAULT NULL,
  `country_code_ISO2_birth` char(2) DEFAULT NULL,
  `multiple_birth` tinyint(1) NOT NULL DEFAULT 0,
  `date_birth` date DEFAULT NULL,
  `time_birth` time DEFAULT NULL,
  `country_code_ISO2_death` char(2) DEFAULT NULL,
  `Date_death` date DEFAULT NULL,
  `Time_death` time DEFAULT NULL,
  `gender` varchar(20) NOT NULL DEFAULT 'NO_INFO',
  `occupation` varchar(100) DEFAULT NULL,
  `type_meal` varchar(50) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `SMS_Reminders` tinyint(1) NOT NULL DEFAULT 0,
  `EMAIL_Reminders` tinyint(1) NOT NULL DEFAULT 0,
  `system_language_1st` varchar(10) DEFAULT NULL,
  `system_language_2nd` varchar(10) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `blood_type_variant` varchar(10) DEFAULT NULL,
  `main_meal` varchar(50) DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `email_address` varchar(255) NOT NULL DEFAULT '',
  `email_address_additional` varchar(255) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `How_did_you_find_us` varchar(100) DEFAULT NULL,
  `How_did_you_find_us_complement` varchar(255) DEFAULT NULL,
  `is_organ_donor` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_special_needs` tinyint(1) NOT NULL DEFAULT 0,
  `geo_location_tracking_approved` tinyint(1) NOT NULL DEFAULT 0,
  `visible_identification_marks` varchar(255) DEFAULT NULL,
  `eye_color` varchar(50) DEFAULT NULL,
  `idc_ethnic_group` varchar(50) DEFAULT NULL,
  `IDC_Religion` varchar(50) DEFAULT NULL,
  `IDC_Education` varchar(50) DEFAULT NULL,
  `social_economic_class` varchar(50) DEFAULT NULL,
  `authorize_share_of_data_for_research` tinyint(1) NOT NULL DEFAULT 0,
  `suggested_days_between_appointments` int(11) DEFAULT NULL,
  `uuid_reporter` varchar(36) DEFAULT NULL,
  `source_info` varchar(50) NOT NULL DEFAULT '',
  `digital_document` varchar(255) DEFAULT NULL,
  `uuid_recorder` varchar(36) DEFAULT NULL,
  `IDC_role_recorder` varchar(50) DEFAULT NULL,
  `time_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_person`),
  UNIQUE KEY `uq_persons_uuid` (`uuid_entity`),
  KEY `idx_persons_address` (`uuid_address_main`),
  KEY `idx_persons_source_info` (`source_info`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id_supplier` int(11) NOT NULL AUTO_INCREMENT,
  `uuid_supplier` varchar(36) NOT NULL,
  `supplier_type` varchar(30) NOT NULL DEFAULT 'SUPPLIER',
  `country_code_ISO2` char(2) NOT NULL DEFAULT '',
  `uuid_address_main` varchar(36) DEFAULT NULL,
  `uuid_person_contact` varchar(36) DEFAULT NULL,
  `unique_id` varchar(100) NOT NULL DEFAULT '',
  `short_name` varchar(100) NOT NULL DEFAULT '',
  `supplier_name` varchar(100) NOT NULL DEFAULT '',
  `homepage` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `is_inactive` tinyint(1) NOT NULL DEFAULT 1,
  `source_info` varchar(50) NOT NULL DEFAULT '',
  `time_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_supplier`),
  UNIQUE KEY `uq_suppliers_uuid` (`uuid_supplier`),
  KEY `idx_suppliers_active_name` (`is_inactive`,`supplier_name`),
  KEY `idx_suppliers_contact` (`uuid_person_contact`),
  KEY `idx_suppliers_address` (`uuid_address_main`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `addresses_entities` (
  `id_address_entity` int(11) NOT NULL AUTO_INCREMENT,
  `uuid_address` varchar(36) NOT NULL,
  `entity_name` varchar(50) NOT NULL,
  `uuid_entity` varchar(36) NOT NULL,
  `address_type` varchar(30) NOT NULL DEFAULT 'MAIN',
  `time_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_info` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_address_entity`),
  UNIQUE KEY `uq_addresses_entities_link` (`uuid_address`,`entity_name`,`uuid_entity`,`address_type`),
  KEY `idx_addresses_entities_entity` (`entity_name`,`uuid_entity`),
  KEY `idx_addresses_entities_address` (`uuid_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `phones_entities` (
  `id_phone_entity` int(11) NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(50) NOT NULL,
  `uuid_entity` varchar(36) NOT NULL,
  `country_prefix` int(4) DEFAULT 0,
  `area_code` int(2) DEFAULT 0,
  `phone_number` varchar(50) NOT NULL DEFAULT '',
  `phone_type` varchar(30) NOT NULL DEFAULT 'MAIN',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `time_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_info` varchar(50) NOT NULL DEFAULT '',
  `phone_as_string` varchar(50)
    GENERATED ALWAYS AS (
      trim(
        concat(
          if(`country_prefix` is null or `country_prefix` = 0, '', concat('+', `country_prefix`, ' ')),
          if(`area_code` is null or `area_code` = 0, '', concat('(', `area_code`, ') ')),
          `phone_number`
        )
      )
    ) STORED,
  PRIMARY KEY (`id_phone_entity`),
  KEY `Entity_PhoneType` (`entity_name`,`uuid_entity`,`phone_type`),
  KEY `idx_phones_entities_entity` (`entity_name`,`uuid_entity`,`is_main`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

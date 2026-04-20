START TRANSACTION;

ALTER TABLE `phones_entities`
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

CREATE TABLE IF NOT EXISTS `portal_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` enum('ACTIVITY','ERROR','AUTH') NOT NULL,
  `event` varchar(255) NOT NULL,
  `context_json` longtext DEFAULT NULL,
  `page` varchar(100) DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_portal_activity_logs_level_created` (`level`,`created_at`),
  KEY `idx_portal_activity_logs_user_created` (`user_id`,`created_at`),
  KEY `idx_portal_activity_logs_supplier_created` (`supplier_id`,`created_at`),
  CONSTRAINT `fk_portal_activity_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_portal_activity_logs_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_daily_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ad_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `stat_date` date NOT NULL,
  `impressions` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ad_daily_stats_ad_date` (`ad_id`,`stat_date`),
  KEY `idx_ad_daily_stats_supplier_date` (`supplier_id`,`stat_date`),
  KEY `idx_ad_daily_stats_date` (`stat_date`),
  CONSTRAINT `fk_ad_daily_stats_ad`
    FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_ad_daily_stats_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `ad_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ad_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `price_text` varchar(200) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('DRAFT','PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ads_supplier_created` (`supplier_id`,`created_at`),
  KEY `idx_ads_supplier_status_updated` (`supplier_id`,`status`,`updated_at`),
  KEY `idx_ads_status_updated` (`status`,`updated_at`),
  KEY `idx_ads_category` (`category_id`),
  CONSTRAINT `fk_ads_category` FOREIGN KEY (`category_id`) REFERENCES `ad_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ads_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ad_id` int(11) NOT NULL,
  `old_status` enum('DRAFT','PENDING','APPROVED','REJECTED') DEFAULT NULL,
  `new_status` enum('DRAFT','PENDING','APPROVED','REJECTED') NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `changed_by_user_id` int(10) unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hist_ad_changed` (`ad_id`,`changed_at`),
  KEY `idx_hist_changed_by` (`changed_by_user_id`,`changed_at`),
  CONSTRAINT `fk_ad_status_history_ad` FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ad_status_history_user` FOREIGN KEY (`changed_by_user_id`) REFERENCES `portal_users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Optional seed categories (safe, won’t duplicate) */
INSERT IGNORE INTO `ad_categories` (`name`) VALUES
  ('General'),
  ('Products'),
  ('Services'),
  ('Logistics');

COMMIT;
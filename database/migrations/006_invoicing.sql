START TRANSACTION;

CREATE TABLE IF NOT EXISTS `pricing_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price_per_ad` decimal(12,2) NOT NULL,
  `currency_code` char(3) NOT NULL DEFAULT 'SEK',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 25.00,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pricing_rules_active_dates` (`is_active`,`effective_from`,`effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pricing_rules`
  (`name`, `description`, `price_per_ad`, `currency_code`, `vat_rate`, `effective_from`, `effective_to`, `is_active`)
SELECT
  'Default monthly price per ad',
  'Seeded default used when no other pricing rule overlaps the billing month.',
  500.00,
  'SEK',
  25.00,
  '2026-01-01',
  NULL,
  1
WHERE NOT EXISTS (
  SELECT 1
  FROM `pricing_rules`
  WHERE `name` = 'Default monthly price per ad'
);

CREATE TABLE IF NOT EXISTS `invoice_number_sequences` (
  `period_key` char(6) NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `pricing_rule_id` int(10) unsigned DEFAULT NULL,
  `billing_year` smallint(5) unsigned NOT NULL,
  `billing_month` tinyint(3) unsigned NOT NULL,
  `billing_period_start` date NOT NULL,
  `billing_period_end` date NOT NULL,
  `sequence_no` int(10) unsigned NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `status` enum('DRAFT','SENT','PAID','OVERDUE') NOT NULL DEFAULT 'DRAFT',
  `currency_code` char(3) NOT NULL DEFAULT 'SEK',
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 25.00,
  `subtotal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_name_snapshot` varchar(700) NOT NULL DEFAULT '',
  `supplier_short_name_snapshot` varchar(700) NOT NULL DEFAULT '',
  `contact_person_snapshot` varchar(700) NOT NULL DEFAULT '',
  `supplier_email_snapshot` varchar(700) NOT NULL DEFAULT '',
  `supplier_vat_number_snapshot` varchar(700) NOT NULL DEFAULT '',
  `homepage_snapshot` varchar(700) NOT NULL DEFAULT '',
  `address_line_1_snapshot` varchar(700) NOT NULL DEFAULT '',
  `address_line_2_snapshot` varchar(700) NOT NULL DEFAULT '',
  `city_snapshot` varchar(700) NOT NULL DEFAULT '',
  `region_snapshot` varchar(700) NOT NULL DEFAULT '',
  `postal_code_snapshot` varchar(700) NOT NULL DEFAULT '',
  `country_code_snapshot` char(2) NOT NULL DEFAULT '',
  `generated_by_user_id` int(10) unsigned NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `overdue_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_supplier_month` (`supplier_id`,`billing_year`,`billing_month`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  UNIQUE KEY `uq_invoices_sequence` (`billing_year`,`billing_month`,`sequence_no`),
  KEY `idx_invoices_status_due` (`status`,`due_date`),
  KEY `idx_invoices_supplier_status` (`supplier_id`,`status`,`created_at`),
  CONSTRAINT `fk_invoices_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_pricing_rule`
    FOREIGN KEY (`pricing_rule_id`) REFERENCES `pricing_rules` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_generated_by`
    FOREIGN KEY (`generated_by_user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_lines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `ad_id` int(11) NOT NULL,
  `pricing_rule_id` int(10) unsigned DEFAULT NULL,
  `ad_title` varchar(200) NOT NULL,
  `description` varchar(255) NOT NULL,
  `line_period_start` date NOT NULL,
  `line_period_end` date NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL,
  `net_amount` decimal(12,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL,
  `vat_amount` decimal(12,2) NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_lines_ad` (`invoice_id`,`ad_id`),
  KEY `idx_invoice_lines_ad` (`ad_id`),
  CONSTRAINT `fk_invoice_lines_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_lines_ad`
    FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_invoice_lines_pricing_rule`
    FOREIGN KEY (`pricing_rule_id`) REFERENCES `pricing_rules` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `recorded_by_user_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_payments_invoice` (`invoice_id`),
  KEY `idx_invoice_payments_date` (`payment_date`),
  CONSTRAINT `fk_invoice_payments_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_payments_user`
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `old_status` enum('DRAFT','SENT','PAID','OVERDUE') DEFAULT NULL,
  `new_status` enum('DRAFT','SENT','PAID','OVERDUE') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `changed_by_user_id` int(10) unsigned NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice_status_history_invoice` (`invoice_id`,`changed_at`),
  CONSTRAINT `fk_invoice_status_history_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_status_history_user`
    FOREIGN KEY (`changed_by_user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_activation_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ad_id` int(11) NOT NULL,
  `old_is_active` tinyint(1) NOT NULL,
  `new_is_active` tinyint(1) NOT NULL,
  `changed_by_user_id` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ad_activation_history_ad_changed` (`ad_id`,`changed_at`),
  CONSTRAINT `fk_ad_activation_history_ad`
    FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_ad_activation_history_user`
    FOREIGN KEY (`changed_by_user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ad_activation_history`
  (`ad_id`, `old_is_active`, `new_is_active`, `changed_by_user_id`, `note`, `changed_at`)
SELECT
  a.`id`,
  0,
  1,
  (
    SELECT h.`changed_by_user_id`
    FROM `ad_status_history` h
    WHERE h.`ad_id` = a.`id`
      AND h.`new_status` = 'APPROVED'
    ORDER BY h.`changed_at` DESC, h.`id` DESC
    LIMIT 1
  ),
  'Baseline active-state entry for invoicing migration',
  COALESCE(
    (
      SELECT h.`changed_at`
      FROM `ad_status_history` h
      WHERE h.`ad_id` = a.`id`
        AND h.`new_status` = 'APPROVED'
      ORDER BY h.`changed_at` DESC, h.`id` DESC
      LIMIT 1
    ),
    a.`created_at`
  )
FROM `ads` a
WHERE a.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1
    FROM `ad_activation_history` ah
    WHERE ah.`ad_id` = a.`id`
  );

COMMIT;

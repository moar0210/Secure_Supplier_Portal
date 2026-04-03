START TRANSACTION;

CREATE TABLE IF NOT EXISTS `supplier_logos` (
  `supplier_id` int(11) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `uploaded_by_user_id` int(10) unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`supplier_id`),
  KEY `idx_supplier_logos_uploaded_by` (`uploaded_by_user_id`),
  CONSTRAINT `fk_supplier_logos_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id_supplier`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_supplier_logos_uploaded_by`
    FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `portal_users` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

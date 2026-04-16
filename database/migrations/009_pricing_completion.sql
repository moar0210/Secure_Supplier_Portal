START TRANSACTION;

ALTER TABLE `ads`
  ADD COLUMN `price_model_type` varchar(40) DEFAULT NULL AFTER `description`;

ALTER TABLE `pricing_rules`
  ADD COLUMN `subscription_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `price_per_ad`,
  ADD COLUMN `optional_service_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `subscription_fee`,
  ADD COLUMN `service_fee_label` varchar(120) DEFAULT NULL AFTER `optional_service_fee`;

ALTER TABLE `invoice_lines`
  ADD COLUMN `line_type` enum('SUBSCRIPTION','ADVERTISEMENT','SERVICE') NOT NULL DEFAULT 'ADVERTISEMENT' AFTER `pricing_rule_id`,
  ADD COLUMN `line_code` varchar(50) DEFAULT NULL AFTER `line_type`,
  MODIFY COLUMN `ad_id` int(11) NULL;

UPDATE `invoice_lines`
SET
  `line_type` = 'ADVERTISEMENT',
  `line_code` = CONCAT('AD:', `ad_id`)
WHERE `line_code` IS NULL;

ALTER TABLE `invoice_lines`
  MODIFY COLUMN `line_code` varchar(50) NOT NULL,
  DROP INDEX `uq_invoice_lines_ad`,
  ADD UNIQUE KEY `uq_invoice_lines_code` (`invoice_id`, `line_code`),
  ADD KEY `idx_invoice_lines_type` (`invoice_id`, `line_type`);

COMMIT;

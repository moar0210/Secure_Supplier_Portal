SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `verification_token_innodb`;

CREATE TABLE `verification_token_innodb` (
    `id_verification_token` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `expiry_date` DATETIME NULL,
    `token` VARCHAR(255) NULL,
    PRIMARY KEY (`id_verification_token`),
    UNIQUE KEY `uk_verification_token_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `verification_token_innodb` (
    `id_verification_token`,
    `username`,
    `expiry_date`,
    `token`
)
SELECT
    `id_verification_token`,
    `username`,
    `expiry_date`,
    `token`
FROM `verification_token`
ON DUPLICATE KEY UPDATE
    `expiry_date` = VALUES(`expiry_date`),
    `token` = VALUES(`token`);

DROP TABLE `verification_token`;
RENAME TABLE `verification_token_innodb` TO `verification_token`;

ALTER TABLE `portal_users`
    ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

SET FOREIGN_KEY_CHECKS = 1;

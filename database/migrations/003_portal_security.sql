CREATE TABLE IF NOT EXISTS `verification_token` (
  `id_verification_token` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `expiry_date` datetime(6) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_verification_token`),
  UNIQUE KEY `uk_verification_token_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `verification_token`
  MODIFY `username` varchar(50) NOT NULL;

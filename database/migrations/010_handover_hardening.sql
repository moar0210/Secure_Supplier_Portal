-- 010_handover_hardening.sql
-- Final hardening pass:
--   * Convert verification_token from Aria to InnoDB so it participates in
--     transactions and can carry an FK to portal_users.
--   * Add portal_users.must_change_password for the forced rotation flow.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `verification_token` ENGINE=InnoDB;

-- The legacy schema keyed verification_token by username (varchar) rather than
-- user_id. Leave that as-is for data continuity, but tighten the column types
-- so it behaves identically under InnoDB.
ALTER TABLE `verification_token`
    MODIFY `username` VARCHAR(50) NOT NULL,
    MODIFY `expiry_date` DATETIME NULL,
    MODIFY `token` VARCHAR(255) NULL;

ALTER TABLE `portal_users`
    ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

SET FOREIGN_KEY_CHECKS = 1;

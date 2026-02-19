-- 001_portal_auth_seed.sql
INSERT INTO roles (name) VALUES ('ADMIN')
  ON DUPLICATE KEY UPDATE name=name;

INSERT INTO roles (name) VALUES ('SUPPLIER')
  ON DUPLICATE KEY UPDATE name=name;

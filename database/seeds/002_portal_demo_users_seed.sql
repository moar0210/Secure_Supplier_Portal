-- 002_portal_demo_users_seed.sql
-- Demo users for local testing
-- Login password for both users: password

START TRANSACTION;

INSERT INTO roles (name) VALUES ('ADMIN')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO roles (name) VALUES ('SUPPLIER')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO portal_users
    (username, email, password_hash, supplier_id, is_active, must_change_password, failed_login_count, locked_until)
VALUES
    (
        'admintest',
        'admintest@example.local',
        '$2y$12$Wnt1dXt.8NCzjd9eqKXqAuzUAfQkFX2jLayynfp89mc1kQ9IGAXe6',
        NULL,
        1,
        1,
        0,
        NULL
    )
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    supplier_id = NULL,
    is_active = 1,
    must_change_password = 1,
    failed_login_count = 0,
    locked_until = NULL;

INSERT INTO portal_users
    (username, email, password_hash, supplier_id, is_active, must_change_password, failed_login_count, locked_until)
VALUES
    (
        'suppliertest',
        'suppliertest@example.local',
        '$2y$12$Wnt1dXt.8NCzjd9eqKXqAuzUAfQkFX2jLayynfp89mc1kQ9IGAXe6',
        23,
        1,
        1,
        0,
        NULL
    )
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    supplier_id = 23,
    is_active = 1,
    must_change_password = 1,
    failed_login_count = 0,
    locked_until = NULL;

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT pu.id, r.id
FROM portal_users pu
JOIN roles r ON r.name = 'ADMIN'
WHERE pu.username = 'admintest';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT pu.id, r.id
FROM portal_users pu
JOIN roles r ON r.name = 'SUPPLIER'
WHERE pu.username = 'suppliertest';

COMMIT;
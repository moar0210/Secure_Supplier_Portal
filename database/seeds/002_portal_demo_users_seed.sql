-- Login password for both users: password

START TRANSACTION;

SET @demo_supplier_id = 23;
SET @demo_supplier_uuid = '00000000-0000-0000-0000-000000000023';
SET @demo_address_uuid = '00000000-0000-0000-0000-000000000123';
SET @demo_contact_uuid = '00000000-0000-0000-0000-000000000223';

INSERT INTO roles (name) VALUES ('ADMIN')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO roles (name) VALUES ('SUPPLIER')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO addresses (
    uuid_address,
    country_code_ISO2,
    city,
    description,
    complement,
    region,
    postal_code,
    website,
    time_updated,
    source_info
)
SELECT
    @demo_address_uuid,
    'SE',
    'Karlstad',
    'Demo Street 23',
    '',
    'Varmland',
    '65224',
    'https://supplier-demo.example.local',
    NOW(),
    'PORTAL_SEED'
WHERE NOT EXISTS (
    SELECT 1 FROM addresses WHERE uuid_address = @demo_address_uuid
);

INSERT INTO persons (
    uuid_entity,
    abbreviation,
    country_code_ISO2,
    uuid_address_main,
    first_name,
    last_name,
    full_name,
    gender,
    email_address,
    time_updated,
    source_info
)
SELECT
    @demo_contact_uuid,
    'DS',
    'SE',
    @demo_address_uuid,
    'Demo',
    'Supplier',
    'Demo Supplier',
    'NO_INFO',
    'suppliertest@example.local',
    NOW(),
    'PORTAL_SEED'
WHERE NOT EXISTS (
    SELECT 1 FROM persons WHERE uuid_entity = @demo_contact_uuid
);

INSERT INTO suppliers (
    id_supplier,
    uuid_supplier,
    supplier_type,
    country_code_ISO2,
    uuid_address_main,
    uuid_person_contact,
    unique_id,
    short_name,
    supplier_name,
    homepage,
    email,
    is_inactive,
    source_info,
    time_updated
)
SELECT
    @demo_supplier_id,
    @demo_supplier_uuid,
    'SUPPLIER',
    'SE',
    @demo_address_uuid,
    @demo_contact_uuid,
    'SE-DEMO-23',
    'Demo Supplier',
    'Demo Supplier AB',
    'https://supplier-demo.example.local',
    'suppliertest@example.local',
    0,
    'PORTAL_SEED',
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM suppliers WHERE id_supplier = @demo_supplier_id
);

INSERT IGNORE INTO addresses_entities (
    uuid_address,
    entity_name,
    uuid_entity,
    address_type,
    time_updated,
    source_info
)
VALUES (
    @demo_address_uuid,
    'SUPPLIER',
    @demo_supplier_uuid,
    'MAIN',
    NOW(),
    'PORTAL_SEED'
);

INSERT INTO phones_entities (
    entity_name,
    uuid_entity,
    country_prefix,
    area_code,
    phone_number,
    phone_type,
    display_order,
    is_main,
    time_updated,
    source_info
)
SELECT
    'SUPPLIER',
    @demo_supplier_uuid,
    '46',
    '54',
    '230000',
    'MAIN',
    0,
    1,
    NOW(),
    'PORTAL_SEED'
WHERE NOT EXISTS (
    SELECT 1
    FROM phones_entities
    WHERE entity_name = 'SUPPLIER'
      AND uuid_entity = @demo_supplier_uuid
      AND phone_type = 'MAIN'
);

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
        @demo_supplier_id,
        1,
        1,
        0,
        NULL
    )
ON DUPLICATE KEY UPDATE
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    supplier_id = @demo_supplier_id,
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

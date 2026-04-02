<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$root = dirname(__DIR__, 2);

require $root . '/app/lib/helpers.php';
require $root . '/app/lib/Database.php';
require $root . '/app/lib/UserFacingException.php';
require $root . '/app/lib/Crypto.php';
require $root . '/app/lib/SupplierProfileEncryptionMap.php';
require $root . '/app/lib/SupplierService.php';
require $root . '/app/lib/ProfileEncryptionBackfill.php';

$configPath = $root . '/app/config/config.local.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "[ERROR] Missing app/config/config.local.php\n");
    exit(1);
}

if (!extension_loaded('sodium')) {
    fwrite(STDERR, "[ERROR] sodium extension is required. Run the script with: C:\\xampp\\php\\php.exe -d extension=sodium app\\scripts\\test_profile_encryption.php\n");
    exit(1);
}

$config = require $configPath;
$db = new Database($config);
$pdo = $db->pdo();

$keyV1 = base64_encode(random_bytes(32));
$keyV2 = base64_encode(random_bytes(32));
$crypto = new Crypto([
    'enabled' => true,
    'active_key_id' => 'v1',
    'keys' => [
        'v1' => $keyV1,
    ],
]);

$service = new SupplierService($pdo, $crypto);
$backfill = new ProfileEncryptionBackfill($pdo, $crypto);

$checks = [];
$supplierId = null;
$supplierUuid = 'TST-SUP-' . bin2hex(random_bytes(8));
$addressUuid = 'TST-ADR-' . bin2hex(random_bytes(8));
$personUuid = 'TST-PER-' . bin2hex(random_bytes(8));

try {
    $cipher = $crypto->encryptString('supplier@example.test', SupplierProfileEncryptionMap::SUPPLIERS['email']);
    assertSameValue('supplier@example.test', $crypto->decryptString($cipher, SupplierProfileEncryptionMap::SUPPLIERS['email']), 'round-trip encrypt/decrypt works', $checks);

    [$tamperedPrefix, $tamperedKeyId, $tamperedPayload] = explode(':', $cipher, 3);
    $tamperedBinary = base64_decode(strtr($tamperedPayload, '-_', '+/'), true);
    if ($tamperedBinary === false || strlen($tamperedBinary) < 11) {
        throw new RuntimeException('Unable to prepare tampered ciphertext test data.');
    }
    $tamperedBinary[10] = chr(ord($tamperedBinary[10]) ^ 1);
    $tampered = $tamperedPrefix . ':' . $tamperedKeyId . ':' . rtrim(strtr(base64_encode($tamperedBinary), '+/', '-_'), '=');
    assertThrows(
        static fn() => $crypto->decryptString($tampered, SupplierProfileEncryptionMap::SUPPLIERS['email']),
        'tampered ciphertext fails',
        $checks
    );

    $wrongKeyCrypto = new Crypto([
        'enabled' => true,
        'active_key_id' => 'v1',
        'keys' => [
            'v1' => $keyV2,
        ],
    ]);
    assertThrows(
        static fn() => $wrongKeyCrypto->decryptString($cipher, SupplierProfileEncryptionMap::SUPPLIERS['email']),
        'wrong key fails',
        $checks
    );

    $missingKeyCrypto = new Crypto([
        'enabled' => true,
        'active_key_id' => 'v2',
        'keys' => [
            'v2' => $keyV2,
        ],
    ]);
    assertThrows(
        static fn() => $missingKeyCrypto->decryptString($cipher, SupplierProfileEncryptionMap::SUPPLIERS['email']),
        'missing key id fails',
        $checks
    );

    assertSameValue(true, $crypto->isEncryptedValue($cipher), 'already-encrypted detection works for ciphertext', $checks);
    assertSameValue(false, $crypto->isEncryptedValue('plain@example.test'), 'already-encrypted detection skips plaintext', $checks);
    assertSameValue(null, $crypto->encryptNullable(null, SupplierProfileEncryptionMap::SUPPLIERS['unique_id']), 'null encryption preserves null', $checks);
    assertSameValue('', $crypto->encryptNullable('', SupplierProfileEncryptionMap::SUPPLIERS['unique_id']), 'empty-string encryption preserves empty string', $checks);
    assertSameValue('', $crypto->decryptNullable('', SupplierProfileEncryptionMap::SUPPLIERS['unique_id']), 'empty-string decryption preserves empty string', $checks);

    $pdo->prepare("
        INSERT INTO addresses (
            uuid_address,
            country_code_ISO2,
            city,
            description,
            complement,
            region,
            postal_code,
            website,
            source_info,
            time_updated
        ) VALUES (
            :uuid_address,
            'SE',
            :city,
            :description,
            :complement,
            :region,
            :postal_code,
            :website,
            'PORTAL_TEST',
            NOW()
        )
    ")->execute([
        ':uuid_address' => $addressUuid,
        ':city' => 'Karlstad',
        ':description' => 'Testgatan 12',
        ':complement' => 'Suite 4',
        ':region' => 'Varmland',
        ':postal_code' => '65220',
        ':website' => 'https://supplier.test',
    ]);

    $pdo->prepare("
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
            source_info,
            time_updated
        ) VALUES (
            :uuid_entity,
            'TS',
            'SE',
            :uuid_address_main,
            :first_name,
            :last_name,
            :full_name,
            'NO_INFO',
            :email_address,
            'PORTAL_TEST',
            NOW()
        )
    ")->execute([
        ':uuid_entity' => $personUuid,
        ':uuid_address_main' => $addressUuid,
        ':first_name' => 'Testa',
        ':last_name' => 'Leverantor',
        ':full_name' => 'Testa Leverantor',
        ':email_address' => 'contact@supplier.test',
    ]);

    $pdo->prepare("
        INSERT INTO suppliers (
            uuid_supplier,
            country_code_ISO2,
            uuid_address_main,
            uuid_person_contact,
            unique_id,
            short_name,
            supplier_name,
            homepage,
            email,
            source_info,
            is_inactive,
            time_updated
        ) VALUES (
            :uuid_supplier,
            'SE',
            :uuid_address_main,
            :uuid_person_contact,
            :unique_id,
            :short_name,
            :supplier_name,
            :homepage,
            :email,
            'PORTAL_TEST',
            0,
            NOW()
        )
    ")->execute([
        ':uuid_supplier' => $supplierUuid,
        ':uuid_address_main' => $addressUuid,
        ':uuid_person_contact' => $personUuid,
        ':unique_id' => 'VAT-TEST-001',
        ':short_name' => 'Test Supplier',
        ':supplier_name' => 'Test Supplier Portal',
        ':homepage' => 'https://supplier.test',
        ':email' => 'portal@supplier.test',
    ]);

    $supplierId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO phones_entities (
            entity_name,
            uuid_entity,
            country_prefix,
            area_code,
            phone_number,
            phone_type,
            display_order,
            is_main,
            source_info,
            time_updated
        ) VALUES (
            'SUPPLIER',
            :uuid_entity,
            :country_prefix,
            :area_code,
            :phone_number,
            'MAIN',
            0,
            1,
            'PORTAL_TEST',
            NOW()
        )
    ")->execute([
        ':uuid_entity' => $supplierUuid,
        ':country_prefix' => '46',
        ':area_code' => '54',
        ':phone_number' => '1234567',
    ]);

    $beforeBackfill = $service->getProfile($supplierId);
    assertSameValue('portal@supplier.test', $beforeBackfill['email'] ?? null, 'legacy plaintext rows still read before backfill', $checks);
    assertSameValue('Testa Leverantor', $beforeBackfill['contact_person'] ?? null, 'legacy contact data still reads before backfill', $checks);

    $firstRun = $backfill->run([$supplierId]);
    $secondRun = $backfill->run([$supplierId]);

    assertTrueValue(($firstRun['suppliers']['email']['encrypted'] ?? 0) >= 1, 'backfill encrypts supplier email', $checks);
    assertTrueValue(($firstRun['persons']['full_name']['encrypted'] ?? 0) >= 1, 'backfill encrypts supplier contact rows', $checks);
    assertTrueValue(($firstRun['addresses']['description']['encrypted'] ?? 0) >= 1, 'backfill encrypts supplier address rows', $checks);
    assertTrueValue(($firstRun['phones_entities']['phone_number']['encrypted'] ?? 0) >= 1, 'backfill encrypts supplier phone rows', $checks);
    assertSameValue(0, $secondRun['suppliers']['email']['encrypted'] ?? null, 'backfill is idempotent on second pass', $checks);
    assertTrueValue(($secondRun['suppliers']['email']['already_encrypted'] ?? 0) >= 1, 'backfill reports already-encrypted rows on second pass', $checks);

    $rawSupplier = $pdo->prepare("SELECT email, unique_id FROM suppliers WHERE id_supplier = :id");
    $rawSupplier->execute([':id' => $supplierId]);
    $supplierRow = $rawSupplier->fetch(PDO::FETCH_ASSOC) ?: [];
    assertSameValue(true, $crypto->isEncryptedValue((string)($supplierRow['email'] ?? '')), 'supplier email stored encrypted at rest', $checks);
    assertSameValue(true, $crypto->isEncryptedValue((string)($supplierRow['unique_id'] ?? '')), 'supplier unique id stored encrypted at rest', $checks);

    $rawPerson = $pdo->prepare("SELECT first_name, full_name, email_address FROM persons WHERE uuid_entity = :uuid");
    $rawPerson->execute([':uuid' => $personUuid]);
    $personRow = $rawPerson->fetch(PDO::FETCH_ASSOC) ?: [];
    assertSameValue(true, $crypto->isEncryptedValue((string)($personRow['first_name'] ?? '')), 'person fields stored encrypted at rest', $checks);
    assertSameValue(true, $crypto->isEncryptedValue((string)($personRow['email_address'] ?? '')), 'person email stored encrypted at rest', $checks);

    $rawAddress = $pdo->prepare("SELECT description, city, postal_code FROM addresses WHERE uuid_address = :uuid");
    $rawAddress->execute([':uuid' => $addressUuid]);
    $addressRow = $rawAddress->fetch(PDO::FETCH_ASSOC) ?: [];
    assertSameValue(true, $crypto->isEncryptedValue((string)($addressRow['description'] ?? '')), 'address fields stored encrypted at rest', $checks);
    assertSameValue(true, $crypto->isEncryptedValue((string)($addressRow['city'] ?? '')), 'address city stored encrypted at rest', $checks);

    $rawPhone = $pdo->prepare("SELECT country_prefix, area_code, phone_number FROM phones_entities WHERE uuid_entity = :uuid AND entity_name = 'SUPPLIER' LIMIT 1");
    $rawPhone->execute([':uuid' => $supplierUuid]);
    $phoneRow = $rawPhone->fetch(PDO::FETCH_ASSOC) ?: [];
    assertSameValue(true, $crypto->isEncryptedValue((string)($phoneRow['phone_number'] ?? '')), 'phone number stored encrypted at rest', $checks);

    $afterBackfill = $service->getProfile($supplierId);
    assertSameValue('portal@supplier.test', $afterBackfill['email'] ?? null, 'encrypted supplier rows decrypt on read', $checks);
    assertSameValue('VAT-TEST-001', $afterBackfill['vat_number'] ?? null, 'encrypted supplier unique id decrypts on read', $checks);
    assertSameValue('Testgatan 12', $afterBackfill['address_line_1'] ?? null, 'encrypted address decrypts on read', $checks);
    assertSameValue('+46 (54) 1234567', $afterBackfill['phone_display'] ?? null, 'encrypted phone decrypts on read', $checks);

    $updateInput = [
        'company_name' => 'Test Supplier Portal Updated',
        'short_name' => 'Updated Supplier',
        'contact_person' => 'Nova Updated',
        'email' => 'updated@supplier.test',
        'homepage' => 'https://supplier-updated.test',
        'vat_number' => 'VAT-UPDATED-002',
        'address_line_1' => 'Nyagatan 99',
        'address_line_2' => 'Floor 2',
        'city' => 'Stockholm',
        'region' => 'Stockholm',
        'postal_code' => '11122',
        'country_code' => 'SE',
        'phone_country_prefix' => '46',
        'phone_area_code' => '8',
        'phone_number' => '7654321',
    ];

    $service->updateProfile($supplierId, $updateInput);
    $afterUpdate = $service->getProfile($supplierId);

    assertSameValue('updated@supplier.test', $afterUpdate['email'] ?? null, 'supplier profile update still works with encrypted storage', $checks);
    assertSameValue('Nova Updated', $afterUpdate['contact_person'] ?? null, 'contact person update still works with encrypted storage', $checks);
    assertSameValue('Nyagatan 99', $afterUpdate['address_line_1'] ?? null, 'address update still works with encrypted storage', $checks);
    assertSameValue('7654321', $afterUpdate['phone_number'] ?? null, 'phone update still works with encrypted storage', $checks);

    $listRows = $service->listSuppliers(500);
    $listed = null;
    foreach ($listRows as $row) {
        if ((int)($row['id_supplier'] ?? 0) === $supplierId) {
            $listed = $row;
            break;
        }
    }
    assertSameValue('updated@supplier.test', $listed['email'] ?? null, 'supplier list decrypts encrypted email', $checks);

    echo "Profile encryption verification completed.\n";
    foreach ($checks as $check) {
        echo ($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['label'] . "\n";
    }

    foreach ($checks as $check) {
        if (!$check['pass']) {
            exit(1);
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($supplierId !== null) {
        $pdo->prepare("DELETE FROM phones_entities WHERE entity_name = 'SUPPLIER' AND uuid_entity = :uuid")->execute([':uuid' => $supplierUuid]);
        $pdo->prepare("DELETE FROM addresses_entities WHERE uuid_entity = :uuid OR uuid_address = :uuid_address")->execute([
            ':uuid' => $supplierUuid,
            ':uuid_address' => $addressUuid,
        ]);
        $pdo->prepare("DELETE FROM suppliers WHERE id_supplier = :id")->execute([':id' => $supplierId]);
        $pdo->prepare("DELETE FROM persons WHERE uuid_entity = :uuid")->execute([':uuid' => $personUuid]);
        $pdo->prepare("DELETE FROM addresses WHERE uuid_address = :uuid")->execute([':uuid' => $addressUuid]);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $expected === $actual,
    ];
}

function assertTrueValue(bool $condition, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $condition,
    ];
}

function assertThrows(callable $fn, string $label, array &$checks): void
{
    try {
        $fn();
        $checks[] = [
            'label' => $label,
            'pass' => false,
        ];
    } catch (Throwable) {
        $checks[] = [
            'label' => $label,
            'pass' => true,
        ];
    }
}


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
    fwrite(STDERR, "[ERROR] sodium extension is required. Run the script with: C:\xampp\php\php.exe -d extension=sodium app\scripts\benchmark_profile_encryption.php\n");
    exit(1);
}

$config = require $configPath;
$db = new Database($config);
$pdo = $db->pdo();

$enabledCrypto = new Crypto([
    'enabled' => true,
    'active_key_id' => 'v1',
    'keys' => [
        'v1' => base64_encode(random_bytes(32)),
    ],
]);
$disabledCrypto = new Crypto([
    'enabled' => false,
    'active_key_id' => '',
    'keys' => [],
]);

$enabledService = new SupplierService($pdo, $enabledCrypto);
$disabledService = new SupplierService($pdo, $disabledCrypto);
$backfill = new ProfileEncryptionBackfill($pdo, $enabledCrypto);

$plainFixture = null;
$encryptedFixture = null;

try {
    $plainFixture = createBenchmarkFixture($pdo, 'PLAIN');
    $encryptedFixture = createBenchmarkFixture($pdo, 'ENC');
    $backfill->run([$encryptedFixture['supplier_id']]);

    $plainProfile = $disabledService->getProfile($plainFixture['supplier_id']);
    $encryptedProfile = $enabledService->getProfile($encryptedFixture['supplier_id']);

    if ($plainProfile === null || $encryptedProfile === null) {
        throw new RuntimeException('Benchmark could not load temporary supplier profiles.');
    }

    $fieldSamples = [
        'suppliers.email' => (string)$encryptedProfile['email'],
        'suppliers.unique_id' => (string)$encryptedProfile['vat_number'],
        'persons.full_name' => (string)$encryptedProfile['contact_person'],
        'addresses.description' => (string)$encryptedProfile['address_line_1'],
        'phones_entities.phone_number' => (string)$encryptedProfile['phone_number'],
    ];

    echo "Profile encryption benchmark\n";
    echo "reports=wall-clock-latency,memory-usage,peak-memory\n";
    echo "cpu_metrics=not_measured_in_plain_php\n";
    echo "comparison=crypto-enabled-vs-plaintext-disabled\n\n";

    foreach ($fieldSamples as $aad => $plaintext) {
        $sample = $plaintext === '' ? 'sample' : $plaintext;

        printBenchmark(benchmarkBlock(
            'field.encrypt.enabled.' . $aad,
            1000,
            static fn() => $enabledCrypto->encryptString($sample, $aad)
        ));

        printBenchmark(benchmarkBlock(
            'field.encrypt.disabled.' . $aad,
            1000,
            static fn() => $disabledCrypto->encryptString($sample, $aad)
        ));

        $ciphertext = $enabledCrypto->encryptString($sample, $aad);
        printBenchmark(benchmarkBlock(
            'field.decrypt.enabled.' . $aad,
            1000,
            static fn() => $enabledCrypto->decryptString($ciphertext, $aad)
        ));

        printBenchmark(benchmarkBlock(
            'field.decrypt.disabled.' . $aad,
            1000,
            static fn() => $disabledCrypto->decryptString($sample, $aad)
        ));
    }

    printBenchmark(benchmarkBlock(
        'supplier_profile_read.enabled',
        100,
        static fn() => $enabledService->getProfile($encryptedFixture['supplier_id'])
    ));

    printBenchmark(benchmarkBlock(
        'supplier_profile_read.disabled',
        100,
        static fn() => $disabledService->getProfile($plainFixture['supplier_id'])
    ));

    $plainUpdateInput = profileToUpdateInput($plainProfile);
    $encryptedUpdateInput = profileToUpdateInput($encryptedProfile);

    printBenchmark(benchmarkBlock(
        'supplier_profile_update.enabled',
        10,
        static fn() => $enabledService->updateProfile($encryptedFixture['supplier_id'], $encryptedUpdateInput)
    ));

    printBenchmark(benchmarkBlock(
        'supplier_profile_update.disabled',
        10,
        static fn() => $disabledService->updateProfile($plainFixture['supplier_id'], $plainUpdateInput)
    ));
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_array($plainFixture)) {
        deleteBenchmarkFixture($pdo, $plainFixture);
    }

    if (is_array($encryptedFixture)) {
        deleteBenchmarkFixture($pdo, $encryptedFixture);
    }
}

function benchmarkBlock(string $label, int $iterations, callable $fn): array
{
    gc_collect_cycles();
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $startUsage = memory_get_usage(true);
    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    $elapsedMs = (hrtime(true) - $start) / 1000000;
    $endUsage = memory_get_usage(true);
    $peakUsage = memory_get_peak_usage(true);

    return [
        'label' => $label,
        'iterations' => $iterations,
        'total_ms' => $elapsedMs,
        'avg_ms' => $elapsedMs / $iterations,
        'memory_delta_bytes' => $endUsage - $startUsage,
        'peak_memory_bytes' => $peakUsage,
        'peak_over_start_bytes' => $peakUsage - $startUsage,
    ];
}

function printBenchmark(array $stats): void
{
    echo '[' . $stats['label'] . "]\n";
    echo 'iterations=' . $stats['iterations'] . "\n";
    echo 'wall_clock_total_ms=' . number_format((float)$stats['total_ms'], 3) . "\n";
    echo 'wall_clock_avg_ms=' . number_format((float)$stats['avg_ms'], 3) . "\n";
    echo 'memory_delta_bytes=' . (int)$stats['memory_delta_bytes'] . "\n";
    echo 'memory_delta_human=' . formatBytes((int)$stats['memory_delta_bytes']) . "\n";
    echo 'peak_memory_bytes=' . (int)$stats['peak_memory_bytes'] . "\n";
    echo 'peak_memory_human=' . formatBytes((int)$stats['peak_memory_bytes']) . "\n";
    echo 'peak_over_start_bytes=' . (int)$stats['peak_over_start_bytes'] . "\n";
    echo 'peak_over_start_human=' . formatBytes((int)$stats['peak_over_start_bytes']) . "\n\n";
}

function formatBytes(int $bytes): string
{
    $negative = $bytes < 0;
    $value = abs($bytes);
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    $formatted = number_format($value, $unitIndex === 0 ? 0 : 2);

    return ($negative ? '-' : '') . $formatted . ' ' . $units[$unitIndex];
}

function createBenchmarkFixture(PDO $pdo, string $suffix): array
{
    $supplierUuid = 'BEN-' . $suffix . '-SUP-' . bin2hex(random_bytes(6));
    $addressUuid = 'BEN-' . $suffix . '-ADR-' . bin2hex(random_bytes(6));
    $personUuid = 'BEN-' . $suffix . '-PER-' . bin2hex(random_bytes(6));
    $sourceInfo = 'PORTAL_BENCH_' . $suffix;

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
            :source_info,
            NOW()
        )
    ")->execute([
        ':uuid_address' => $addressUuid,
        ':city' => 'Karlstad',
        ':description' => 'Benchmarkgatan 10',
        ':complement' => 'Suite 2',
        ':region' => 'Varmland',
        ':postal_code' => '65220',
        ':website' => 'https://benchmark.test',
        ':source_info' => $sourceInfo,
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
            'BP',
            'SE',
            :uuid_address_main,
            :first_name,
            :last_name,
            :full_name,
            'NO_INFO',
            :email_address,
            :source_info,
            NOW()
        )
    ")->execute([
        ':uuid_entity' => $personUuid,
        ':uuid_address_main' => $addressUuid,
        ':first_name' => 'Bench',
        ':last_name' => 'Portal',
        ':full_name' => 'Bench Portal',
        ':email_address' => 'bench@supplier.test',
        ':source_info' => $sourceInfo,
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
            :source_info,
            0,
            NOW()
        )
    ")->execute([
        ':uuid_supplier' => $supplierUuid,
        ':uuid_address_main' => $addressUuid,
        ':uuid_person_contact' => $personUuid,
        ':unique_id' => 'VAT-BENCH-001',
        ':short_name' => 'Bench Supplier',
        ':supplier_name' => 'Benchmark Supplier Portal',
        ':homepage' => 'https://benchmark.test',
        ':email' => 'portal@benchmark.test',
        ':source_info' => $sourceInfo,
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
            :source_info,
            NOW()
        )
    ")->execute([
        ':uuid_entity' => $supplierUuid,
        ':country_prefix' => '46',
        ':area_code' => '54',
        ':phone_number' => '5550001',
        ':source_info' => $sourceInfo,
    ]);

    return [
        'supplier_id' => $supplierId,
        'supplier_uuid' => $supplierUuid,
        'address_uuid' => $addressUuid,
        'person_uuid' => $personUuid,
        'source_info' => $sourceInfo,
    ];
}

function deleteBenchmarkFixture(PDO $pdo, array $fixture): void
{
    $pdo->prepare("
        DELETE FROM phones_entities
        WHERE source_info = :source_info
          AND uuid_entity = :uuid_entity
    ")->execute([
        ':source_info' => $fixture['source_info'],
        ':uuid_entity' => $fixture['supplier_uuid'],
    ]);

    $pdo->prepare("
        DELETE FROM addresses_entities
        WHERE source_info = :source_info
          AND (uuid_entity = :uuid_entity OR uuid_address = :uuid_address)
    ")->execute([
        ':source_info' => $fixture['source_info'],
        ':uuid_entity' => $fixture['supplier_uuid'],
        ':uuid_address' => $fixture['address_uuid'],
    ]);

    $pdo->prepare('DELETE FROM suppliers WHERE id_supplier = :id')->execute([
        ':id' => $fixture['supplier_id'],
    ]);
    $pdo->prepare('DELETE FROM persons WHERE uuid_entity = :uuid')->execute([
        ':uuid' => $fixture['person_uuid'],
    ]);
    $pdo->prepare('DELETE FROM addresses WHERE uuid_address = :uuid')->execute([
        ':uuid' => $fixture['address_uuid'],
    ]);
}

function profileToUpdateInput(array $profile): array
{
    return [
        'company_name' => (string)$profile['company_name'],
        'short_name' => (string)$profile['short_name'],
        'contact_person' => (string)$profile['contact_person'],
        'email' => (string)$profile['email'],
        'homepage' => (string)$profile['homepage'],
        'vat_number' => (string)$profile['vat_number'],
        'address_line_1' => (string)$profile['address_line_1'],
        'address_line_2' => (string)$profile['address_line_2'],
        'city' => (string)$profile['city'],
        'region' => (string)$profile['region'],
        'postal_code' => (string)$profile['postal_code'],
        'country_code' => (string)$profile['country_code'],
        'phone_country_prefix' => (string)$profile['phone_country_prefix'],
        'phone_area_code' => (string)$profile['phone_area_code'],
        'phone_number' => (string)$profile['phone_number'],
    ];
}
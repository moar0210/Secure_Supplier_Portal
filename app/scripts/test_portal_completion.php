<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$bootstrap = require __DIR__ . '/bootstrap.php';

require_once $bootstrap['root'] . '/app/lib/PortalUserService.php';
require_once $bootstrap['root'] . '/app/lib/StatsService.php';
require_once $bootstrap['root'] . '/app/lib/InvoiceService.php';

/** @var PDO $pdo */
$pdo = $bootstrap['pdo'];
/** @var Crypto $crypto */
$crypto = $bootstrap['crypto'];
/** @var SupplierService $supplierService */
$supplierService = $bootstrap['supplierService'];
$portalUserService = new PortalUserService($pdo);
$statsService = new StatsService($pdo);
$invoiceService = new InvoiceService($pdo, $crypto, $supplierService, (array)$bootstrap['config']);

$checks = [];
$fixture = [
    'supplier_id' => null,
    'supplier_uuid' => null,
    'address_uuid' => null,
    'person_uuid' => null,
    'ad_id' => null,
    'company_user_id' => null,
    'admin_created_user_id' => null,
    'actor_user_id' => null,
];

try {
    assertTrueValue((bool)$pdo->query("SHOW TABLES LIKE 'portal_activity_logs'")->fetchColumn(), 'activity log table exists', $checks);
    assertTrueValue((bool)$pdo->query("SHOW TABLES LIKE 'ad_daily_stats'")->fetchColumn(), 'daily ad stats table exists', $checks);

    $fixture['actor_user_id'] = createAdminActor($pdo);

    $supplierInput = [
        'company_name' => 'Completion Test Supplier ' . bin2hex(random_bytes(3)),
        'short_name' => 'Completion Test',
        'contact_person' => 'Jane Example',
        'email' => 'completion_' . bin2hex(random_bytes(3)) . '@example.local',
        'homepage' => 'https://example.local/supplier',
        'vat_number' => 'SE556677-8899',
        'address_line_1' => 'Example Street 1',
        'address_line_2' => 'Suite 2',
        'city' => 'Karlstad',
        'region' => 'Varmland',
        'postal_code' => '65224',
        'country_code' => 'SE',
        'phone_country_prefix' => '46',
        'phone_area_code' => '54',
        'phone_number' => '1234567',
    ];

    $fixture['supplier_id'] = $supplierService->createSupplier($supplierInput, null, $fixture['actor_user_id']);
    $profile = $supplierService->getProfile((int)$fixture['supplier_id']);
    assertTrueValue(is_array($profile), 'supplier creation returns a readable profile', $checks);
    assertSameValue(1, (int)($profile['is_inactive'] ?? 0), 'new suppliers start inactive', $checks);

    $supplierRow = fetchSupplierRow($pdo, (int)$fixture['supplier_id']);
    $fixture['supplier_uuid'] = (string)$supplierRow['uuid_supplier'];
    $fixture['address_uuid'] = (string)$supplierRow['uuid_address_main'];
    $fixture['person_uuid'] = (string)$supplierRow['uuid_person_contact'];

    $supplierService->setSupplierActiveState((int)$fixture['supplier_id'], true);
    $profile = $supplierService->getProfile((int)$fixture['supplier_id']);
    assertSameValue(0, (int)($profile['is_inactive'] ?? 1), 'supplier activation toggles approval state', $checks);

    $fixture['company_user_id'] = $portalUserService->createUserForSupplier((int)$fixture['supplier_id'], [
        'username' => 'company_' . bin2hex(random_bytes(4)),
        'email' => 'company_' . bin2hex(random_bytes(4)) . '@example.local',
        'password' => 'CompanyPass123',
        'confirm_password' => 'CompanyPass123',
        'is_active' => '1',
    ]);
    $supplierUsers = $portalUserService->listUsersForSupplier((int)$fixture['supplier_id']);
    assertTrueValue(count($supplierUsers) >= 1, 'supplier company user is listed', $checks);
    if (mustChangePasswordColumnExists($pdo)) {
        assertSameValue(1, fetchMustChangePasswordFlag($pdo, (int)$fixture['company_user_id']), 'new supplier-linked users must rotate their initial password', $checks);
    }

    $portalUserService->updateUserForSupplier((int)$fixture['company_user_id'], (int)$fixture['supplier_id'], [
        'username' => 'company_updated_' . bin2hex(random_bytes(3)),
        'email' => 'company_updated_' . bin2hex(random_bytes(3)) . '@example.local',
        'password' => '',
        'confirm_password' => '',
        'is_active' => '0',
    ]);
    $updatedCompanyUser = $portalUserService->getUser((int)$fixture['company_user_id']);
    assertSameValue(0, (int)($updatedCompanyUser['is_active'] ?? 1), 'supplier user update can deactivate a company user', $checks);

    $fixture['admin_created_user_id'] = $portalUserService->createUserAsAdmin([
        'username' => 'admin_scope_' . bin2hex(random_bytes(4)),
        'email' => 'admin_scope_' . bin2hex(random_bytes(4)) . '@example.local',
        'password' => 'AdminScope123',
        'confirm_password' => 'AdminScope123',
        'role_name' => 'SUPPLIER',
        'supplier_id' => (string)$fixture['supplier_id'],
        'is_active' => '1',
    ]);
    $portalUserService->updateUserAsAdmin((int)$fixture['admin_created_user_id'], [
        'username' => 'admin_updated_' . bin2hex(random_bytes(3)),
        'email' => 'admin_updated_' . bin2hex(random_bytes(3)) . '@example.local',
        'password' => 'AdminUpdated123',
        'confirm_password' => 'AdminUpdated123',
        'role_name' => 'ADMIN',
        'supplier_id' => '',
        'is_active' => '1',
    ], (int)$fixture['actor_user_id']);
    $updatedAdminManagedUser = $portalUserService->getUser((int)$fixture['admin_created_user_id']);
    assertTrueValue(in_array('ADMIN', (array)($updatedAdminManagedUser['roles'] ?? []), true), 'admin user management can change roles', $checks);
    if (mustChangePasswordColumnExists($pdo)) {
        assertSameValue(1, fetchMustChangePasswordFlag($pdo, (int)$fixture['admin_created_user_id']), 'admin-issued password resets require a forced password change', $checks);
    }

    PortalLogger::write($pdo, 'ACTIVITY', 'Completion test log event', [
        'actor_user_id' => $fixture['actor_user_id'],
        'supplier_id' => $fixture['supplier_id'],
        'page' => 'test_portal_completion',
    ]);

    $fixture['ad_id'] = createApprovedActiveAd($pdo, (int)$fixture['supplier_id'], (int)$fixture['actor_user_id']);

    $publicAds = $statsService->listPublicAds();
    $publicAdIds = array_map(static fn(array $row): int => (int)$row['id'], $publicAds);
    assertTrueValue(in_array((int)$fixture['ad_id'], $publicAdIds, true), 'approved active ad appears in the shop API public listing', $checks);

    $statsService->recordImpressions([(int)$fixture['ad_id']]);
    $statsService->recordImpressions([(int)$fixture['ad_id']]);
    $statsService->recordClick((int)$fixture['ad_id']);

    $supplierDashboard = $statsService->supplierDashboard((int)$fixture['supplier_id'], [
        'date_from' => date('Y-m-d'),
        'date_to' => date('Y-m-d'),
        'granularity' => 'day',
    ]);
    assertSameValue(2, (int)($supplierDashboard['summary']['impressions'] ?? 0), 'supplier dashboard counts impressions', $checks);
    assertSameValue(1, (int)($supplierDashboard['summary']['clicks'] ?? 0), 'supplier dashboard counts clicks', $checks);

    $adminReport = $statsService->adminReport([
        'date_from' => date('Y-m-d'),
        'date_to' => date('Y-m-d'),
        'granularity' => 'day',
    ]);
    assertTrueValue((int)($adminReport['visibility']['impressions'] ?? 0) >= 2, 'admin report aggregates visibility totals', $checks);
    assertTrueValue(count((array)($adminReport['recent_activity'] ?? [])) >= 1, 'admin report includes recent activity log rows', $checks);

    $draftInvoiceId = createIsolatedDraftInvoice($pdo, $crypto, $supplierService, (int)$fixture['supplier_id'], (int)$fixture['actor_user_id']);
    assertTrueValue($draftInvoiceId > 0, 'an isolated draft invoice fixture can be created for deletion checks', $checks);

    $invoiceService->deleteDraftInvoice($draftInvoiceId);
    assertSameValue(0, findInvoiceExists($pdo, $draftInvoiceId), 'draft invoice deletion removes the invoice', $checks);

    echo "Portal completion verification completed.\n";
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
    cleanupFixture($pdo, $fixture);
}

function createAdminActor(PDO $pdo): int
{
    $username = 'completion_admin_' . bin2hex(random_bytes(4));
    $email = $username . '@example.local';
    $passwordHash = password_hash('CompletionAdmin123', PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Unable to create admin password hash.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO portal_users (
            username,
            email,
            password_hash,
            supplier_id,
            is_active,
            failed_login_count,
            locked_until
        ) VALUES (
            :username,
            :email,
            :password_hash,
            NULL,
            1,
            0,
            NULL
        )
    ");
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
    ]);
    $userId = (int)$pdo->lastInsertId();

    $roleStmt = $pdo->prepare("
        INSERT INTO user_roles (user_id, role_id)
        SELECT :user_id, id
        FROM roles
        WHERE name = 'ADMIN'
    ");
    $roleStmt->execute([':user_id' => $userId]);

    return $userId;
}

function fetchSupplierRow(PDO $pdo, int $supplierId): array
{
    $stmt = $pdo->prepare("
        SELECT uuid_supplier, uuid_address_main, uuid_person_contact
        FROM suppliers
        WHERE id_supplier = :supplier_id
        LIMIT 1
    ");
    $stmt->execute([':supplier_id' => $supplierId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException('Unable to load the created supplier row.');
    }

    return $row;
}

function createApprovedActiveAd(PDO $pdo, int $supplierId, int $actorUserId): int
{
    $periodStart = new DateTimeImmutable('first day of this month');
    $periodEnd = new DateTimeImmutable('last day of this month');

    $stmt = $pdo->prepare("
        INSERT INTO ads (
            supplier_id,
            category_id,
            title,
            description,
            price_model_type,
            price_text,
            valid_from,
            valid_to,
            is_active,
            status,
            rejection_reason,
            created_at,
            updated_at
        ) VALUES (
            :supplier_id,
            NULL,
            :title,
            :description,
            'FIXED_DISCOUNT',
            'Marketplace completion offer',
            :valid_from,
            :valid_to,
            1,
            'APPROVED',
            NULL,
            NOW(),
            NOW()
        )
    ");
    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':title' => 'Completion Test Ad ' . bin2hex(random_bytes(4)),
        ':description' => 'Advertisement created by the completion verification script.',
        ':valid_from' => $periodStart->format('Y-m-d'),
        ':valid_to' => $periodEnd->format('Y-m-d'),
    ]);
    $adId = (int)$pdo->lastInsertId();

    $statusStmt = $pdo->prepare("
        INSERT INTO ad_status_history (
            ad_id,
            old_status,
            new_status,
            reason,
            changed_by_user_id,
            changed_at
        ) VALUES (
            :ad_id,
            NULL,
            'APPROVED',
            NULL,
            :changed_by_user_id,
            NOW()
        )
    ");
    $statusStmt->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
    ]);

    $activationStmt = $pdo->prepare("
        INSERT INTO ad_activation_history (
            ad_id,
            old_is_active,
            new_is_active,
            changed_by_user_id,
            note,
            changed_at
        ) VALUES (
            :ad_id,
            0,
            1,
            :changed_by_user_id,
            'Completion verification activation',
            NOW()
        )
    ");
    $activationStmt->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
    ]);

    return $adId;
}

function createIsolatedDraftInvoice(PDO $pdo, Crypto $crypto, SupplierService $supplierService, int $supplierId, int $actorUserId): int
{
    $profile = $supplierService->getProfile($supplierId);
    if ($profile === null) {
        throw new RuntimeException('Unable to load the supplier profile for the draft invoice fixture.');
    }

    $billingYear = 2099;
    $billingMonth = 12;
    $invoiceNumber = 'TST-' . strtoupper(bin2hex(random_bytes(6)));
    $issueDate = '2099-12-01';
    $dueDate = '2099-12-31';

    $snapshots = [
        'supplier_name_snapshot' => encryptSnapshotValue($crypto, (string)$profile['company_name'], 'invoices.supplier_name_snapshot'),
        'supplier_short_name_snapshot' => encryptSnapshotValue($crypto, (string)$profile['short_name'], 'invoices.supplier_short_name_snapshot'),
        'contact_person_snapshot' => encryptSnapshotValue($crypto, (string)$profile['contact_person'], 'invoices.contact_person_snapshot'),
        'supplier_email_snapshot' => encryptSnapshotValue($crypto, (string)$profile['email'], 'invoices.supplier_email_snapshot'),
        'supplier_vat_number_snapshot' => encryptSnapshotValue($crypto, (string)$profile['vat_number'], 'invoices.supplier_vat_number_snapshot'),
        'homepage_snapshot' => encryptSnapshotValue($crypto, (string)$profile['homepage'], 'invoices.homepage_snapshot'),
        'address_line_1_snapshot' => encryptSnapshotValue($crypto, (string)$profile['address_line_1'], 'invoices.address_line_1_snapshot'),
        'address_line_2_snapshot' => encryptSnapshotValue($crypto, (string)$profile['address_line_2'], 'invoices.address_line_2_snapshot'),
        'city_snapshot' => encryptSnapshotValue($crypto, (string)$profile['city'], 'invoices.city_snapshot'),
        'region_snapshot' => encryptSnapshotValue($crypto, (string)$profile['region'], 'invoices.region_snapshot'),
        'postal_code_snapshot' => encryptSnapshotValue($crypto, (string)$profile['postal_code'], 'invoices.postal_code_snapshot'),
    ];

    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            supplier_id,
            pricing_rule_id,
            billing_year,
            billing_month,
            billing_period_start,
            billing_period_end,
            sequence_no,
            invoice_number,
            status,
            currency_code,
            issue_date,
            due_date,
            vat_rate,
            subtotal_amount,
            vat_amount,
            total_amount,
            supplier_name_snapshot,
            supplier_short_name_snapshot,
            contact_person_snapshot,
            supplier_email_snapshot,
            supplier_vat_number_snapshot,
            homepage_snapshot,
            address_line_1_snapshot,
            address_line_2_snapshot,
            city_snapshot,
            region_snapshot,
            postal_code_snapshot,
            country_code_snapshot,
            generated_by_user_id
        ) VALUES (
            :supplier_id,
            NULL,
            :billing_year,
            :billing_month,
            :billing_period_start,
            :billing_period_end,
            :sequence_no,
            :invoice_number,
            :status,
            'SEK',
            :issue_date,
            :due_date,
            25.00,
            0.00,
            0.00,
            0.00,
            :supplier_name_snapshot,
            :supplier_short_name_snapshot,
            :contact_person_snapshot,
            :supplier_email_snapshot,
            :supplier_vat_number_snapshot,
            :homepage_snapshot,
            :address_line_1_snapshot,
            :address_line_2_snapshot,
            :city_snapshot,
            :region_snapshot,
            :postal_code_snapshot,
            :country_code_snapshot,
            :generated_by_user_id
        )
    ");
    $stmt->execute([
        ':supplier_id' => $supplierId,
        ':billing_year' => $billingYear,
        ':billing_month' => $billingMonth,
        ':billing_period_start' => $issueDate,
        ':billing_period_end' => $dueDate,
        ':sequence_no' => random_int(9000, 9999),
        ':invoice_number' => $invoiceNumber,
        ':status' => InvoiceService::STATUS_DRAFT,
        ':issue_date' => $issueDate,
        ':due_date' => $dueDate,
        ':supplier_name_snapshot' => $snapshots['supplier_name_snapshot'],
        ':supplier_short_name_snapshot' => $snapshots['supplier_short_name_snapshot'],
        ':contact_person_snapshot' => $snapshots['contact_person_snapshot'],
        ':supplier_email_snapshot' => $snapshots['supplier_email_snapshot'],
        ':supplier_vat_number_snapshot' => $snapshots['supplier_vat_number_snapshot'],
        ':homepage_snapshot' => $snapshots['homepage_snapshot'],
        ':address_line_1_snapshot' => $snapshots['address_line_1_snapshot'],
        ':address_line_2_snapshot' => $snapshots['address_line_2_snapshot'],
        ':city_snapshot' => $snapshots['city_snapshot'],
        ':region_snapshot' => $snapshots['region_snapshot'],
        ':postal_code_snapshot' => $snapshots['postal_code_snapshot'],
        ':country_code_snapshot' => strtoupper((string)$profile['country_code']),
        ':generated_by_user_id' => $actorUserId,
    ]);

    return (int)$pdo->lastInsertId();
}

function findInvoiceExists(PDO $pdo, int $invoiceId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE id = :id');
    $stmt->execute([':id' => $invoiceId]);

    return (int)$stmt->fetchColumn();
}

function mustChangePasswordColumnExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM portal_users LIKE 'must_change_password'");

    return $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function fetchMustChangePasswordFlag(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT must_change_password FROM portal_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);

    return (int)($stmt->fetchColumn() ?: 0);
}

function encryptSnapshotValue(Crypto $crypto, string $value, string $aad): string
{
    return (string)$crypto->encryptNullable($value, $aad);
}

function cleanupFixture(PDO $pdo, array $fixture): void
{
    if (!empty($fixture['supplier_uuid'])) {
        $stmt = $pdo->prepare("
            DELETE FROM phones_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
        ");
        $stmt->execute([':uuid_entity' => $fixture['supplier_uuid']]);

        $stmt = $pdo->prepare("
            DELETE FROM addresses_entities
            WHERE entity_name = 'SUPPLIER'
              AND uuid_entity = :uuid_entity
        ");
        $stmt->execute([':uuid_entity' => $fixture['supplier_uuid']]);
    }

    if (!empty($fixture['supplier_id'])) {
        $pdo->prepare('DELETE FROM invoices WHERE supplier_id = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
        $pdo->prepare('DELETE FROM ads WHERE supplier_id = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
        $pdo->prepare('DELETE FROM portal_activity_logs WHERE supplier_id = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
    }

    foreach (['company_user_id', 'admin_created_user_id', 'actor_user_id'] as $userKey) {
        if (!empty($fixture[$userKey])) {
            $pdo->prepare('DELETE FROM portal_activity_logs WHERE user_id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
            $pdo->prepare('DELETE FROM portal_users WHERE id = :user_id')->execute([
                ':user_id' => $fixture[$userKey],
            ]);
        }
    }

    if (!empty($fixture['supplier_id'])) {
        $pdo->prepare('DELETE FROM suppliers WHERE id_supplier = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
    }

    if (!empty($fixture['person_uuid'])) {
        $pdo->prepare('DELETE FROM persons WHERE uuid_entity = :uuid_entity')->execute([
            ':uuid_entity' => $fixture['person_uuid'],
        ]);
    }

    if (!empty($fixture['address_uuid'])) {
        $pdo->prepare('DELETE FROM addresses WHERE uuid_address = :uuid_address')->execute([
            ':uuid_address' => $fixture['address_uuid'],
        ]);
    }
}

function assertTrueValue(bool $condition, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $condition,
    ];
}

function assertSameValue(mixed $expected, mixed $actual, string $label, array &$checks): void
{
    $checks[] = [
        'label' => $label,
        'pass' => $expected === $actual,
    ];
}

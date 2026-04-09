<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$bootstrap = require __DIR__ . '/bootstrap.php';

require_once $bootstrap['root'] . '/app/lib/InvoiceService.php';

/** @var PDO $pdo */
$pdo = $bootstrap['pdo'];
/** @var Crypto $crypto */
$crypto = $bootstrap['crypto'];
/** @var SupplierService $supplierService */
$supplierService = $bootstrap['supplierService'];
$config = (array)$bootstrap['config'];

$invoiceService = new InvoiceService($pdo, $crypto, $supplierService, $config);

$checks = [];
$fixture = null;
$actorUserId = null;
$pricingRuleId = null;

try {
    $today = new DateTimeImmutable('today');
    $currentPeriodStart = new DateTimeImmutable($today->format('Y-m-01'));
    $nextPeriodStart = $currentPeriodStart->modify('first day of next month');

    $currentBillingMonth = $currentPeriodStart->format('Y-m');
    $nextBillingMonth = $nextPeriodStart->format('Y-m');

    $actorUserId = createTemporaryPortalUser($pdo);
    $pricingRuleId = createTemporaryPricingRule($pdo, $currentPeriodStart);
    $fixture = createInvoiceFixture($pdo);

    $currentAdId = createApprovedActiveAd(
        $pdo,
        $fixture['supplier_id'],
        $actorUserId,
        'Visibility Package A',
        $currentPeriodStart,
        $currentPeriodStart->modify('last day of this month')
    );
    $futureAdId = createApprovedActiveAd(
        $pdo,
        $fixture['supplier_id'],
        $actorUserId,
        'Visibility Package B',
        $nextPeriodStart,
        $nextPeriodStart->modify('last day of this month')
    );

    $currentRun = $invoiceService->generateMonthlyInvoices($currentBillingMonth, $actorUserId);
    assertSameValue(1, $currentRun['created'] ?? null, 'current-month generation creates one draft invoice', $checks);

    $currentInvoice = findInvoiceForSupplierMonth($invoiceService, $fixture['supplier_id'], $currentBillingMonth);
    assertTrueValue(is_array($currentInvoice), 'current-month draft invoice can be loaded for the supplier/month', $checks);

    if (!is_array($currentInvoice)) {
        throw new RuntimeException('Current-month draft invoice was not created for the temporary supplier.');
    }

    assertSameValue(InvoiceService::STATUS_DRAFT, $currentInvoice['status'] ?? null, 'current-month invoice starts as DRAFT', $checks);
    assertSameValue(1, count((array)($currentInvoice['lines'] ?? [])), 'current-month draft contains one invoice line', $checks);
    assertSameValue('Visibility Package A', $currentInvoice['lines'][0]['ad_title'] ?? null, 'current-month invoice line keeps the advertisement title', $checks);
    assertSameValue('321.00', number_format((float)($currentInvoice['total_amount'] ?? 0), 2, '.', ''), 'current-month total matches the configured price + VAT', $checks);

    $futureRun = $invoiceService->generateMonthlyInvoices($nextBillingMonth, $actorUserId);
    $futureInvoice = findInvoiceForSupplierMonth($invoiceService, $fixture['supplier_id'], $nextBillingMonth);
    assertTrueValue(is_array($futureInvoice), 'next-month generation creates a future draft invoice', $checks);

    $secondFutureRun = $invoiceService->generateMonthlyInvoices($nextBillingMonth, $actorUserId);
    $futureInvoiceAfterRerun = findInvoiceForSupplierMonth($invoiceService, $fixture['supplier_id'], $nextBillingMonth);
    assertTrueValue(is_array($futureInvoiceAfterRerun), 're-running next-month billing keeps the future draft invoice available', $checks);

    deactivateApprovedAd($pdo, $futureAdId, $actorUserId);

    $thirdFutureRun = $invoiceService->generateMonthlyInvoices($nextBillingMonth, $actorUserId);
    assertSameValue(1, $thirdFutureRun['removed'] ?? null, 'future draft invoices are removed when no ads remain billable for that month', $checks);
    assertSameValue(null, findInvoiceForSupplierMonth($invoiceService, $fixture['supplier_id'], $nextBillingMonth), 'stale future draft invoice is no longer present after cleanup', $checks);

    reactivateApprovedAd($pdo, $futureAdId, $actorUserId);

    $fourthFutureRun = $invoiceService->generateMonthlyInvoices($nextBillingMonth, $actorUserId);
    assertSameValue(1, $fourthFutureRun['created'] ?? null, 'future billing can recreate a draft invoice after the ad becomes billable again', $checks);

    $currentInvoiceId = (int)$currentInvoice['id'];
    $invoiceService->transitionToSent($currentInvoiceId, $actorUserId);
    $sentInvoice = $invoiceService->getInvoiceDetailForAdmin($currentInvoiceId);
    assertSameValue(InvoiceService::STATUS_SENT, $sentInvoice['status'] ?? null, 'draft invoice can transition to SENT', $checks);

    $invoiceService->recordPayment($currentInvoiceId, [
        'amount' => number_format((float)$sentInvoice['total_amount'], 2, '.', ''),
        'payment_date' => $today->format('Y-m-d'),
        'payment_method' => 'Bank transfer',
    ], $actorUserId);

    $paidInvoice = $invoiceService->getInvoiceDetailForAdmin($currentInvoiceId);
    assertSameValue(InvoiceService::STATUS_PAID, $paidInvoice['status'] ?? null, 'sent invoice can be marked as PAID', $checks);
    assertSameValue('Bank transfer', $paidInvoice['payment']['payment_method'] ?? null, 'payment method is stored with the invoice payment', $checks);

    $pdfBinary = $invoiceService->generatePdfBinary($paidInvoice);
    assertTrueValue(str_starts_with($pdfBinary, '%PDF'), 'invoice PDF generation returns PDF binary', $checks);

    echo "Invoicing verification completed.\n";
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
    if (is_array($fixture)) {
        $pdo->prepare('DELETE FROM invoices WHERE supplier_id = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
        $pdo->prepare('DELETE FROM ads WHERE supplier_id = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
        $pdo->prepare('DELETE FROM suppliers WHERE id_supplier = :supplier_id')->execute([
            ':supplier_id' => $fixture['supplier_id'],
        ]);
        $pdo->prepare('DELETE FROM persons WHERE uuid_entity = :person_uuid')->execute([
            ':person_uuid' => $fixture['person_uuid'],
        ]);
        $pdo->prepare('DELETE FROM addresses WHERE uuid_address = :address_uuid')->execute([
            ':address_uuid' => $fixture['address_uuid'],
        ]);
    }

    if ($pricingRuleId !== null) {
        $pdo->prepare('DELETE FROM pricing_rules WHERE id = :id')->execute([
            ':id' => $pricingRuleId,
        ]);
    }

    if ($actorUserId !== null) {
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([
            ':user_id' => $actorUserId,
        ]);
        $pdo->prepare('DELETE FROM portal_users WHERE id = :user_id')->execute([
            ':user_id' => $actorUserId,
        ]);
    }
}

function createTemporaryPortalUser(PDO $pdo): int
{
    $token = bin2hex(random_bytes(6));
    $username = 'invoice_test_' . $token;
    $email = $username . '@example.local';
    $passwordHash = password_hash('temporary-password-123', PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Unable to create a temporary password hash.');
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

    return (int)$pdo->lastInsertId();
}

function createTemporaryPricingRule(PDO $pdo, DateTimeImmutable $periodStart): int
{
    $stmt = $pdo->prepare("
        INSERT INTO pricing_rules (
            name,
            description,
            price_per_ad,
            currency_code,
            vat_rate,
            effective_from,
            effective_to,
            is_active
        ) VALUES (
            :name,
            :description,
            :price_per_ad,
            'SEK',
            25.00,
            :effective_from,
            NULL,
            1
        )
    ");
    $stmt->execute([
        ':name' => 'Invoice Verification Rule ' . bin2hex(random_bytes(4)),
        ':description' => 'Temporary pricing rule for invoicing verification.',
        ':price_per_ad' => '256.80',
        ':effective_from' => $periodStart->format('Y-m-d'),
    ]);

    return (int)$pdo->lastInsertId();
}

function createInvoiceFixture(PDO $pdo): array
{
    $suffix = bin2hex(random_bytes(6));
    $addressUuid = 'INV-ADR-' . $suffix;
    $personUuid = 'INV-PER-' . $suffix;
    $supplierUuid = 'INV-SUP-' . $suffix;
    $sourceInfo = 'INVTEST_' . $suffix;

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
        ':description' => 'Testgatan 42',
        ':complement' => 'Floor 3',
        ':region' => 'Varmland',
        ':postal_code' => '65220',
        ':website' => 'https://angstrom.example.test',
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
            'AO',
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
        ':first_name' => 'Ase',
        ':last_name' => 'Oberg',
        ':full_name' => 'Ase Oberg',
        ':email_address' => 'contact@angstrom.example.test',
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
        ':unique_id' => 'VAT-' . strtoupper($suffix),
        ':short_name' => 'Angstrom Test',
        ':supplier_name' => 'Angstrom Supplier Test',
        ':homepage' => 'https://angstrom.example.test',
        ':email' => 'portal@angstrom.example.test',
        ':source_info' => $sourceInfo,
    ]);

    return [
        'supplier_id' => (int)$pdo->lastInsertId(),
        'person_uuid' => $personUuid,
        'address_uuid' => $addressUuid,
    ];
}

function createApprovedActiveAd(PDO $pdo, int $supplierId, int $actorUserId, string $title, DateTimeImmutable $validFrom, DateTimeImmutable $validTo): int
{
    $changedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO ads (
            supplier_id,
            category_id,
            title,
            description,
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
            :price_text,
            :valid_from,
            :valid_to,
            1,
            'APPROVED',
            NULL,
            :created_at,
            :updated_at
        )
    ")->execute([
        ':supplier_id' => $supplierId,
        ':title' => $title,
        ':description' => 'Temporary invoice verification advertisement.',
        ':price_text' => 'Included in verification flow',
        ':valid_from' => $validFrom->format('Y-m-d'),
        ':valid_to' => $validTo->format('Y-m-d'),
        ':created_at' => $changedAt,
        ':updated_at' => $changedAt,
    ]);

    $adId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO ad_status_history (
            ad_id,
            old_status,
            new_status,
            reason,
            changed_by_user_id,
            changed_at
        ) VALUES (
            :ad_id,
            'PENDING',
            'APPROVED',
            NULL,
            :changed_by_user_id,
            :changed_at
        )
    ")->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
        ':changed_at' => $changedAt,
    ]);

    $pdo->prepare("
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
            'Activated for invoice verification.',
            :changed_at
        )
    ")->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
        ':changed_at' => $changedAt,
    ]);

    return $adId;
}

function deactivateApprovedAd(PDO $pdo, int $adId, int $actorUserId): void
{
    $changedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $pdo->prepare("
        UPDATE ads
        SET is_active = 0,
            updated_at = :updated_at
        WHERE id = :id
    ")->execute([
        ':id' => $adId,
        ':updated_at' => $changedAt,
    ]);

    $pdo->prepare("
        INSERT INTO ad_activation_history (
            ad_id,
            old_is_active,
            new_is_active,
            changed_by_user_id,
            note,
            changed_at
        ) VALUES (
            :ad_id,
            1,
            0,
            :changed_by_user_id,
            'Temporarily deactivated during invoice verification.',
            :changed_at
        )
    ")->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
        ':changed_at' => $changedAt,
    ]);
}

function reactivateApprovedAd(PDO $pdo, int $adId, int $actorUserId): void
{
    $changedAt = (new DateTimeImmutable('now'))->modify('+1 minute')->format('Y-m-d H:i:s');

    $pdo->prepare("
        UPDATE ads
        SET is_active = 1,
            updated_at = :updated_at
        WHERE id = :id
    ")->execute([
        ':id' => $adId,
        ':updated_at' => $changedAt,
    ]);

    $pdo->prepare("
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
            'Reactivated during invoice verification.',
            :changed_at
        )
    ")->execute([
        ':ad_id' => $adId,
        ':changed_by_user_id' => $actorUserId,
        ':changed_at' => $changedAt,
    ]);
}

function findInvoiceForSupplierMonth(InvoiceService $invoiceService, int $supplierId, string $billingMonth): ?array
{
    $rows = $invoiceService->listInvoicesForAdmin([
        'supplier_id' => (string)$supplierId,
        'billing_month' => $billingMonth,
        'status' => 'ALL',
    ]);

    if ($rows === []) {
        return null;
    }

    return $invoiceService->getInvoiceDetailForAdmin((int)$rows[0]['id']);
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


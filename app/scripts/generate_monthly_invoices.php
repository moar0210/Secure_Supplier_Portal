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

$billingMonth = $argv[1] ?? date('Y-m');
$actorUserId = isset($argv[2]) && ctype_digit((string)$argv[2])
    ? (int)$argv[2]
    : resolveDefaultAdminUserId($pdo);

if ($actorUserId < 1) {
    fwrite(STDERR, "No active admin user was found for invoice generation.\n");
    exit(1);
}

$invoiceService = new InvoiceService($pdo, $crypto, $supplierService, $config);

try {
    $result = $invoiceService->generateMonthlyInvoices($billingMonth, $actorUserId);
    echo 'Billing month: ' . $result['billing_month'] . PHP_EOL;
    echo 'Eligible suppliers: ' . (int)$result['eligible_suppliers'] . PHP_EOL;
    echo 'Eligible ads: ' . (int)$result['eligible_ads'] . PHP_EOL;
    echo 'Created: ' . (int)$result['created'] . PHP_EOL;
    echo 'Updated: ' . (int)$result['updated'] . PHP_EOL;
    echo 'Removed: ' . (int)$result['removed'] . PHP_EOL;
    echo 'Skipped: ' . (int)$result['skipped'] . PHP_EOL;
    echo 'Failed: ' . (int)$result['failed'] . PHP_EOL;

    foreach ((array)($result['errors'] ?? []) as $error) {
        echo 'Error: ' . $error . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function resolveDefaultAdminUserId(PDO $pdo): int
{
    $stmt = $pdo->prepare("
        SELECT u.id
        FROM portal_users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r ON r.id = ur.role_id
        WHERE r.name = 'ADMIN'
          AND u.is_active = 1
        ORDER BY u.id ASC
        LIMIT 1
    ");
    $stmt->execute();

    return (int)($stmt->fetchColumn() ?: 0);
}

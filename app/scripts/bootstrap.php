<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/lib/helpers.php';

$configPath = $root . '/app/config/config.local.php';
if (!file_exists($configPath)) {
    throw new RuntimeException('Missing app/config/config.local.php. Copy app/config/config.example.php and add local credentials.');
}

$config = require $configPath;

require $root . '/app/lib/Database.php';
require $root . '/app/lib/UserFacingException.php';
require $root . '/app/lib/Crypto.php';
require $root . '/app/lib/SupplierProfileEncryptionMap.php';
require $root . '/app/lib/SupplierService.php';
require $root . '/app/lib/ProfileEncryptionBackfill.php';

$db = new Database($config);
$pdo = $db->pdo();
$crypto = new Crypto((array)($config['crypto'] ?? []));
$supplierService = new SupplierService($pdo, $crypto);
$backfill = new ProfileEncryptionBackfill($pdo, $crypto);

return [
    'root' => $root,
    'config' => $config,
    'db' => $db,
    'pdo' => $pdo,
    'crypto' => $crypto,
    'supplierService' => $supplierService,
    'backfill' => $backfill,
];

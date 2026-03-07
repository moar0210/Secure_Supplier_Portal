<?php

declare(strict_types=1);

$auth->requireRole('SUPPLIER');

$supplierId = $auth->supplierId();
if ($supplierId === null) {
    header("Location: ?page=403");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ?page=404");
    exit;
}

Csrf::verifyOrFail();

$idRaw = $_POST['id'] ?? null;
$activeRaw = $_POST['active'] ?? null;

if (!$idRaw || !ctype_digit((string)$idRaw)) {
    header("Location: ?page=404");
    exit;
}
$adId = (int)$idRaw;

$active = ((string)$activeRaw === '1');

try {
    $adsService->toggleActiveForSupplier($adId, $supplierId, $active, (int)$auth->userId());
} catch (Throwable $e) {
    
}

header("Location: ?page=ads_list");
exit;
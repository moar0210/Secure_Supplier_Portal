<?php

declare(strict_types=1);

// Must be logged in
$auth->requireLogin();

$id = $_GET["id"] ?? null;
if (!$id || !ctype_digit($id)) {
    header("Location: ?page=404");
    exit;
}
$supplierIdRequested = (int)$id;

// Supplier can only view own supplier record (admin can view any)
if ($auth->hasRole('SUPPLIER') && !$auth->hasRole('ADMIN')) {
    $ownSupplierId = $auth->supplierId();
    if ($ownSupplierId === null || $ownSupplierId !== $supplierIdRequested) {
        header("Location: ?page=403");
        exit;
    }
}

$stmt = $db->pdo()->prepare("
  SELECT *
  FROM suppliers
  WHERE id_supplier = ?
  LIMIT 1
");
$stmt->execute([$supplierIdRequested]);
$supplier = $stmt->fetch();

if (!$supplier) {
    header("Location: ?page=404");
    exit;
}
?>

<h1>Supplier #<?= (int)$supplier["id_supplier"] ?></h1>

<table border="1" cellpadding="6" cellspacing="0">
    <tbody>
        <?php foreach ($supplier as $k => $v): ?>
            <tr>
                <th><?= h((string)$k) ?></th>
                <td><?= h((string)$v) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
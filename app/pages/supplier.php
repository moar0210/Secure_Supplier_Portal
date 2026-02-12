<?php
$id = $_GET["id"] ?? null;
if (!$id || !ctype_digit($id)) {
    echo "<p>Invalid supplier id.</p>";
    return;
}

$stmt = $db->pdo()->prepare("
  SELECT *
  FROM suppliers
  WHERE id_supplier = ?
  LIMIT 1
");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    echo "<p>Supplier not found.</p>";
    return;
}
?>

<h1>Supplier #<?= htmlspecialchars($supplier["id_supplier"]) ?></h1>

<table border="1" cellpadding="6" cellspacing="0">
    <tbody>
        <?php foreach ($supplier as $k => $v): ?>
            <tr>
                <th><?= htmlspecialchars($k) ?></th>
                <td><?= htmlspecialchars((string)$v) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
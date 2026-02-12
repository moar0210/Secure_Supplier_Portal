<?php
$stmt = $db->pdo()->prepare("
  SELECT id_supplier, supplier_name, short_name, email, homepage, is_inactive
  FROM suppliers
  ORDER BY id_supplier ASC
  LIMIT 50
");
$stmt->execute();
$rows = $stmt->fetchAll();
?>

<h1>Suppliers</h1>

<?php if (!$rows): ?>
    <p>No suppliers found.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier Name</th>
                <th>Short Name</th>
                <th>Email</th>
                <th>Homepage</th>
                <th>Inactive?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r["id_supplier"]) ?></td>
                    <td>
                        <a href="?page=supplier&id=<?= htmlspecialchars($r["id_supplier"]) ?>">
                            <?= htmlspecialchars($r["supplier_name"]) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($r["short_name"]) ?></td>
                    <td><?= htmlspecialchars($r["email"]) ?></td>
                    <td><?= htmlspecialchars($r["homepage"]) ?></td>
                    <td><?= htmlspecialchars($r["is_inactive"]) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
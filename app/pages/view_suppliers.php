<h1>Suppliers</h1>

<?php if (!$rows): ?>
    <p>No suppliers found.</p>
<?php else: ?>
    <table>
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
            <?php foreach ($rows as $row): ?>
                <?php $sid = (int)$row['id_supplier']; ?>
                <tr>
                    <td><?= $sid ?></td>
                    <td>
                        <a href="?page=supplier&id=<?= $sid ?>">
                            <?= h((string)$row['supplier_name']) ?>
                        </a>
                    </td>
                    <td><?= h((string)$row['short_name']) ?></td>
                    <td><?= h((string)$row['email']) ?></td>
                    <td><?= h((string)$row['homepage']) ?></td>
                    <td><?= h((string)$row['is_inactive']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

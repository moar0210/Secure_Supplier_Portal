<div class="page-header">
    <h1>Suppliers</h1>
    <div class="page-header__actions">
        <a href="?page=supplier_create"><button type="button">+ Create supplier</button></a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No suppliers found.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier name</th>
                <th>Short name</th>
                <th>Email</th>
                <th>Homepage</th>
                <th>Status</th>
                <th>Users</th>
                <th>Ads</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $sid = (int)$row['id_supplier'];
                $isInactive = !empty($row['is_inactive']);
                ?>
                <tr>
                    <td><?= $sid ?></td>
                    <td>
                        <a href="?page=supplier&amp;id=<?= $sid ?>">
                            <?= h((string)$row['supplier_name']) ?>
                        </a>
                    </td>
                    <td><?= h((string)$row['short_name']) ?></td>
                    <td><?= h((string)$row['email']) ?></td>
                    <td><?= h((string)$row['homepage']) ?></td>
                    <td>
                        <span class="badge <?= $isInactive ? 'badge--pending' : 'badge--approved' ?>">
                            <?= $isInactive ? 'Inactive' : 'Active' ?>
                        </span>
                    </td>
                    <td><?= (int)($row['portal_user_count'] ?? 0) ?></td>
                    <td><?= (int)($row['ad_count'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

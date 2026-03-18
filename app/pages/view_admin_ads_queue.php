<h1>Admin - Ads Queue</h1>

<p>
    Filter:
    <?php foreach ($allowed as $allowedStatus): ?>
        <a href="?page=admin_ads_queue&status=<?= h($allowedStatus) ?>"
           style="margin-right:10px;<?= $allowedStatus === $status ? 'font-weight:bold;text-decoration:underline;' : '' ?>">
            <?= h($allowedStatus) ?>
        </a>
    <?php endforeach; ?>
</p>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if (!$rows): ?>
    <p>No ads found for this filter.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Supplier</th>
                <th>Category</th>
                <th>Title</th>
                <th>Status</th>
                <th>Active</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= h((string)($row['supplier_name'] ?? '')) ?> (#<?= (int)$row['supplier_id'] ?>)</td>
                    <td><?= h((string)($row['category_name'] ?? '-')) ?></td>
                    <td><?= h((string)$row['title']) ?></td>
                    <td><?= h((string)$row['status']) ?></td>
                    <td><?= !empty($row['is_active']) ? 'yes' : 'no' ?></td>
                    <td><?= h((string)$row['updated_at']) ?></td>
                    <td>
                        <a href="?page=admin_ad_review&id=<?= (int)$row['id'] ?>">Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

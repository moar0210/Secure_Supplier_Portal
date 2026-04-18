<div class="page-header">
    <h1>Ads queue</h1>
</div>

<div class="tag-filter">
    <?php foreach ($allowed as $allowedStatus): ?>
        <a href="?page=admin_ads_queue&amp;status=<?= h($allowedStatus) ?>" class="<?= $allowedStatus === $status ? 'is-active' : '' ?>">
            <?= h($allowedStatus) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No ads found for this filter.</p></div>
<?php else: ?>
    <?php
    $badge = static function (string $status): string {
        $status = strtoupper($status);
        $cls = match ($status) {
            'APPROVED' => 'badge--approved',
            'PENDING' => 'badge--pending',
            'REJECTED' => 'badge--rejected',
            default => 'badge--draft',
        };
        return '<span class="badge ' . $cls . '">' . h($status) . '</span>';
    };
    ?>
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
                    <td><?= $badge((string)$row['status']) ?></td>
                    <td><?= !empty($row['is_active']) ? 'Yes' : 'No' ?></td>
                    <td class="muted small"><?= h((string)$row['updated_at']) ?></td>
                    <td>
                        <a href="?page=admin_ad_review&amp;id=<?= (int)$row['id'] ?>">Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

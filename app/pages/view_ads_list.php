<?php
$badge = static function (string $status): string {
    $status = strtoupper($status);

    return match ($status) {
        'APPROVED' => '<span style="padding:2px 6px;border:1px solid #080;background:#efe;">APPROVED</span>',
        'PENDING' => '<span style="padding:2px 6px;border:1px solid #aa0;background:#fffbdd;">PENDING</span>',
        'REJECTED' => '<span style="padding:2px 6px;border:1px solid #a00;background:#fee;">REJECTED</span>',
        default => '<span style="padding:2px 6px;border:1px solid #888;background:#f6f6f6;">DRAFT</span>',
    };
};
?>
<h1>My Ads</h1>

<?php if ($deleted): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        Advertisement deleted successfully.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<p>
    <a href="?page=ad_create">+ Create new ad</a>
</p>

<?php if (!$rows): ?>
    <p>No ads yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Title</th>
                <th>Price Model</th>
                <th>Status</th>
                <th>Active</th>
                <th>Updated</th>
                <th style="width:260px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $id = (int)$row['id'];
                $status = (string)$row['status'];
                $isActive = !empty($row['is_active']);
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><?= h((string)($row['category_name'] ?? '-')) ?></td>
                    <td><?= h((string)$row['title']) ?></td>
                    <td><?= h(AdsService::priceModelLabel((string)($row['price_model_type'] ?? '')) ?: '-') ?></td>
                    <td><?= $badge($status) ?></td>
                    <td><?= $isActive ? 'yes' : 'no' ?></td>
                    <td><?= h((string)$row['updated_at']) ?></td>
                    <td>
                        <a href="?page=ad_edit&id=<?= $id ?>">Edit / View</a>

                        <?php if (strtoupper($status) === 'APPROVED'): ?>
                            <form method="post" action="?page=ad_toggle" style="display:inline;">
                                <?= Csrf::input(); ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                                <button type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        <?php elseif (in_array(strtoupper($status), ['DRAFT', 'REJECTED'], true)): ?>
                            <form method="post" action="?page=ad_edit&id=<?= $id ?>" style="display:inline;">
                                <?= Csrf::input(); ?>
                                <input type="hidden" name="action" value="submit">
                                <button type="submit">Submit</button>
                            </form>
                            <form method="post" action="?page=ad_delete" style="display:inline;" data-confirm="Delete this ad?">
                                <?= Csrf::input(); ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit">Delete</button>
                            </form>
                        <?php else: ?>
                            <span style="opacity:.8;">(Waiting review)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

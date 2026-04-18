<?php
$badge = static function (string $status): string {
    $status = strtoupper($status);
    $cls = match ($status) {
        'APPROVED' => 'badge--approved',
        'PENDING' => 'badge--pending',
        'REJECTED' => 'badge--rejected',
        default => 'badge--draft',
    };
    return '<span class="badge ' . $cls . '">' . h($status ?: 'DRAFT') . '</span>';
};
?>
<div class="page-header">
    <h1>My ads</h1>
    <div class="page-header__actions">
        <a href="?page=ad_create"><button type="button">+ Create ad</button></a>
    </div>
</div>

<?php if ($deleted): ?>
    <div class="alert alert--success">Advertisement deleted successfully.</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No ads yet.</p>
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Title</th>
                <th>Price model</th>
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
                    <td><?= $isActive ? 'Yes' : 'No' ?></td>
                    <td class="muted small"><?= h((string)$row['updated_at']) ?></td>
                    <td>
                        <div class="actions-inline">
                            <a href="?page=ad_edit&amp;id=<?= $id ?>" class="muted small">Edit</a>

                            <?php if (strtoupper($status) === 'APPROVED'): ?>
                                <form method="post" action="?page=ad_toggle" class="inline-form">
                                    <?= Csrf::input(); ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="active" value="<?= $isActive ? 0 : 1 ?>">
                                    <button type="submit" class="btn-secondary"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            <?php elseif (in_array(strtoupper($status), ['DRAFT', 'REJECTED'], true)): ?>
                                <form method="post" action="?page=ad_edit&amp;id=<?= $id ?>" class="inline-form">
                                    <?= Csrf::input(); ?>
                                    <input type="hidden" name="action" value="submit">
                                    <button type="submit" class="btn-secondary">Submit</button>
                                </form>
                                <form method="post" action="?page=ad_delete" class="inline-form" data-confirm="Delete this ad?">
                                    <?= Csrf::input(); ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="muted small">(Waiting review)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

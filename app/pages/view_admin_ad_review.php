<h1>Admin - Review Ad #<?= (int)$ad['id'] ?></h1>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<table>
    <tbody>
        <tr>
            <th>Supplier</th>
            <td><?= h((string)($ad['supplier_name'] ?? '')) ?> (#<?= (int)$ad['supplier_id'] ?>)</td>
        </tr>
        <tr>
            <th>Category</th>
            <td><?= h((string)($ad['category_name'] ?? '-')) ?></td>
        </tr>
        <tr>
            <th>Title</th>
            <td><?= h((string)$ad['title']) ?></td>
        </tr>
        <tr>
            <th>Description</th>
            <td><?= nl2br(h((string)$ad['description'])) ?></td>
        </tr>
        <tr>
            <th>Price</th>
            <td><?= h((string)($ad['price_text'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Validity</th>
            <td><?= h((string)($ad['valid_from'] ?? '')) ?> -> <?= h((string)($ad['valid_to'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><strong><?= h((string)$ad['status']) ?></strong></td>
        </tr>
        <tr>
            <th>Active</th>
            <td><?= !empty($ad['is_active']) ? 'yes' : 'no' ?></td>
        </tr>
        <tr>
            <th>Rejection reason</th>
            <td><?= h((string)($ad['rejection_reason'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Updated</th>
            <td><?= h((string)$ad['updated_at']) ?></td>
        </tr>
    </tbody>
</table>

<?php if ((string)$ad['status'] === 'PENDING'): ?>
    <h2>Decision</h2>

    <p style="opacity:.85;">Approving marks the ad as APPROVED. The supplier must activate it separately.</p>

    <form method="post" style="margin-bottom:10px;">
        <?= Csrf::input(); ?>
        <input type="hidden" name="decision" value="approve">
        <button type="submit">Approve</button>
    </form>

    <form method="post">
        <?= Csrf::input(); ?>
        <input type="hidden" name="decision" value="reject">
        <div>
            <label>Rejection reason (required)</label><br>
            <input name="reason" required maxlength="500" style="width:420px;">
        </div>
        <button type="submit">Reject</button>
    </form>
<?php else: ?>
    <p><em>This ad is not PENDING. Only PENDING ads can be approved/rejected.</em></p>
<?php endif; ?>

<h2>Status history</h2>
<?php if (!$history): ?>
    <p>No history rows found.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>From</th>
                <th>To</th>
                <th>By</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?= h((string)$entry['changed_at']) ?></td>
                    <td><?= h((string)($entry['old_status'] ?? '')) ?></td>
                    <td><?= h((string)$entry['new_status']) ?></td>
                    <td><?= h((string)($entry['username'] ?? '')) ?></td>
                    <td><?= h((string)($entry['reason'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

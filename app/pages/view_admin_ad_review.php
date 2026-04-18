<?php
$badgeClass = match (strtoupper((string)$ad['status'])) {
    'APPROVED' => 'badge--approved',
    'PENDING' => 'badge--pending',
    'REJECTED' => 'badge--rejected',
    default => 'badge--draft',
};
?>
<div class="page-header">
    <h1>Review ad #<?= (int)$ad['id'] ?></h1>
    <div class="page-header__actions">
        <a href="?page=admin_ads_queue" class="muted small">&larr; Back to queue</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
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
            <th>Price model</th>
            <td><?= h(AdsService::priceModelLabel((string)($ad['price_model_type'] ?? '')) ?: '-') ?></td>
        </tr>
        <tr>
            <th>Offer details</th>
            <td><?= h((string)($ad['price_text'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Validity</th>
            <td><?= h((string)($ad['valid_from'] ?? '')) ?> &rarr; <?= h((string)($ad['valid_to'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="badge <?= $badgeClass ?>"><?= h((string)$ad['status']) ?></span></td>
        </tr>
        <tr>
            <th>Active</th>
            <td><?= !empty($ad['is_active']) ? 'Yes' : 'No' ?></td>
        </tr>
        <tr>
            <th>Rejection reason</th>
            <td><?= h((string)($ad['rejection_reason'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Updated</th>
            <td class="muted small"><?= h((string)$ad['updated_at']) ?></td>
        </tr>
    </tbody>
</table>

<?php if ((string)$ad['status'] === 'PENDING'): ?>
    <h2>Decision</h2>

    <p class="muted small">Approving marks the ad as APPROVED. The supplier must activate it separately.</p>

    <form method="post" class="mb-3">
        <?= Csrf::input(); ?>
        <input type="hidden" name="decision" value="approve">
        <button type="submit">Approve</button>
    </form>

    <form method="post" class="card">
        <?= Csrf::input(); ?>
        <input type="hidden" name="decision" value="reject">
        <div class="field">
            <label>Rejection reason (required)</label>
            <input name="reason" required maxlength="500">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-danger">Reject</button>
        </div>
    </form>
<?php else: ?>
    <p class="muted small"><em>This ad is not PENDING. Only PENDING ads can be approved/rejected.</em></p>
<?php endif; ?>

<h2>Status history</h2>
<?php if (!$history): ?>
    <div class="card card--muted"><p class="mb-0 muted">No history rows found.</p></div>
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
                    <td class="muted small"><?= h((string)$entry['changed_at']) ?></td>
                    <td><?= h((string)($entry['old_status'] ?? '')) ?></td>
                    <td><?= h((string)$entry['new_status']) ?></td>
                    <td><?= h((string)($entry['username'] ?? '')) ?></td>
                    <td><?= h((string)($entry['reason'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

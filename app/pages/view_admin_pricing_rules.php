<?php
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<div class="page-header">
    <h1>Pricing rules</h1>
</div>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h($notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<div class="alert alert--info">
    Rule selection: if multiple active rules overlap a billing month, the most recent <code>effective_from</code> wins.
    Monthly invoices can combine subscription, advertisement usage, and optional service-fee lines from the selected rule.
</div>

<h2>Create rule</h2>
<form method="post" class="card mb-5">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <div class="field-row">
        <div class="field">
            <label>Name</label>
            <input name="name" required maxlength="100">
        </div>
        <div class="field">
            <label>Price per ad</label>
            <input type="number" name="price_per_ad" min="0" step="0.01" value="500.00" required>
        </div>
        <div class="field">
            <label>Subscription fee</label>
            <input type="number" name="subscription_fee" min="0" step="0.01" value="0.00">
        </div>
        <div class="field">
            <label>Optional service fee</label>
            <input type="number" name="optional_service_fee" min="0" step="0.01" value="0.00">
        </div>
    </div>
    <div class="field-row mt-3">
        <div class="field">
            <label>VAT rate</label>
            <input type="number" name="vat_rate" min="0" max="100" step="0.01" value="25.00" required>
        </div>
        <div class="field">
            <label>Currency</label>
            <input name="currency_code" value="SEK" maxlength="3">
        </div>
        <div class="field">
            <label>Effective from</label>
            <input type="date" name="effective_from">
        </div>
        <div class="field">
            <label>Effective to</label>
            <input type="date" name="effective_to">
        </div>
    </div>
    <div class="field-row mt-3">
        <div class="field">
            <label>Description</label>
            <input name="description" maxlength="255">
        </div>
        <div class="field">
            <label>Service fee label</label>
            <input name="service_fee_label" maxlength="120" placeholder="Optional onboarding support">
        </div>
    </div>
    <div class="mt-3">
        <input type="hidden" name="is_active" value="0">
        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
    </div>
    <div class="form-actions">
        <button type="submit">Create rule</button>
    </div>
</form>

<h2>Existing rules</h2>
<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No pricing rules found.</p></div>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <form method="post" class="card mb-3">
            <?= Csrf::input(); ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div class="field-row">
                <div class="field">
                    <label>Name</label>
                    <input name="name" required maxlength="100" value="<?= h((string)$row['name']) ?>">
                </div>
                <div class="field">
                    <label>Price per ad</label>
                    <input type="number" name="price_per_ad" min="0" step="0.01" value="<?= h($money($row['price_per_ad'])) ?>">
                </div>
                <div class="field">
                    <label>Subscription fee</label>
                    <input type="number" name="subscription_fee" min="0" step="0.01" value="<?= h($money($row['subscription_fee'] ?? 0)) ?>">
                </div>
                <div class="field">
                    <label>Optional service fee</label>
                    <input type="number" name="optional_service_fee" min="0" step="0.01" value="<?= h($money($row['optional_service_fee'] ?? 0)) ?>">
                </div>
            </div>
            <div class="field-row mt-3">
                <div class="field">
                    <label>VAT rate</label>
                    <input type="number" name="vat_rate" min="0" max="100" step="0.01" value="<?= h($money($row['vat_rate'])) ?>">
                </div>
                <div class="field">
                    <label>Currency</label>
                    <input name="currency_code" value="<?= h((string)$row['currency_code']) ?>" maxlength="3">
                </div>
                <div class="field">
                    <label>Effective from</label>
                    <input type="date" name="effective_from" value="<?= h((string)($row['effective_from'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label>Effective to</label>
                    <input type="date" name="effective_to" value="<?= h((string)($row['effective_to'] ?? '')) ?>">
                </div>
            </div>
            <div class="field-row mt-3">
                <div class="field">
                    <label>Description</label>
                    <input name="description" maxlength="255" value="<?= h((string)($row['description'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label>Service fee label</label>
                    <input name="service_fee_label" maxlength="120" value="<?= h((string)($row['service_fee_label'] ?? '')) ?>">
                </div>
            </div>
            <div class="mt-3">
                <input type="hidden" name="is_active" value="0">
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Active</label>
            </div>
            <div class="form-actions">
                <button type="submit" name="action" value="update">Save changes</button>
                <button type="submit" name="action" value="deactivate" class="btn-danger" data-confirm="Deactivate this pricing rule?">Deactivate</button>
            </div>
            <p class="muted small mt-3 mb-0">
                ID <?= (int)$row['id'] ?> &middot; created <?= h((string)$row['created_at']) ?> &middot; updated <?= h((string)$row['updated_at']) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

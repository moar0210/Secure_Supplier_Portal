<?php
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<h1>Admin - Pricing Rules</h1>

<?php if ($notice): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        <?= h($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<p style="opacity:.85;">
    Rule selection assumption: if multiple active rules overlap a billing month, the most recent `effective_from` wins.
    Monthly invoices can combine subscription, advertisement usage, and optional service-fee lines from the selected rule.
</p>

<h2>Create Rule</h2>
<form method="post" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div>
            <label>Name</label><br>
            <input name="name" required maxlength="100">
        </div>
        <div>
            <label>Price per ad</label><br>
            <input type="number" name="price_per_ad" min="0" step="0.01" value="500.00" required>
        </div>
        <div>
            <label>Subscription fee</label><br>
            <input type="number" name="subscription_fee" min="0" step="0.01" value="0.00">
        </div>
        <div>
            <label>Optional service fee</label><br>
            <input type="number" name="optional_service_fee" min="0" step="0.01" value="0.00">
        </div>
        <div>
            <label>VAT rate</label><br>
            <input type="number" name="vat_rate" min="0" max="100" step="0.01" value="25.00" required>
        </div>
        <div>
            <label>Currency</label><br>
            <input name="currency_code" value="SEK" maxlength="3">
        </div>
        <div>
            <label>Effective from</label><br>
            <input type="date" name="effective_from">
        </div>
        <div>
            <label>Effective to</label><br>
            <input type="date" name="effective_to">
        </div>
    </div>
    <div style="margin-top:10px;">
        <label>Description</label><br>
        <input name="description" maxlength="255" style="width:520px;">
    </div>
    <div style="margin-top:10px;">
        <label>Service fee label</label><br>
        <input name="service_fee_label" maxlength="120" style="width:520px;" placeholder="Optional onboarding support">
    </div>
    <div style="margin-top:10px;">
        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
    </div>
    <button type="submit" style="margin-top:10px;">Create rule</button>
</form>

<h2>Existing Rules</h2>
<?php if (!$rows): ?>
    <p>No pricing rules found.</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <form method="post" style="padding:12px;border:1px solid #ccc;margin-bottom:12px;">
            <?= Csrf::input(); ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <div>
                    <label>Name</label><br>
                    <input name="name" required maxlength="100" value="<?= h((string)$row['name']) ?>">
                </div>
                <div>
                    <label>Price per ad</label><br>
                    <input type="number" name="price_per_ad" min="0" step="0.01" value="<?= h($money($row['price_per_ad'])) ?>">
                </div>
                <div>
                    <label>Subscription fee</label><br>
                    <input type="number" name="subscription_fee" min="0" step="0.01" value="<?= h($money($row['subscription_fee'] ?? 0)) ?>">
                </div>
                <div>
                    <label>Optional service fee</label><br>
                    <input type="number" name="optional_service_fee" min="0" step="0.01" value="<?= h($money($row['optional_service_fee'] ?? 0)) ?>">
                </div>
                <div>
                    <label>VAT rate</label><br>
                    <input type="number" name="vat_rate" min="0" max="100" step="0.01" value="<?= h($money($row['vat_rate'])) ?>">
                </div>
                <div>
                    <label>Currency</label><br>
                    <input name="currency_code" value="<?= h((string)$row['currency_code']) ?>" maxlength="3">
                </div>
                <div>
                    <label>Effective from</label><br>
                    <input type="date" name="effective_from" value="<?= h((string)($row['effective_from'] ?? '')) ?>">
                </div>
                <div>
                    <label>Effective to</label><br>
                    <input type="date" name="effective_to" value="<?= h((string)($row['effective_to'] ?? '')) ?>">
                </div>
            </div>
            <div style="margin-top:10px;">
                <label>Description</label><br>
                <input name="description" maxlength="255" value="<?= h((string)($row['description'] ?? '')) ?>" style="width:520px;">
            </div>
            <div style="margin-top:10px;">
                <label>Service fee label</label><br>
                <input name="service_fee_label" maxlength="120" value="<?= h((string)($row['service_fee_label'] ?? '')) ?>" style="width:520px;">
            </div>
            <div style="margin-top:10px;">
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Active</label>
            </div>
            <div style="margin-top:10px;">
                <button type="submit" name="action" value="update">Save changes</button>
                <button type="submit" name="action" value="deactivate" data-confirm="Deactivate this pricing rule?">Deactivate</button>
            </div>
            <p style="margin:10px 0 0;opacity:.8;">
                ID <?= (int)$row['id'] ?>, created <?= h((string)$row['created_at']) ?>, updated <?= h((string)$row['updated_at']) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

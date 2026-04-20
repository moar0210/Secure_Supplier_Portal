<?php
$money = static fn(mixed $value): string => number_format((float)$value, 2);
?>
<div class="page-header">
    <div class="page-header__title-group">
        <h1>Pricing rules</h1>
        <p class="page-header__subtitle">Maintain pricing and VAT rules used by the monthly invoice generation workflow.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" data-collapsible-target="createPricingPanel" aria-expanded="false">
            + Create rule
        </button>
    </div>
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

<div class="collapsible" id="createPricingPanel">
    <div class="modal__header">
        <h2 class="modal__title">Create pricing rule</h2>
        <button type="button" class="modal__close" data-collapsible-close="createPricingPanel" aria-label="Close">&times;</button>
    </div>
    <div class="collapsible__body">
        <form method="post">
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
                <button type="button" class="btn-secondary" data-collapsible-close="createPricingPanel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No pricing rules found.</p></div>
<?php else: ?>
    <div class="table-card mb-5">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Name</th>
                    <th class="text-right">Price / ad</th>
                    <th class="text-right">Subscription</th>
                    <th class="text-right">VAT</th>
                    <th>Currency</th>
                    <th>Effective</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $rowId = 'rule-' . (int)$row['id'];
                    $isActive = !empty($row['is_active']);
                    $effectiveFrom = (string)($row['effective_from'] ?? '');
                    $effectiveTo = (string)($row['effective_to'] ?? '');
                    ?>
                    <tr id="<?= h($rowId) ?>">
                        <td class="muted"><?= (int)$row['id'] ?></td>
                        <td><strong><?= h((string)$row['name']) ?></strong>
                            <?php if (!empty($row['description'])): ?>
                                <div class="muted small"><?= h((string)$row['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= h($money($row['price_per_ad'])) ?></td>
                        <td class="text-right"><?= h($money($row['subscription_fee'] ?? 0)) ?></td>
                        <td class="text-right"><?= h($money($row['vat_rate'])) ?>%</td>
                        <td><?= h((string)$row['currency_code']) ?></td>
                        <td class="muted small">
                            <?= h($effectiveFrom !== '' ? $effectiveFrom : '—') ?>
                            <?php if ($effectiveTo !== ''): ?>
                                → <?= h($effectiveTo) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $isActive ? 'badge--active' : 'badge--inactive' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn-ghost btn-icon"
                                data-collapsible-target="<?= h('edit-' . $rowId) ?>"
                                aria-expanded="false"
                                title="Edit rule">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9" style="padding:0;background:var(--color-surface-subtle);">
                            <div class="collapsible" id="<?= h('edit-' . $rowId) ?>" style="margin:0;border:0;border-radius:0;border-top:1px solid var(--color-border);background:transparent;">
                                <div class="collapsible__body" style="border-top:0;">
                                    <form method="post">
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
                                                <input type="date" name="effective_from" value="<?= h($effectiveFrom) ?>">
                                            </div>
                                            <div class="field">
                                                <label>Effective to</label>
                                                <input type="date" name="effective_to" value="<?= h($effectiveTo) ?>">
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
                                            <label><input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>> Active</label>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="action" value="update">Save changes</button>
                                            <button type="submit" name="action" value="deactivate" class="btn-danger" data-confirm="Deactivate this pricing rule?">Deactivate</button>
                                            <button type="button" class="btn-secondary" data-collapsible-close="<?= h('edit-' . $rowId) ?>">Cancel</button>
                                        </div>
                                        <p class="muted small mt-3 mb-0">
                                            Created <?= h((string)$row['created_at']) ?> &middot; updated <?= h((string)$row['updated_at']) ?>
                                        </p>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

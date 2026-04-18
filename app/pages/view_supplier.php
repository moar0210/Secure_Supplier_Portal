<div class="page-header">
    <h1>Supplier profile</h1>
</div>

<?php if ($created): ?>
    <div class="alert alert--success">Supplier created successfully.</div>
<?php endif; ?>

<?php if ($updated): ?>
    <div class="alert alert--success">Supplier profile updated successfully.</div>
<?php endif; ?>

<?php if (!empty($notice)): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card card--muted mb-5">
    <strong>Supplier status:</strong>
    <?php $isInactive = !empty($supplier['is_inactive']); ?>
    <span class="badge <?= $isInactive ? 'badge--pending' : 'badge--approved' ?>">
        <?= $isInactive ? 'Inactive / pending approval' : 'Active / approved' ?>
    </span>

    <?php if (!empty($isAdmin)): ?>
        <form method="post" action="?page=supplier_status" class="inline-form" style="margin-left:12px;">
            <?= Csrf::input(); ?>
            <input type="hidden" name="id" value="<?= (int)$supplier['id_supplier'] ?>">
            <input type="hidden" name="active" value="<?= $isInactive ? '1' : '0' ?>">
            <button type="submit" class="btn-secondary">
                <?= $isInactive ? 'Approve / Activate supplier' : 'Deactivate supplier' ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($isAdmin)): ?>
    <p class="muted small">
        <a href="?page=supplier_users">Company users</a>
        &middot;
        <a href="?page=supplier_stats">Statistics</a>
    </p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" autocomplete="off" class="form-stack">
    <?= Csrf::input(); ?>

    <div class="field">
        <label>Company name</label>
        <input name="company_name" required maxlength="100" value="<?= h((string)$supplier['company_name']) ?>">
    </div>

    <div class="field">
        <label>Short name</label>
        <input name="short_name" maxlength="100" value="<?= h((string)$supplier['short_name']) ?>">
    </div>

    <div class="field">
        <label>Contact person</label>
        <input name="contact_person" required maxlength="100" value="<?= h((string)$supplier['contact_person']) ?>">
    </div>

    <div class="field">
        <label>Email</label>
        <input name="email" type="email" required maxlength="100" value="<?= h((string)$supplier['email']) ?>">
    </div>

    <div class="field">
        <label>Homepage</label>
        <input name="homepage" type="url" maxlength="100" value="<?= h((string)$supplier['homepage']) ?>">
    </div>

    <div class="field">
        <label>VAT / tax number</label>
        <input name="vat_number" maxlength="50" value="<?= h((string)$supplier['vat_number']) ?>">
    </div>

    <fieldset>
        <legend>Logo</legend>
        <?php if (!empty($logo)): ?>
            <div class="mb-3">
                <img
                    src="?page=supplier_logo&amp;id=<?= (int)$supplier['id_supplier'] ?>&amp;v=<?= rawurlencode((string)($logo['updated_at'] ?? '')) ?>"
                    alt="Current supplier logo"
                    class="logo-preview">
                <div class="muted small mt-3">
                    Current file: <?= h((string)($logo['original_filename'] ?? 'supplier-logo')) ?>
                    (<?= number_format(((int)($logo['file_size'] ?? 0)) / 1024, 1) ?> KB)
                </div>
                <label class="mt-3" style="display:block;">
                    <input type="checkbox" name="remove_logo" value="1">
                    Remove current logo
                </label>
            </div>
        <?php else: ?>
            <p class="muted small">No logo uploaded yet.</p>
        <?php endif; ?>

        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        <p class="muted small mt-3 mb-0">
            PNG, JPG, or WebP up to 2 MB. Uploaded files are validated and stored outside the web root.
        </p>
    </fieldset>

    <div class="field">
        <label>Address line 1</label>
        <input name="address_line_1" required maxlength="100" value="<?= h((string)$supplier['address_line_1']) ?>">
    </div>

    <div class="field">
        <label>Address line 2</label>
        <input name="address_line_2" maxlength="50" value="<?= h((string)$supplier['address_line_2']) ?>">
    </div>

    <div class="field">
        <label>City</label>
        <input name="city" required maxlength="30" value="<?= h((string)$supplier['city']) ?>">
    </div>

    <div class="field">
        <label>Region / state</label>
        <input name="region" maxlength="30" value="<?= h((string)$supplier['region']) ?>">
    </div>

    <div class="field">
        <label>Postal code</label>
        <input name="postal_code" maxlength="10" value="<?= h((string)$supplier['postal_code']) ?>">
    </div>

    <div class="field">
        <label>Country code (ISO-2)</label>
        <input name="country_code" required maxlength="2" pattern="[A-Za-z]{2}" style="width:120px;" value="<?= h((string)$supplier['country_code']) ?>">
    </div>

    <fieldset>
        <legend>Phone</legend>
        <div class="field-row">
            <div class="field">
                <label>Country prefix</label>
                <input name="phone_country_prefix" inputmode="numeric" maxlength="4" style="width:120px;" value="<?= h((string)$supplier['phone_country_prefix']) ?>">
            </div>
            <div class="field">
                <label>Area code</label>
                <input name="phone_area_code" inputmode="numeric" maxlength="6" style="width:120px;" value="<?= h((string)$supplier['phone_area_code']) ?>">
            </div>
            <div class="field">
                <label>Phone number</label>
                <input name="phone_number" inputmode="numeric" maxlength="20" style="width:220px;" value="<?= h((string)$supplier['phone_number']) ?>">
            </div>
        </div>
        <?php if (!empty($supplier['phone_display'])): ?>
            <p class="muted small mt-3 mb-0">Current formatted phone: <?= h((string)$supplier['phone_display']) ?></p>
        <?php endif; ?>
    </fieldset>

    <div class="form-actions">
        <button type="submit">Save profile</button>
    </div>
</form>

<div class="page-header">
    <h1>Create supplier</h1>
    <div class="page-header__actions">
        <a href="?page=suppliers" class="muted small">&larr; Back to suppliers</a>
    </div>
</div>

<p class="muted">New suppliers are created as inactive so an administrator can review and approve them before they go live.</p>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" autocomplete="off" class="form-stack">
    <?= Csrf::input(); ?>

    <div class="field">
        <label>Company name</label>
        <input name="company_name" required maxlength="100" value="<?= h((string)$form['company_name']) ?>">
    </div>

    <div class="field">
        <label>Short name</label>
        <input name="short_name" maxlength="100" value="<?= h((string)$form['short_name']) ?>">
    </div>

    <div class="field">
        <label>Contact person</label>
        <input name="contact_person" required maxlength="100" value="<?= h((string)$form['contact_person']) ?>">
    </div>

    <div class="field">
        <label>Email</label>
        <input name="email" type="email" required maxlength="100" value="<?= h((string)$form['email']) ?>">
    </div>

    <div class="field">
        <label>Homepage</label>
        <input name="homepage" type="url" maxlength="100" value="<?= h((string)$form['homepage']) ?>">
    </div>

    <div class="field">
        <label>VAT / tax number</label>
        <input name="vat_number" maxlength="50" value="<?= h((string)$form['vat_number']) ?>">
    </div>

    <fieldset>
        <legend>Logo</legend>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        <p class="muted small mt-3 mb-0">
            PNG, JPG, or WebP up to 2 MB. Uploaded files are validated and stored outside the web root.
        </p>
    </fieldset>

    <div class="field">
        <label>Address line 1</label>
        <input name="address_line_1" required maxlength="100" value="<?= h((string)$form['address_line_1']) ?>">
    </div>

    <div class="field">
        <label>Address line 2</label>
        <input name="address_line_2" maxlength="50" value="<?= h((string)$form['address_line_2']) ?>">
    </div>

    <div class="field">
        <label>City</label>
        <input name="city" required maxlength="30" value="<?= h((string)$form['city']) ?>">
    </div>

    <div class="field">
        <label>Region / state</label>
        <input name="region" maxlength="30" value="<?= h((string)$form['region']) ?>">
    </div>

    <div class="field">
        <label>Postal code</label>
        <input name="postal_code" maxlength="10" value="<?= h((string)$form['postal_code']) ?>">
    </div>

    <div class="field">
        <label>Country code (ISO-2)</label>
        <input name="country_code" required maxlength="2" pattern="[A-Za-z]{2}" style="width:120px;" value="<?= h((string)$form['country_code']) ?>">
    </div>

    <fieldset>
        <legend>Phone</legend>
        <div class="field-row">
            <div class="field">
                <label>Country prefix</label>
                <input name="phone_country_prefix" inputmode="numeric" maxlength="4" style="width:120px;" value="<?= h((string)$form['phone_country_prefix']) ?>">
            </div>
            <div class="field">
                <label>Area code</label>
                <input name="phone_area_code" inputmode="numeric" maxlength="6" style="width:120px;" value="<?= h((string)$form['phone_area_code']) ?>">
            </div>
            <div class="field">
                <label>Phone number</label>
                <input name="phone_number" inputmode="numeric" maxlength="20" style="width:220px;" value="<?= h((string)$form['phone_number']) ?>">
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
        <button type="submit">Create supplier</button>
        <a href="?page=suppliers" class="muted small">Cancel</a>
    </div>
</form>

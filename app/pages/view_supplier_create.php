<h1>Create Supplier</h1>

<p>New suppliers are created as inactive so an administrator can review and approve them before they go live.</p>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h((string)$error) ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" autocomplete="off">
    <?= Csrf::input(); ?>

    <div style="margin:10px 0;">
        <label>Company name</label><br>
        <input name="company_name" required maxlength="100" style="width:520px;" value="<?= h((string)$form['company_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Short name</label><br>
        <input name="short_name" maxlength="100" style="width:520px;" value="<?= h((string)$form['short_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Contact person</label><br>
        <input name="contact_person" required maxlength="100" style="width:520px;" value="<?= h((string)$form['contact_person']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Email</label><br>
        <input name="email" type="email" required maxlength="100" style="width:520px;" value="<?= h((string)$form['email']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Homepage</label><br>
        <input name="homepage" type="url" maxlength="100" style="width:520px;" value="<?= h((string)$form['homepage']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>VAT / tax number</label><br>
        <input name="vat_number" maxlength="50" style="width:520px;" value="<?= h((string)$form['vat_number']) ?>">
    </div>

    <div style="margin:10px 0;">
        <strong>Logo upload</strong><br>
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        <div style="margin-top:6px; opacity:.85;">
            PNG, JPG, or WebP up to 2 MB. Uploaded files are validated and stored outside the web root.
        </div>
    </div>

    <div style="margin:10px 0;">
        <label>Address line 1</label><br>
        <input name="address_line_1" required maxlength="100" style="width:520px;" value="<?= h((string)$form['address_line_1']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Address line 2</label><br>
        <input name="address_line_2" maxlength="50" style="width:520px;" value="<?= h((string)$form['address_line_2']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>City</label><br>
        <input name="city" required maxlength="30" style="width:520px;" value="<?= h((string)$form['city']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Region / state</label><br>
        <input name="region" maxlength="30" style="width:520px;" value="<?= h((string)$form['region']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Postal code</label><br>
        <input name="postal_code" maxlength="10" style="width:520px;" value="<?= h((string)$form['postal_code']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Country code (ISO-2)</label><br>
        <input name="country_code" required maxlength="2" pattern="[A-Za-z]{2}" style="width:120px;" value="<?= h((string)$form['country_code']) ?>">
    </div>

    <fieldset style="margin:18px 0; padding:12px;">
        <legend>Phone</legend>
        <div style="margin:10px 0;">
            <label>Country prefix</label><br>
            <input name="phone_country_prefix" inputmode="numeric" maxlength="4" style="width:120px;" value="<?= h((string)$form['phone_country_prefix']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Area code</label><br>
            <input name="phone_area_code" inputmode="numeric" maxlength="6" style="width:120px;" value="<?= h((string)$form['phone_area_code']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Phone number</label><br>
            <input name="phone_number" inputmode="numeric" maxlength="20" style="width:220px;" value="<?= h((string)$form['phone_number']) ?>">
        </div>
    </fieldset>

    <button type="submit">Create supplier</button>
    <a href="?page=suppliers" style="margin-left:10px;">Cancel</a>
</form>

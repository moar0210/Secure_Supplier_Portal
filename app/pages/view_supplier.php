<h1>Supplier Profile</h1>

<?php if ($updated): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        Supplier profile updated successfully.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <?= Csrf::input(); ?>

    <div style="margin:10px 0;">
        <label>Company name</label><br>
        <input name="company_name" required style="width:520px;" value="<?= h((string)$supplier['company_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Short name</label><br>
        <input name="short_name" style="width:520px;" value="<?= h((string)$supplier['short_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Contact person</label><br>
        <input name="contact_person" required style="width:520px;" value="<?= h((string)$supplier['contact_person']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Email</label><br>
        <input name="email" type="email" required style="width:520px;" value="<?= h((string)$supplier['email']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Homepage</label><br>
        <input name="homepage" style="width:520px;" value="<?= h((string)$supplier['homepage']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>VAT / tax number</label><br>
        <input name="vat_number" style="width:520px;" value="<?= h((string)$supplier['vat_number']) ?>">
    </div>

    <div style="margin:10px 0;">
        <strong>Logo upload</strong><br>
        <span style="opacity:.85;">
            Logo upload is deferred in this prototype until secure file upload and storage handling is implemented.
        </span>
    </div>

    <div style="margin:10px 0;">
        <label>Address line 1</label><br>
        <input name="address_line_1" required style="width:520px;" value="<?= h((string)$supplier['address_line_1']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Address line 2</label><br>
        <input name="address_line_2" style="width:520px;" value="<?= h((string)$supplier['address_line_2']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>City</label><br>
        <input name="city" required style="width:520px;" value="<?= h((string)$supplier['city']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Region / state</label><br>
        <input name="region" style="width:520px;" value="<?= h((string)$supplier['region']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Postal code</label><br>
        <input name="postal_code" style="width:520px;" value="<?= h((string)$supplier['postal_code']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Country code (ISO-2)</label><br>
        <input name="country_code" required maxlength="2" style="width:120px;" value="<?= h((string)$supplier['country_code']) ?>">
    </div>

    <fieldset style="margin:18px 0; padding:12px;">
        <legend>Phone</legend>
        <div style="margin:10px 0;">
            <label>Country prefix</label><br>
            <input name="phone_country_prefix" style="width:120px;" value="<?= h((string)$supplier['phone_country_prefix']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Area code</label><br>
            <input name="phone_area_code" style="width:120px;" value="<?= h((string)$supplier['phone_area_code']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Phone number</label><br>
            <input name="phone_number" style="width:220px;" value="<?= h((string)$supplier['phone_number']) ?>">
        </div>
        <?php if (!empty($supplier['phone_display'])): ?>
            <p style="margin:0; opacity:.8;">Current formatted phone: <?= h((string)$supplier['phone_display']) ?></p>
        <?php endif; ?>
    </fieldset>

    <button type="submit">Save profile</button>
</form>

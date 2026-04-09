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

<form method="post" enctype="multipart/form-data" autocomplete="off">
    <?= Csrf::input(); ?>

    <div style="margin:10px 0;">
        <label>Company name</label><br>
        <input name="company_name" required maxlength="100" style="width:520px;" value="<?= h((string)$supplier['company_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Short name</label><br>
        <input name="short_name" maxlength="100" style="width:520px;" value="<?= h((string)$supplier['short_name']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Contact person</label><br>
        <input name="contact_person" required maxlength="100" style="width:520px;" value="<?= h((string)$supplier['contact_person']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Email</label><br>
        <input name="email" type="email" required maxlength="100" style="width:520px;" value="<?= h((string)$supplier['email']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Homepage</label><br>
        <input name="homepage" type="url" maxlength="100" style="width:520px;" value="<?= h((string)$supplier['homepage']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>VAT / tax number</label><br>
        <input name="vat_number" maxlength="50" style="width:520px;" value="<?= h((string)$supplier['vat_number']) ?>">
    </div>

    <div style="margin:10px 0;">
        <strong>Logo upload</strong><br>
        <?php if (!empty($logo)): ?>
            <div style="margin:8px 0 10px;">
                <img
                    src="?page=supplier_logo&amp;id=<?= (int)$supplier['id_supplier'] ?>&amp;v=<?= rawurlencode((string)($logo['updated_at'] ?? '')) ?>"
                    alt="Current supplier logo"
                    style="display:block;max-width:180px;max-height:180px;border:1px solid #ccc;padding:6px;background:#fff;">
                <div style="margin-top:8px; opacity:.85;">
                    Current file: <?= h((string)($logo['original_filename'] ?? 'supplier-logo')) ?>
                    (<?= number_format(((int)($logo['file_size'] ?? 0)) / 1024, 1) ?> KB)
                </div>
                <label style="display:block;margin-top:8px;">
                    <input type="checkbox" name="remove_logo" value="1">
                    Remove current logo
                </label>
            </div>
        <?php else: ?>
            <div style="margin:8px 0; opacity:.85;">No logo uploaded yet.</div>
        <?php endif; ?>

        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
        <div style="margin-top:6px; opacity:.85;">
            PNG, JPG, or WebP up to 2 MB. Uploaded files are validated and stored outside the web root.
        </div>
    </div>

    <div style="margin:10px 0;">
        <label>Address line 1</label><br>
        <input name="address_line_1" required maxlength="100" style="width:520px;" value="<?= h((string)$supplier['address_line_1']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Address line 2</label><br>
        <input name="address_line_2" maxlength="50" style="width:520px;" value="<?= h((string)$supplier['address_line_2']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>City</label><br>
        <input name="city" required maxlength="30" style="width:520px;" value="<?= h((string)$supplier['city']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Region / state</label><br>
        <input name="region" maxlength="30" style="width:520px;" value="<?= h((string)$supplier['region']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Postal code</label><br>
        <input name="postal_code" maxlength="10" style="width:520px;" value="<?= h((string)$supplier['postal_code']) ?>">
    </div>

    <div style="margin:10px 0;">
        <label>Country code (ISO-2)</label><br>
        <input name="country_code" required maxlength="2" pattern="[A-Za-z]{2}" style="width:120px;" value="<?= h((string)$supplier['country_code']) ?>">
    </div>

    <fieldset style="margin:18px 0; padding:12px;">
        <legend>Phone</legend>
        <div style="margin:10px 0;">
            <label>Country prefix</label><br>
            <input name="phone_country_prefix" inputmode="numeric" maxlength="4" style="width:120px;" value="<?= h((string)$supplier['phone_country_prefix']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Area code</label><br>
            <input name="phone_area_code" inputmode="numeric" maxlength="6" style="width:120px;" value="<?= h((string)$supplier['phone_area_code']) ?>">
        </div>
        <div style="margin:10px 0;">
            <label>Phone number</label><br>
            <input name="phone_number" inputmode="numeric" maxlength="20" style="width:220px;" value="<?= h((string)$supplier['phone_number']) ?>">
        </div>
        <?php if (!empty($supplier['phone_display'])): ?>
            <p style="margin:0; opacity:.8;">Current formatted phone: <?= h((string)$supplier['phone_display']) ?></p>
        <?php endif; ?>
    </fieldset>

    <button type="submit">Save profile</button>
</form>

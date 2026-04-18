<div class="page-header">
    <h1>Portal users</h1>
</div>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="admin_users">
    <div class="filter-bar__title">Filters</div>
    <div class="field-row">
        <div class="field">
            <label>Search</label>
            <input name="search" value="<?= h((string)$filters['search']) ?>" placeholder="Username, email, supplier">
        </div>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <?php foreach (['ALL', 'ADMIN', 'SUPPLIER'] as $role): ?>
                    <option value="<?= h($role) ?>" <?= strtoupper((string)$filters['role']) === $role ? 'selected' : '' ?>>
                        <?= h($role) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach (['ALL', 'ACTIVE', 'INACTIVE'] as $statusOption): ?>
                    <option value="<?= h($statusOption) ?>" <?= strtoupper((string)$filters['status']) === $statusOption ? 'selected' : '' ?>>
                        <?= h($statusOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Supplier</label>
            <select name="supplier_id">
                <option value="">All suppliers</option>
                <?php foreach ($supplierOptions as $supplier): ?>
                    <?php $supplierId = (int)$supplier['id_supplier']; ?>
                    <option value="<?= $supplierId ?>" <?= (string)$supplierId === (string)$filters['supplier_id'] ? 'selected' : '' ?>>
                        <?= h((string)$supplier['supplier_name']) ?> (#<?= $supplierId ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <a href="?page=admin_users" class="muted small">Reset</a>
    </div>
</form>

<h2>Create user</h2>
<form method="post" class="card mb-5">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <div class="field-row">
        <div class="field">
            <label>Username</label>
            <input name="username" required maxlength="50">
        </div>
        <div class="field">
            <label>Email</label>
            <input name="email" type="email" required maxlength="255">
        </div>
        <div class="field">
            <label>Role</label>
            <select name="role_name">
                <option value="SUPPLIER">SUPPLIER</option>
                <option value="ADMIN">ADMIN</option>
            </select>
        </div>
        <div class="field">
            <label>Supplier</label>
            <select name="supplier_id">
                <option value="">No supplier</option>
                <?php foreach ($supplierOptions as $supplier): ?>
                    <option value="<?= (int)$supplier['id_supplier'] ?>">
                        <?= h((string)$supplier['supplier_name']) ?> (#<?= (int)$supplier['id_supplier'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="field-row mt-3">
        <div class="field">
            <label>Password</label>
            <input name="password" type="password" required>
        </div>
        <div class="field">
            <label>Confirm password</label>
            <input name="confirm_password" type="password" required>
        </div>
    </div>
    <div class="mt-3">
        <input type="hidden" name="is_active" value="0">
        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
    </div>
    <div class="form-actions">
        <button type="submit">Create user</button>
    </div>
</form>

<h2>Existing users</h2>
<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No portal users found.</p></div>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <?php $roles = (array)($row['roles'] ?? []); ?>
        <form method="post" class="card mb-3">
            <?= Csrf::input(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

            <div class="field-row">
                <div class="field">
                    <label>Username</label>
                    <input name="username" required maxlength="50" value="<?= h((string)$row['username']) ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input name="email" type="email" required maxlength="255" value="<?= h((string)$row['email']) ?>">
                </div>
                <div class="field">
                    <label>Role</label>
                    <select name="role_name">
                        <option value="SUPPLIER" <?= in_array('SUPPLIER', $roles, true) ? 'selected' : '' ?>>SUPPLIER</option>
                        <option value="ADMIN" <?= in_array('ADMIN', $roles, true) ? 'selected' : '' ?>>ADMIN</option>
                    </select>
                </div>
                <div class="field">
                    <label>Supplier</label>
                    <select name="supplier_id">
                        <option value="">No supplier</option>
                        <?php foreach ($supplierOptions as $supplier): ?>
                            <?php $supplierId = (int)$supplier['id_supplier']; ?>
                            <option value="<?= $supplierId ?>" <?= (int)($row['supplier_id'] ?? 0) === $supplierId ? 'selected' : '' ?>>
                                <?= h((string)$supplier['supplier_name']) ?> (#<?= $supplierId ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field-row mt-3">
                <div class="field">
                    <label>New password</label>
                    <input name="password" type="password" placeholder="Leave blank to keep">
                </div>
                <div class="field">
                    <label>Confirm password</label>
                    <input name="confirm_password" type="password" placeholder="Leave blank to keep">
                </div>
            </div>

            <div class="mt-3">
                <input type="hidden" name="is_active" value="0">
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Active</label>
                <?php if ((int)$row['id'] === (int)$currentUserId): ?>
                    <span class="muted small" style="margin-left:10px;">Current session</span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit">Save changes</button>
            </div>
            <p class="muted small mt-3 mb-0">
                Roles: <?= h((string)($row['role_names'] ?: 'None')) ?>
                <?php if (!empty($row['supplier_name'])): ?>
                    &middot; Supplier: <?= h((string)$row['supplier_name']) ?> (#<?= (int)$row['supplier_id'] ?>)
                <?php endif; ?>
                &middot; Last login: <?= h((string)($row['last_login_at'] ?? 'never')) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

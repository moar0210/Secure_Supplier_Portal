<h1>Admin - Portal Users</h1>

<?php if ($notice): ?>
    <div style="padding:8px;border:1px solid #080;background:#efe;margin-bottom:12px;">
        <?= h((string)$notice) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:8px;border:1px solid #a00;background:#fee;margin-bottom:12px;">
        <?= h((string)$error) ?>
    </div>
<?php endif; ?>

<form method="get" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <input type="hidden" name="page" value="admin_users">
    <strong>Filters</strong>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;">
        <div>
            <label>Search</label><br>
            <input name="search" value="<?= h((string)$filters['search']) ?>" placeholder="Username, email, supplier">
        </div>
        <div>
            <label>Role</label><br>
            <select name="role">
                <?php foreach (['ALL', 'ADMIN', 'SUPPLIER'] as $role): ?>
                    <option value="<?= h($role) ?>" <?= strtoupper((string)$filters['role']) === $role ? 'selected' : '' ?>>
                        <?= h($role) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Status</label><br>
            <select name="status">
                <?php foreach (['ALL', 'ACTIVE', 'INACTIVE'] as $status): ?>
                    <option value="<?= h($status) ?>" <?= strtoupper((string)$filters['status']) === $status ? 'selected' : '' ?>>
                        <?= h($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Supplier</label><br>
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
    <button type="submit" style="margin-top:10px;">Apply filters</button>
    <a href="?page=admin_users" style="margin-left:10px;">Reset</a>
</form>

<h2>Create User</h2>
<form method="post" style="padding:12px;border:1px solid #ccc;margin-bottom:18px;">
    <?= Csrf::input(); ?>
    <input type="hidden" name="action" value="create">
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div>
            <label>Username</label><br>
            <input name="username" required maxlength="50">
        </div>
        <div>
            <label>Email</label><br>
            <input name="email" type="email" required maxlength="255">
        </div>
        <div>
            <label>Role</label><br>
            <select name="role_name">
                <option value="SUPPLIER">SUPPLIER</option>
                <option value="ADMIN">ADMIN</option>
            </select>
        </div>
        <div>
            <label>Supplier</label><br>
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
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;">
        <div>
            <label>Password</label><br>
            <input name="password" type="password" required>
        </div>
        <div>
            <label>Confirm password</label><br>
            <input name="confirm_password" type="password" required>
        </div>
    </div>
    <div style="margin-top:10px;">
        <input type="hidden" name="is_active" value="0">
        <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
    </div>
    <button type="submit" style="margin-top:10px;">Create user</button>
</form>

<h2>Existing Users</h2>
<?php if (!$rows): ?>
    <p>No portal users found.</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <?php $roles = (array)($row['roles'] ?? []); ?>
        <form method="post" style="padding:12px;border:1px solid #ccc;margin-bottom:12px;">
            <?= Csrf::input(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <div>
                    <label>Username</label><br>
                    <input name="username" required maxlength="50" value="<?= h((string)$row['username']) ?>">
                </div>
                <div>
                    <label>Email</label><br>
                    <input name="email" type="email" required maxlength="255" value="<?= h((string)$row['email']) ?>">
                </div>
                <div>
                    <label>Role</label><br>
                    <select name="role_name">
                        <option value="SUPPLIER" <?= in_array('SUPPLIER', $roles, true) ? 'selected' : '' ?>>SUPPLIER</option>
                        <option value="ADMIN" <?= in_array('ADMIN', $roles, true) ? 'selected' : '' ?>>ADMIN</option>
                    </select>
                </div>
                <div>
                    <label>Supplier</label><br>
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

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;">
                <div>
                    <label>New password</label><br>
                    <input name="password" type="password" placeholder="Leave blank to keep">
                </div>
                <div>
                    <label>Confirm password</label><br>
                    <input name="confirm_password" type="password" placeholder="Leave blank to keep">
                </div>
            </div>

            <div style="margin-top:10px;">
                <input type="hidden" name="is_active" value="0">
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($row['is_active']) ? 'checked' : '' ?>> Active</label>
                <?php if ((int)$row['id'] === (int)$currentUserId): ?>
                    <span style="margin-left:10px;opacity:.8;">Current session</span>
                <?php endif; ?>
            </div>

            <button type="submit" style="margin-top:10px;">Save changes</button>
            <p style="margin:10px 0 0;opacity:.85;">
                Roles: <?= h((string)($row['role_names'] ?: 'None')) ?>
                <?php if (!empty($row['supplier_name'])): ?>
                    | Supplier: <?= h((string)$row['supplier_name']) ?> (#<?= (int)$row['supplier_id'] ?>)
                <?php endif; ?>
                | Last login: <?= h((string)($row['last_login_at'] ?? 'never')) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

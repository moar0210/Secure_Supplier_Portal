<?php
$activeFilter = strtoupper((string)($filters['status'] ?? 'ALL'));
$roleFilter = strtoupper((string)($filters['role'] ?? 'ALL'));
$searchTerm = trim((string)($filters['search'] ?? ''));
$supplierFilter = trim((string)($filters['supplier_id'] ?? ''));
$hasFilters = $searchTerm !== '' || $roleFilter !== 'ALL' || $activeFilter !== 'ALL' || $supplierFilter !== '';

$initials = static function (string $username): string {
    $parts = preg_split('/[\s._-]+/', $username) ?: [];
    $a = isset($parts[0][0]) ? $parts[0][0] : '';
    $b = isset($parts[1][0]) ? $parts[1][0] : (isset($parts[0][1]) ? $parts[0][1] : '');
    return strtoupper($a . $b);
};

$roleBadge = static function (string $role): string {
    $role = strtoupper(trim($role));
    if ($role === 'ADMIN') {
        return '<span class="badge badge--admin">Admin</span>';
    }
    if ($role === 'SUPPLIER') {
        return '<span class="badge badge--supplier">Supplier</span>';
    }
    return '<span class="badge">' . h($role ?: 'None') . '</span>';
};
?>
<div class="page-header">
    <div class="page-header__title-group">
        <h1>Portal users</h1>
        <p class="page-header__subtitle">Manage user accounts, roles and supplier assignments.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" data-collapsible-target="createUserPanel" aria-expanded="false">
            + Create user
        </button>
    </div>
</div>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<div class="collapsible" id="createUserPanel">
    <div class="modal__header">
        <h2 class="modal__title">Create new user</h2>
        <button type="button" class="modal__close" data-collapsible-close="createUserPanel" aria-label="Close">&times;</button>
    </div>
    <div class="collapsible__body">
        <form method="post">
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
                        <option value="SUPPLIER">Supplier</option>
                        <option value="ADMIN">Admin</option>
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
                <button type="button" class="btn-secondary" data-collapsible-close="createUserPanel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="hidden" name="page" value="admin_users">
    <div class="field-row">
        <div class="field" style="flex: 2 1 320px;">
            <label>Search</label>
            <input name="search" value="<?= h($searchTerm) ?>" placeholder="Username, email, supplier…">
        </div>
        <div class="field">
            <label>Role</label>
            <select name="role">
                <?php foreach (['ALL' => 'All roles', 'ADMIN' => 'Admin', 'SUPPLIER' => 'Supplier'] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $roleFilter === $value ? 'selected' : '' ?>>
                        <?= h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <?php foreach (['ALL' => 'All status', 'ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= $activeFilter === $value ? 'selected' : '' ?>>
                        <?= h($label) ?>
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
                    <option value="<?= $supplierId ?>" <?= (string)$supplierId === $supplierFilter ? 'selected' : '' ?>>
                        <?= h((string)$supplier['supplier_name']) ?> (#<?= $supplierId ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="filter-bar__actions">
        <button type="submit">Apply filters</button>
        <?php if ($hasFilters): ?>
            <a href="?page=admin_users" class="muted small">Reset</a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card card--muted"><p class="mb-0 muted">No portal users found.</p></div>
<?php else: ?>
    <div class="table-card mb-5">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th style="width:110px;">Role</th>
                    <th>Supplier</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $roles = (array)($row['roles'] ?? []);
                    $primaryRole = strtoupper((string)($roles[0] ?? ''));
                    $isActive = !empty($row['is_active']);
                    $username = (string)$row['username'];
                    $rowId = 'user-row-' . (int)$row['id'];
                    ?>
                    <tr>
                        <td class="muted"><?= (int)$row['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="row-card__avatar"><?= h($initials($username) ?: '·') ?></span>
                                <strong><?= h($username) ?></strong>
                            </div>
                        </td>
                        <td><?= h((string)$row['email']) ?></td>
                        <td><?= $roleBadge($primaryRole) ?></td>
                        <td>
                            <?php if (!empty($row['supplier_name'])): ?>
                                <?= h((string)$row['supplier_name']) ?>
                                <span class="muted small">#<?= (int)$row['supplier_id'] ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
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
                                data-collapsible-target="<?= h($rowId) ?>"
                                aria-expanded="false"
                                title="Edit user">
                                Edit
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" style="padding:0;background:var(--color-surface-subtle);">
                            <div class="collapsible" id="<?= h($rowId) ?>" style="margin:0;border:0;border-radius:0;border-top:1px solid var(--color-border);background:transparent;">
                                <div class="collapsible__body" style="border-top:0;">
                                    <form method="post">
                                        <?= Csrf::input(); ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

                                        <div class="field-row">
                                            <div class="field">
                                                <label>Username</label>
                                                <input name="username" required maxlength="50" value="<?= h($username) ?>">
                                            </div>
                                            <div class="field">
                                                <label>Email</label>
                                                <input name="email" type="email" required maxlength="255" value="<?= h((string)$row['email']) ?>">
                                            </div>
                                            <div class="field">
                                                <label>Role</label>
                                                <select name="role_name">
                                                    <option value="SUPPLIER" <?= in_array('SUPPLIER', $roles, true) ? 'selected' : '' ?>>Supplier</option>
                                                    <option value="ADMIN" <?= in_array('ADMIN', $roles, true) ? 'selected' : '' ?>>Admin</option>
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
                                            <label><input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>> Active</label>
                                            <?php if ((int)$row['id'] === (int)$currentUserId): ?>
                                                <span class="muted small" style="margin-left:10px;">Current session</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit">Save changes</button>
                                            <button type="button" class="btn-secondary" data-collapsible-close="<?= h($rowId) ?>">Cancel</button>
                                        </div>
                                        <p class="muted small mt-3 mb-0">
                                            Last login: <?= h((string)($row['last_login_at'] ?? 'never')) ?>
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

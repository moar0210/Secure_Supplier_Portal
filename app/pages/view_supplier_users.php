<?php
$initials = static function (string $username): string {
    $parts = preg_split('/[\s._-]+/', $username) ?: [];
    $a = isset($parts[0][0]) ? $parts[0][0] : '';
    $b = isset($parts[1][0]) ? $parts[1][0] : (isset($parts[0][1]) ? $parts[0][1] : '');
    return strtoupper($a . $b);
};
?>
<div class="page-header">
    <div class="page-header__title-group">
        <h1>Company users</h1>
        <p class="page-header__subtitle">Manage the supplier-linked users who can access your company account in the portal.</p>
    </div>
    <div class="page-header__actions">
        <button type="button" data-collapsible-target="createCompanyUserPanel" aria-expanded="false">
            + Create company user
        </button>
    </div>
</div>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<div class="collapsible" id="createCompanyUserPanel">
    <div class="modal__header">
        <h2 class="modal__title">Create company user</h2>
        <button type="button" class="modal__close" data-collapsible-close="createCompanyUserPanel" aria-label="Close">&times;</button>
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
                <button type="submit">Create company user</button>
                <button type="button" class="btn-secondary" data-collapsible-close="createCompanyUserPanel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No supplier-linked users found yet.</p>
    </div>
<?php else: ?>
    <div class="table-card mb-5">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>User</th>
                    <th>Email</th>
                    <th style="width:140px;">Last login</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isActive = !empty($row['is_active']);
                    $username = (string)$row['username'];
                    $rowId = 'company-user-row-' . (int)$row['id'];
                    ?>
                    <tr>
                        <td class="muted"><?= (int)$row['id'] ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="row-card__avatar"><?= h($initials($username) ?: '·') ?></span>
                                <strong><?= h($username) ?></strong>
                                <?php if ((int)$row['id'] === (int)$currentUserId): ?>
                                    <span class="muted small">· you</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= h((string)$row['email']) ?></td>
                        <td class="muted small"><?= h((string)($row['last_login_at'] ?? 'never')) ?></td>
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
                        <td colspan="6" style="padding:0;background:var(--color-surface-subtle);">
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
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit">Save changes</button>
                                            <button type="button" class="btn-secondary" data-collapsible-close="<?= h($rowId) ?>">Cancel</button>
                                        </div>
                                        <p class="muted small mt-3 mb-0">
                                            Role: <?= h((string)($row['role_names'] ?: 'SUPPLIER')) ?>
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

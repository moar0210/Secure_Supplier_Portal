<div class="page-header">
    <h1>Company users</h1>
</div>

<p class="muted">Manage the supplier-linked users who can access your company account in the portal.</p>

<?php if ($notice): ?>
    <div class="alert alert--success"><?= h((string)$notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--error"><?= h((string)$error) ?></div>
<?php endif; ?>

<h2>Create company user</h2>
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
    </div>
</form>

<h2>Existing company users</h2>
<?php if (!$rows): ?>
    <div class="card card--muted">
        <p class="mb-0 muted">No supplier-linked users found yet.</p>
    </div>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
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
                Role: <?= h((string)($row['role_names'] ?: 'SUPPLIER')) ?>
                &middot; Last login: <?= h((string)($row['last_login_at'] ?? 'never')) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

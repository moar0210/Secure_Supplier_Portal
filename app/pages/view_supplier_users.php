<h1>Company Users</h1>

<p>Manage the supplier-linked users who can access your company account in the portal.</p>

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

<h2>Create Company User</h2>
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
    <button type="submit" style="margin-top:10px;">Create company user</button>
</form>

<h2>Existing Company Users</h2>
<?php if (!$rows): ?>
    <p>No supplier-linked users found yet.</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
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
                Role: <?= h((string)($row['role_names'] ?: 'SUPPLIER')) ?>
                | Last login: <?= h((string)($row['last_login_at'] ?? 'never')) ?>
            </p>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

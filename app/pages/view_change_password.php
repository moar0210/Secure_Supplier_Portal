<div class="auth-card">
    <h1>Change password</h1>

    <?php if (!empty($forced) && empty($success)): ?>
        <div class="alert alert--warning">
            You must choose a new password before continuing.
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert--success">
            Your password has been updated.
        </div>
        <p><a href="?page=home">Continue to the dashboard</a>.</p>
    <?php else: ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?= h((string)$error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="form-stack">
            <?= Csrf::input(); ?>
            <div class="field">
                <label>Current password</label>
                <input name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label>New password</label>
                <input name="new_password" type="password" required autocomplete="new-password">
                <small class="muted">Minimum 10 characters, with at least one letter and one number.</small>
            </div>
            <div class="field">
                <label>Confirm new password</label>
                <input name="confirm_password" type="password" required autocomplete="new-password">
            </div>
            <div class="form-actions">
                <button type="submit">Update password</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="auth-card">
    <h1>Choose a new password</h1>

    <?php if ($success): ?>
        <div class="alert alert--success">
            Your password has been updated successfully. You can now <a href="?page=login">sign in</a>.
        </div>
    <?php elseif (!$isValidToken): ?>
        <div class="alert alert--error">
            The reset link is invalid or has expired.
        </div>
        <p><a href="?page=reset_request">Create a new reset request</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert--error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="?page=reset_password&amp;username=<?= rawurlencode($username) ?>&amp;token=<?= rawurlencode($token) ?>"
            autocomplete="off"
            class="form-stack">
            <?= Csrf::input(); ?>

            <div class="field">
                <label>New password</label>
                <input name="new_password" type="password" required autocomplete="new-password">
            </div>

            <div class="field">
                <label>Confirm new password</label>
                <input name="confirm_password" type="password" required autocomplete="new-password">
            </div>

            <p class="muted small mb-0">
                Passwords must be at least 10 characters and include at least one letter and one number.
            </p>

            <div class="form-actions">
                <button type="submit">Update password</button>
            </div>
        </form>
    <?php endif; ?>
</div>

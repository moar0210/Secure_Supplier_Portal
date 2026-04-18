<div class="auth-card">
    <h1>Reset password</h1>

    <p>Enter your username or email address to create a password reset request.</p>

    <?php if ($error): ?>
        <div class="alert alert--error">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($submitted && !$error): ?>
        <div class="alert alert--success">
            If the account exists, a reset request has been created.
            <?php if ($resetLink): ?>
                <p class="mt-3 mb-0">
                    Because this offline/local thesis build does not use email delivery, the one-time reset link is shown below and only on this response.
                </p>
                <p class="mt-3 mb-0">Expires at: <strong><?= h((string)$resetExpiresAt) ?></strong></p>
                <p class="mt-3 mb-0" style="word-break:break-all;">
                    <a href="<?= h($resetLink) ?>"><?= h($resetLink) ?></a>
                </p>
            <?php else: ?>
                <p class="mt-3 mb-0 muted small">If the account does not exist, no reset link is generated.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" class="form-stack">
        <?= Csrf::input(); ?>
        <div class="field">
            <label>Username or email</label>
            <input name="identifier" required value="<?= h($identifier) ?>">
        </div>
        <div class="form-actions">
            <button type="submit">Create reset request</button>
            <a href="?page=login" class="muted small">Back to sign in</a>
        </div>
    </form>
</div>

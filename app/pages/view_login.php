<?php if ($alreadyLoggedIn): ?>
    <div class="auth-card">
        <h1>Already signed in</h1>
        <p>You are signed in as <strong><?= h((string)$username) ?></strong>.</p>
        <p><a href="?page=home">Go to the dashboard</a>.</p>
        <form method="post" action="?page=logout" class="form-actions">
            <?= Csrf::input(); ?>
            <button type="submit" class="btn-secondary">Sign out</button>
        </form>
    </div>
<?php else: ?>
    <div class="auth-card">
        <h1>Sign in</h1>

        <?php if ($timedOut): ?>
            <div class="alert alert--warning">
                Your session timed out due to inactivity. Please sign in again.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert--error">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="form-stack">
            <?= Csrf::input(); ?>
            <div class="field">
                <label>Username or email</label>
                <input name="identifier" required autocomplete="username" value="<?= h($identifier) ?>">
            </div>
            <div class="field">
                <label>Password</label>
                <input name="password" type="password" required autocomplete="current-password">
            </div>
            <div class="form-actions">
                <button type="submit">Sign in</button>
                <a href="?page=reset_request" class="muted small">Forgot your password?</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($alreadyLoggedIn): ?>
    <p>You are already logged in as <strong><?= h((string)$username) ?></strong>.</p>
    <p><a href="?page=home">Go home</a>.</p>
    <form method="post" action="?page=logout" style="margin-top:10px;">
        <?= Csrf::input(); ?>
        <button type="submit">Logout</button>
    </form>
<?php else: ?>
    <h1>Login</h1>

    <?php if ($timedOut): ?>
        <div style="padding:8px;border:1px solid #aa0;background:#fffbdd;margin-bottom:10px;">
            Your session timed out due to inactivity. Please log in again.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="padding:8px;border:1px solid #a00;background:#fee;">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?= Csrf::input(); ?>
        <div>
            <label>Username or Email</label><br>
            <input name="identifier" required autocomplete="username" value="<?= h($identifier) ?>">
        </div>
        <div>
            <label>Password</label><br>
            <input name="password" type="password" required autocomplete="current-password">
        </div>
        <button type="submit">Login</button>
    </form>

    <p style="margin-top:12px;">
        <a href="?page=reset_request">Forgot your password?</a>
    </p>
<?php endif; ?>
